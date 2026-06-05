# Implementation Plan: deprecate-scope

## Overview

执行策略：自底向上，先改基础设施（异常、枚举、Registry），再改消费层（trait、Command、Service），最后删除废弃模块。每个 top-level task 末尾有 checkpoint 确保增量可验证。

关键决策引用：
- CR-1：统一 deprecated 工厂方法（`InvalidScopeException::deprecated()`）
- CR-2：IO 层立即验证（Registry parse 时 fail-fast）
- CR-3：内容 hash 比对判定幂等
- CR-4：baseline diff 判定 show 状态

## Tasks

- [ ] 1. 基础类型与异常
  - [ ] 1.1 精简 `InvalidScopeException`
    - 新增 `deprecated(string $feature, string $replacement): self` 工厂方法
    - 删除 `unknownScope()`、`projectOnlyInUserScope()` 方法
    - 编写测试验证消息格式与参数化
    - _Ref: Requirement 1, AC 1; Requirement 2, AC 1_
  - [ ] 1.2 精简 `DeployScope` 枚举
    - 删除 `User` case，仅保留 `Project`
    - _Ref: Requirement 1, AC 6_
  - [ ] 1.3 精简 `AbilityEntry`
    - 删除构造函数中 `$scopes` 参数和属性
    - _Ref: Requirement 4, AC 1_
  - [ ] 1.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(service) by Kiro: simplify InvalidScopeException, DeployScope, AbilityEntry — remove scope-related members`

- [ ] 2. Registry 遗留字段验证与 bootstrap section
  - [ ] 2.1 `AbilityRegistry::parse()` 新增遗留字段检测
    - 发现 entry 含 `scopes` key → 收入 validationErrors，首个违规 fail-fast
    - 发现顶层 `global-setup` key → 收入 validationErrors，首个违规 fail-fast
    - 移除原 `resolveScopes()` 解析逻辑
    - 编写测试：scopes field → error；global-setup key → error；正常 yaml → success
    - _Ref: Requirement 4, AC 1-4_
  - [ ] 2.2 `AbilityRegistry::bootstrapIncludes()` 方法
    - 将原 `globalSetupIncludes()` 更名为 `bootstrapIncludes()`
    - 读取 `bootstrap.includes` section，返回 `list<array{type: string, path: string}>`
    - 编写测试：正常 list、空 list、缺失 section、非字符串条目、无冒号条目
    - _Ref: Requirement 5, AC 1-5_
  - [ ] 2.3 删除 `AbilityRegistry::projectOnlyPaths()` 方法
    - _Ref: Requirement 4, AC 1_
  - [ ] 2.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(config) by Kiro: AbilityRegistry — add legacy field validation and rename globalSetupIncludes to bootstrapIncludes`

- [ ] 3. `HandlesDeployScopeOption` trait 重写
  - [ ] 3.1 重写 trait 为废弃检测
    - `configureDeployScopeOption()`：注册 `--scope` 为 `VALUE_OPTIONAL`，description 标记 `[REMOVED]`
    - 新增 `rejectIfScopeProvided(InputInterface, SymfonyStyle): bool`
    - 删除 `resolveDeployScopeOption()`、`guardInstallBatch()`、`guardPresetInstall()`
    - 编写测试：各值（project/user/空/null）→ reject；未传 → pass
    - _Ref: Requirement 1, AC 1-7_
  - [ ] 3.2 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(command) by Kiro: rewrite HandlesDeployScopeOption — reject all --scope usage with deprecated error`

- [ ] 4. Install/Uninstall/Show 命令接入废弃检测
  - [ ] 4.1 `InstallCommand` 接入 `rejectIfScopeProvided()`
    - `execute()` 开头调用 `rejectIfScopeProvided()`，返回 false 则 return FAILURE
    - 移除内部 scope 解析与传递逻辑
    - _Ref: Requirement 1, AC 1_
  - [ ] 4.2 `ShowCommand` 接入 `rejectIfScopeProvided()`
    - 同上模式
    - _Ref: Requirement 1, AC 2_
  - [ ] 4.3 Typed Install 命令接入（`SkillInstallCommand`、`RuleInstallCommand`、`AgentInstallCommand`）
    - 各命令 `execute()` 开头调用 `rejectIfScopeProvided()`
    - _Ref: Requirement 1, AC 3_
  - [ ] 4.4 Typed Uninstall 命令接入（`SkillUninstallCommand`、`RuleUninstallCommand`、`AgentUninstallCommand`）
    - 各命令 `execute()` 开头调用 `rejectIfScopeProvided()`
    - _Ref: Requirement 1, AC 4_
  - [ ] 4.5 `PresetUninstallCommand` 接入 `rejectIfScopeProvided()`
    - `execute()` 开头调用 `rejectIfScopeProvided()`，返回 false 则 return FAILURE
    - _Ref: Requirement 1, AC 4_
  - [ ] 4.6 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(command) by Kiro: wire rejectIfScopeProvided into install, show, and typed install/uninstall commands`

- [ ] 5. `GlobalSetupCommand` 退化
  - [ ] 5.1 退化为 deprecated 桩
    - `execute()` 调用 `InvalidScopeException::deprecated('global-setup command', ...)`，输出 error 并 return FAILURE
    - 移除 `GlobalSetupService` 构造函数依赖
    - 保留命令名和选项定义（确保 `--force`、`--target` 等不报 unknown option）
    - 编写测试：执行命令 → exit 1 + deprecated 消息；带任意 flags → 同样 exit 1
    - _Ref: Requirement 2, AC 1-3_
  - [ ] 5.2 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(command) by Kiro: degrade GlobalSetupCommand to deprecated stub`

- [ ] 6. Service 层简化
  - [ ] 6.1 `DeployRootResolver` 简化
    - 移除 `UserHomeResolver` 依赖与构造函数参数
    - `resolve()` 移除 `DeployScope` 参数，始终返回 project root
    - `absoluteTargetPath()` 移除 `DeployScope` 参数
    - 删除 `parseScopeOption()` 方法
    - _Ref: Requirement 1, AC 6_
  - [ ] 6.2 `InstallationProbe` 简化
    - 移除 `$scope` 参数，硬编码 project
    - _Ref: Requirement 1, AC 6_
  - [ ] 6.3 `Installer` 简化
    - `installTyped()` / `uninstallTyped()` 移除 `$scope` 参数
    - 内部 helper 移除 scope 参数
    - 移除 `Scope: project|user` 输出行
    - _Ref: Requirement 1, AC 6; Requirement 3, AC 2_
  - [ ] 6.4 `CheckService` 简化
    - 移除 `checkTypedForScope()` 方法
    - `checkTyped()` 直接使用 project root
    - _Ref: Requirement 1, AC 6_
  - [ ] 6.5 `AbilityUpdateService` 简化
    - `reportChanges()` 仅遍历 project scope，移除 `DeployScope::User` 迭代
    - 输出格式中移除 `({scope})` 部分
    - _Ref: Requirement 7, AC 1-3_
  - [ ] 6.6 `ShowStatusPresenter` 简化
    - `rows()` / `lines()` 移除 `$scopeFilter` 参数
    - 删除 `mergeStatuses()` 方法
    - 移除 scope label 生成逻辑
    - 状态判定使用 `AbilityDiffService` 做 baseline diff
    - 编写测试：输出无 scope 标签；三种状态正确
    - _Ref: Requirement 6, AC 1-3_
  - [ ] 6.7 `CleanupCommand` 文案清理
    - 替换输出文案，移除 "user-scope global-setup is unchanged" 措辞
    - 添加 `/apm init` 重新安装指引文案
    - 编写测试：输出不含 "user-scope"、"user scope"、"global-setup"、"global setup"
    - _Ref: Requirement 8, AC 1-3_
  - [ ] 6.8 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(service) by Kiro: remove scope parameter from service layer — project-only model`

- [ ] 7. `BootstrapCommand` 扩展
  - [ ] 7.1 注入 `Installer` 和 `AbilityRegistry` 依赖
    - 构造函数新增 `Installer` 和 `AbilityRegistry` 可选参数
    - _Ref: Requirement 3, AC 2_
  - [ ] 7.2 实现 ability install 阶段
    - scaffold 完成后读取 `bootstrapIncludes()`
    - 为空 → 直接返回成功
    - 遍历 includes：使用 `AbilityDiffService` 做 hash 比对判定幂等
    - 已安装且 hash 一致且无 `--force` → skip 并输出 skip 行
    - 已安装且 `--force` → 覆盖安装
    - hash 不一致或目标不存在 → 执行 install
    - 安装失败 → 输出 `[fail]`，继续剩余项
    - 全部完成后若有失败项 → exit 1
    - 编写测试：scaffold + includes 安装成功；幂等 skip；force 覆盖；install 失败不回滚 scaffold；scaffold 失败不进入 install
    - _Ref: Requirement 3, AC 1-8_
  - [ ] 7.3 处理 scaffold 失败场景
    - scaffold 阶段失败（文件已存在无 `--force`）→ exit 1，不进入 ability install
    - _Ref: Requirement 3, AC 8_
  - [ ] 7.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(command) by Kiro: extend BootstrapCommand — scaffold + ability install with hash-based idempotency`

- [ ] 8. 删除废弃模块
  - [ ] 8.1 删除 `ScopeGuard` 类
    - 删除 `src/Service/ScopeGuard.php` 及其测试
    - _Ref: Requirement 1, AC 6_
  - [ ] 8.2 删除 `UserHomeResolver` 类
    - 删除 `src/Service/UserHomeResolver.php` 及其测试
    - _Ref: Requirement 1, AC 6_
  - [ ] 8.3 删除 `DefaultGlobalSetupService` 和 `GlobalSetupService` 接口
    - 删除 `src/Service/DefaultGlobalSetupService.php`、`src/Service/GlobalSetupService.php` 及其测试
    - _Ref: Requirement 2, AC 2_
  - [ ] 8.4 删除 `GlobalInstallDetector` 类
    - 删除 `src/Service/GlobalInstallDetector.php` 及其测试（user scope 检测已无用途）
    - _Ref: Requirement 1, AC 6_
  - [ ] 8.5 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `-(service) by Kiro: remove ScopeGuard, UserHomeResolver, DefaultGlobalSetupService, GlobalSetupService, GlobalInstallDetector`

- [ ] 9. `abilities.yaml` 配置更新
  - [ ] 9.1 更新 `abilities.yaml`
    - 删除所有 entry 的 `scopes` 字段
    - 将 `global-setup` section 改名为 `bootstrap`
    - _Ref: Requirement 4, AC 1-2; Requirement 5, AC 1_
  - [ ] 9.2 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `*(config) by Kiro: rename global-setup to bootstrap and remove scopes field from abilities.yaml`

- [ ] 10. E2E 测试
  - [ ] 10.1 `bin/apm global-setup` → exit 1 + deprecated 消息
    - _Ref: Requirement 2, AC 1_
  - [ ] 10.2 `bin/apm install gitflow --scope user` → exit 1 + deprecated 消息
    - _Ref: Requirement 1, AC 1_
  - [ ] 10.3 `bin/apm show --scope project` → exit 1 + deprecated 消息
    - _Ref: Requirement 1, AC 2, AC 7_
  - [ ] 10.4 `bin/apm skill:install apm --scope user` → exit 1 + deprecated 消息
    - _Ref: Requirement 1, AC 3_
  - [ ] 10.5 `bin/apm bootstrap -t cursor -t kiro` → scaffold + includes 安装成功
    - _Ref: Requirement 3, AC 1-2_
  - [ ] 10.6 `bin/apm bootstrap -t cursor -t kiro`（重复执行）→ includes 被 skip
    - _Ref: Requirement 3, AC 3_
  - [ ] 10.7 `bin/apm show` → 输出无 scope 标签
    - _Ref: Requirement 6, AC 1-2_
  - [ ] 10.8 Checkpoint
    - 运行验证：`./vendor/bin/phpunit --testsuite e2e`
    - commit: `+(e2e) by Kiro: add E2E tests for scope deprecation`

- [ ] 11. 文档收敛
  - [ ] 11.1 更新 `docs/state/cli-commands.md`
    - 移除 `--scope` 参数（含 `[--scope project|user]`）从所有命令签名和错误条件表
    - 标记 `global-setup` 为 removed，注明 `bootstrap` 为替代
    - 更新 `bootstrap` 命令文档（新增 ability install 阶段描述）
    - _Ref: Requirement 9, AC 1-2_
  - [ ] 11.2 更新 `docs/state/deploy-scope.md`
    - 重写为 project-scope-only 路径解析
    - 移除 User Root resolution、User Scope rows、ScopeGuard section、`InvalidScopeException::projectOnlyInUserScope` 工厂方法文档
    - _Ref: Requirement 9, AC 4_
  - [ ] 11.3 更新 `docs/state/abilities-model.md`
    - 移除 `scopes` 字段行、`global-setup` section（含 `global-setup.includes` 描述）、Project-Only 概念（ScopeGuard 引用、`projectOnlyPaths()` 列表）
    - _Ref: Requirement 9, AC 3_
  - [ ] 11.4 更新 `docs/state/install-behavior.md`
    - Deploy Scope 部分仅保留 project scope 路径解析
    - 移除 `global-setup` 引用和 `--scope` 参数
    - _Ref: Requirement 9, AC 5_
  - [ ] 11.5 更新 `docs/manual/usage.md`
    - 移除 `--scope` 用法示例
    - 移除 `global-setup` section，三阶段首次安装改为两阶段（`apm bootstrap` + `/apm init`）
    - _Ref: Requirement 9, AC 1-2_
  - [ ] 11.6 更新 init-workflow 参考文件
    - `.cursor/skills/apm/references/init-workflow.md` 和 `.kiro/skills/apm/references/init-workflow.md`
    - Bootstrap Phase 移除 Global Setup List 禁止条款；首次安装描述改为两阶段
    - _Ref: Requirement 9, AC 5_
  - [ ] 11.7 Migration Guide
    - 在 `docs/manual/` 下新建或更新迁移指南，说明 `--scope` 移除、`global-setup` → `bootstrap` 迁移步骤、`abilities.yaml` 的 `scopes` 字段和 `global-setup` section 需如何更新
    - _Ref: Requirement 9, AC 1-2; Requirement 4, AC 1-2_
  - [ ] 11.8 文档验证
    - 对 7 个受影响文件执行大小写不敏感搜索：`--scope`、`global-setup`、`user scope`、`ScopeGuard` 应返回零匹配（排除明确标记为 removed/deprecated 的行）
    - _Ref: Requirement 9, AC 6_
  - [ ] 11.9 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - 更新 `docs/state/deploy-scope.md`、`docs/state/cli-commands.md`、`docs/state/abilities-model.md`、`docs/state/install-behavior.md`
    - commit: `*(docs) by Kiro: documentation convergence — remove scope references, update bootstrap and deploy-scope docs`

- [ ] 12. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 流程
- commit 随 checkpoint 一起执行
- Task 1-3 为基础设施层变更，后续 task 依赖它们的接口稳定
- Task 6（Service 层）sub-task 较多但紧密耦合（共享 scope 参数移除模式），合并为一个 top-level task
- 删除废弃模块（Task 8）故意放在 service 层之后，确保无引用后再物理删除
- `abilities.yaml` 更新（Task 9）在代码变更完成后执行，避免 Registry 验证中途 fail-fast 阻碍开发

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
  { "id": 1, "tasks": ["1.4"] },
  { "id": 2, "tasks": ["2.1"] },
  { "id": 3, "tasks": ["2.2"] },
  { "id": 4, "tasks": ["2.3"] },
  { "id": 5, "tasks": ["2.4"] },
  { "id": 6, "tasks": ["3.1"] },
  { "id": 7, "tasks": ["3.2"] },
  { "id": 8, "tasks": ["4.1", "4.2", "4.3", "4.4", "4.5"] },
  { "id": 9, "tasks": ["4.6"] },
  { "id": 10, "tasks": ["5.1"] },
  { "id": 11, "tasks": ["5.2"] },
  { "id": 12, "tasks": ["6.1"] },
  { "id": 13, "tasks": ["6.2", "6.3", "6.4", "6.5"] },
  { "id": 14, "tasks": ["6.6", "6.7"] },
  { "id": 15, "tasks": ["6.8"] },
  { "id": 16, "tasks": ["7.1"] },
  { "id": 17, "tasks": ["7.2"] },
  { "id": 18, "tasks": ["7.3"] },
  { "id": 19, "tasks": ["7.4"] },
  { "id": 20, "tasks": ["8.1", "8.2", "8.3", "8.4"] },
  { "id": 21, "tasks": ["8.5"] },
  { "id": 22, "tasks": ["9.1"] },
  { "id": 23, "tasks": ["9.2"] },
  { "id": 24, "tasks": ["10.1", "10.2", "10.3", "10.4"] },
  { "id": 25, "tasks": ["10.5"] },
  { "id": 26, "tasks": ["10.6", "10.7"] },
  { "id": 27, "tasks": ["10.8"] },
  { "id": 28, "tasks": ["11.1", "11.2", "11.3", "11.4", "11.5", "11.6", "11.7"] },
  { "id": 29, "tasks": ["11.8"] },
  { "id": 30, "tasks": ["11.9"] },
  { "id": 31, "tasks": ["12"] }
]}
```
