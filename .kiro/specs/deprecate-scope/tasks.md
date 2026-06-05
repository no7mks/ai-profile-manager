# Implementation Plan: deprecate-scope

## Overview

本计划将 apm 从 dual-scope 模型简化为 single-scope (project-only) 模型。执行策略：

1. **底层先行**：先清理数据模型与基础设施（DeployScope 枚举、InvalidScopeException、DeployRootResolver），再改上层命令
2. **Test First**：每个功能 sub-task 内部先写测试（RED），再写实现（GREEN）
3. **删除后置**：模块精简（删 ScopeGuard、UserHomeResolver 等）安排在所有依赖方已解耦之后
4. **E2E 验证**：覆盖关键用户场景（--scope reject、global-setup reject、bootstrap 完整流程）
5. **文档收敛**：与 design Impact Analysis 一致，更新 state、manual 文档

关键设计决策引用：
- CR-1: 统一 deprecated 工厂方法
- CR-2: IO 层立即验证（abilities.yaml 解析时）
- CR-3: 内容 hash 比对判定幂等
- CR-4: baseline 源文件 diff 判定状态

## Tasks

- [ ] 1. InvalidScopeException 精简与 DeployScope 枚举瘦身
  - [ ] 1.1 精简 DeployScope 枚举
    - 删除 `DeployScope::User` case，仅保留 `DeployScope::Project`
    - 更新枚举文件中的注释
    - _Ref: Requirement 1, AC 6（所有操作默认 project scope）_
  - [ ] 1.2 重构 InvalidScopeException
    - 删除 `unknownScope()`、`projectOnlyInUserScope()` 工厂方法
    - 新增 `deprecated(string $feature, string $replacement): self` 统一工厂方法
    - 消息格式：`"[REMOVED] {$feature} — {$replacement}"`
    - _Ref: Requirement 1, AC 1-5, 7; Requirement 2, AC 1_
  - [ ] 1.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `refactor(scope): slim DeployScope enum and InvalidScopeException`

- [ ] 2. DeployRootResolver 简化
  - [ ] 2.1 移除 DeployRootResolver 对 UserHomeResolver 的依赖
    - 构造函数移除 `UserHomeResolver` 参数
    - `resolve()` 签名移除 `DeployScope` 参数，始终返回 project root (`getcwd()`)
    - `absoluteTargetPath()` 签名移除 `DeployScope` 参数
    - 删除 `parseScopeOption()` 方法
    - _Ref: Requirement 1, AC 6_
  - [ ] 2.2 更新 DeployRootResolver 的所有调用方
    - 全局搜索 `resolve(` 和 `absoluteTargetPath(` 调用，移除 scope 参数传入
    - 包括 Installer、InstallationProbe、CheckService 等
    - _Ref: Requirement 1, AC 6_
  - [ ] 2.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `refactor(scope): simplify DeployRootResolver to project-only`

- [ ] 3. HandlesDeployScopeOption trait 重构
  - [ ] 3.1 重写 HandlesDeployScopeOption trait
    - 保留 `configureDeployScopeOption()` 但改为注册隐藏 `--scope` option（VALUE_OPTIONAL，help 文案 `[REMOVED]`）
    - 删除 `resolveDeployScopeOption()`、`guardInstallBatch()`、`guardPresetInstall()`
    - 新增 `rejectIfScopeProvided(InputInterface, SymfonyStyle): bool` 方法
    - 逻辑：检测 `$input->hasParameterOption(['--scope'])`；若传入 → 调用 `InvalidScopeException::deprecated()` 输出 error 并返回 false；未传入 → 返回 true
    - _Ref: Requirement 1, AC 1-5, 7_
  - [ ] 3.2 更新所有使用 trait 的命令
    - 在每个使用 `HandlesDeployScopeOption` 的命令的 `execute()` 开头调用 `rejectIfScopeProvided()`
    - 命令列表：InstallCommand、ShowCommand、SkillInstallCommand、SkillUninstallCommand、RuleInstallCommand、RuleUninstallCommand、AgentInstallCommand、AgentUninstallCommand、PresetUninstallCommand
    - 若返回 false → return Command::FAILURE
    - _Ref: Requirement 1, AC 1-5, 7_
  - [ ] 3.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `feat(scope): reject --scope option with deprecation error`

- [ ] 4. abilities.yaml 格式迁移
  - [ ] 4.1 迁移 abilities.yaml 格式
    - 删除所有 entry（rules、agents、skills）中的 `scopes` 字段
    - 将顶层 `global-setup` section 改名为 `bootstrap`（`global-setup.includes` → `bootstrap.includes`）
    - 确认现有测试中的 yaml fixture 同步更新（搜索 test/ 目录中引用 scopes 或 global-setup 的 fixture）
    - _Ref: Requirement 4, AC 1-2; Requirement 5, AC 1_
  - [ ] 4.2 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `config(registry): migrate abilities.yaml to bootstrap format`

- [ ] 5. AbilityEntry 与 AbilityRegistry 变更
  - [ ] 5.1 移除 AbilityEntry 的 $scopes 属性
    - 删除构造函数中的 `$scopes` 参数及属性
    - 更新所有 AbilityEntry 实例化调用
    - _Ref: Requirement 4, AC 1_
  - [ ] 5.2 AbilityRegistry parse() 增加遗留字段检测
    - 移除原 `resolveScopes()` 逻辑
    - 新增检测：若 entry 含 `scopes` key → 加入 validationErrors（指明 entry 名称与字段不再支持），fail-fast
    - 新增检测：若顶层含 `global-setup` key → 加入 validationErrors（指明需改名为 `bootstrap`），fail-fast
    - _Ref: Requirement 4, AC 1-4_
  - [ ] 5.3 AbilityRegistry 新增 bootstrapIncludes() 方法
    - 原 `globalSetupIncludes()` 更名为 `bootstrapIncludes()`
    - 读取 `bootstrap.includes` section
    - 解析 `type:path` 格式（首个冒号分隔 type 和 path）
    - 返回 `list<array{type: string, path: string}>`
    - 处理缺失/空 section（返回空列表）、非字符串条目（跳过）、无冒号条目（跳过）
    - _Ref: Requirement 5, AC 1, 3, 5_
  - [ ] 5.4 删除 AbilityRegistry 的 projectOnlyPaths() 方法
    - 移除方法及其所有调用方
    - _Ref: Requirement 4, AC 4（不再有 scope 相关逻辑）_
  - [ ] 5.5 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `feat(registry): add legacy field detection and bootstrapIncludes()`

- [ ] 6. Installer 与 InstallationProbe 简化
  - [ ] 6.1 简化 InstallationProbe
    - 移除 `$scope` 参数（`isPresent()` 方法），硬编码使用 project root
    - 更新所有调用方
    - _Ref: Requirement 1, AC 6_
  - [ ] 6.2 简化 Installer
    - `installTyped()`: 移除 `$scope` 参数
    - `uninstallTyped()`: 移除 `$scope` 参数
    - 内部 helper 方法移除 scope 参数
    - 移除输出中的 `Scope: project|user` 行
    - _Ref: Requirement 1, AC 6_
  - [ ] 6.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `refactor(install): remove scope parameter from Installer and InstallationProbe`

- [ ] 7. CheckService 与 AbilityUpdateService 简化
  - [ ] 7.1 简化 CheckService
    - 移除 `checkTypedForScope()` 方法（若存在）
    - `checkTyped()` 直接使用 project root，不接受 scope 参数
    - _Ref: Requirement 1, AC 6_
  - [ ] 7.2 简化 AbilityUpdateService
    - `reportChanges()` 仅遍历 project scope abilities
    - 移除 `DeployScope::User` 迭代逻辑
    - 输出格式中移除 `({scope})` 部分
    - _Ref: Requirement 7, AC 1-3_
  - [ ] 7.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `refactor(service): simplify CheckService and AbilityUpdateService to project-only`

- [ ] 8. ShowStatusPresenter 简化
  - [ ] 8.1 重构 ShowStatusPresenter
    - `rows()` / `lines()`: 移除 `$scopeFilter` 参数
    - 删除 `mergeStatuses()` 方法
    - 移除 scope label 生成逻辑（不再输出 `(user)`、`(project)` 等标签）
    - 状态判定使用 AbilityDiffService 做 baseline diff
    - _Ref: Requirement 6, AC 1-3_
  - [ ] 8.2 更新 ShowCommand 调用 ShowStatusPresenter 的代码
    - 移除传入 scope filter 的逻辑
    - 确保输出格式为 `{type}:{name}  {status}  {targets}`
    - _Ref: Requirement 6, AC 1_
  - [ ] 8.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `refactor(show): remove scope labels from ShowStatusPresenter`

- [ ] 9. GlobalSetupCommand 退化与 CleanupCommand 文案
  - [ ] 9.1 退化 GlobalSetupCommand
    - 构造函数移除 `GlobalSetupService` 依赖
    - `execute()` 改为输出 `InvalidScopeException::deprecated('global-setup command', 'Use "apm bootstrap" in your project directory instead.')` 的错误消息并 return FAILURE
    - `configure()` 保留命令名和选项定义（确保 `--force`、`--target` 不报 unknown option）
    - _Ref: Requirement 2, AC 1-3_
  - [ ] 9.2 清理 CleanupCommand 文案
    - 成功消息替换为不含 "user-scope"、"user scope"、"global-setup"、"global setup" 的文案
    - 添加引导文案：`To reinstall project abilities, run /apm init in agent chat.`
    - _Ref: Requirement 8, AC 1-3_
  - [ ] 9.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `feat(commands): deprecate global-setup and clean cleanup messaging`

- [ ] 10. BootstrapCommand 扩展
  - [ ] 10.1 BootstrapCommand 新增依赖注入
    - 构造函数新增 `Installer` 和 `AbilityRegistry` 参数（可选 nullable）
    - _Ref: Requirement 3, AC 1-2_
  - [ ] 10.2 实现 ability install 阶段
    - scaffold 完成后读取 `$this->registry->bootstrapIncludes()`
    - 若为空 → 成功返回（不尝试安装）
    - 遍历 includes：对每项使用 AbilityDiffService 做内容 hash 比对
    - hash 一致且无 `--force` → skip（输出 skip 行）
    - hash 一致且有 `--force` → 覆盖安装
    - hash 不一致（含目标不存在） → 执行 install
    - _Ref: Requirement 3, AC 2-4; Requirement 5, AC 2_
  - [ ] 10.3 实现失败处理逻辑
    - 单项安装失败 → 输出 `[fail]`，继续剩余项
    - 所有项处理完毕后，若有失败 → exit 1
    - scaffold 失败 → exit 1，不进入 ability install
    - _Ref: Requirement 3, AC 5-6, 8_
  - [ ] 10.4 处理 bootstrap.includes 条目解析异常
    - 条目在 registry 中找不到 → 报错并 skip
    - _Ref: Requirement 5, AC 4_
  - [ ] 10.5 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `feat(bootstrap): add ability install phase with idempotency`

- [ ] 11. 删除废弃模块
  - [ ] 11.1 删除 ScopeGuard
    - 删除 `src/Service/ScopeGuard.php` 文件
    - 移除所有 import 和引用
    - _Ref: Requirement 1, AC 6（不再需要 project-only 校验）_
  - [ ] 11.2 删除 UserHomeResolver
    - 删除 `src/Service/UserHomeResolver.php` 文件
    - 移除所有 import 和引用
    - _Ref: Requirement 1, AC 6（无 user scope → 无需解析 user root）_
  - [ ] 11.3 删除 GlobalSetupService 接口与实现
    - 删除 `src/Service/GlobalSetupService.php`（接口）
    - 删除 `src/Service/DefaultGlobalSetupService.php`（实现）
    - 移除所有 import 和引用
    - _Ref: Requirement 2, AC 2_
  - [ ] 11.4 删除 GlobalInstallDetector
    - 删除 `src/Service/GlobalInstallDetector.php` 文件
    - 移除所有 import 和引用
    - _Ref: Requirement 1, AC 6（无 user scope → 无需检测全局安装）_
  - [ ] 11.5 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - commit: `chore(cleanup): remove ScopeGuard, UserHomeResolver, GlobalSetupService, GlobalInstallDetector`

- [ ] 12. E2E 测试
  - [ ] 12.1 E2E: --scope 拒绝场景
    - `bin/apm install gitflow --scope user` → exit 1 + deprecated 消息
    - `bin/apm show --scope project` → exit 1 + deprecated 消息
    - `bin/apm skill:install apm --scope` → exit 1 + deprecated 消息
    - _脚本_
    - _Ref: Requirement 1, AC 1-5, 7_
  - [ ] 12.2 E2E: global-setup 拒绝场景
    - `bin/apm global-setup` → exit 1 + deprecated 消息
    - `bin/apm global-setup --force --target cursor` → exit 1 + deprecated 消息
    - _脚本_
    - _Ref: Requirement 2, AC 1-3_
  - [ ] 12.3 E2E: bootstrap 完整流程
    - `bin/apm bootstrap -t cursor -t kiro` → scaffold + includes 安装成功
    - 重复执行 → 幂等 skip
    - `--force` → 覆盖安装
    - _脚本_
    - _Ref: Requirement 3, AC 1-7_
  - [ ] 12.4 E2E: abilities.yaml 遗留字段
    - 准备含 `scopes` 字段的 yaml → 任何命令 exit 非零 + 错误消息
    - 准备含 `global-setup` key 的 yaml → 任何命令 exit 非零 + 错误消息
    - _脚本_
    - _Ref: Requirement 4, AC 1-4_
  - [ ] 12.5 Checkpoint
    - 运行验证：`./vendor/bin/phpunit --testsuite e2e`
    - commit: `test(e2e): add scope deprecation E2E tests`

- [ ] 13. 文档收敛
  - [ ] 13.1 更新 docs/state/deploy-scope.md
    - 重写为 project-scope-only 路径解析
    - 移除 User Root、User Scope、ScopeGuard section、`projectOnlyInUserScope` 描述
    - _Ref: Requirement 9, AC 4_
  - [ ] 13.2 更新 docs/state/cli-commands.md
    - 移除 `--scope` 参数签名和错误条件表
    - `global-setup` section 标记为 removed，说明 `bootstrap` 为替代
    - 更新 `bootstrap` 命令描述（含 ability install 阶段）
    - _Ref: Requirement 9, AC 1-2_
  - [ ] 13.3 更新 docs/state/abilities-model.md
    - 移除 `scopes` 字段行
    - 移除 `global-setup` section
    - 移除 Project-Only 概念（ScopeGuard、`projectOnlyPaths()`）
    - _Ref: Requirement 9, AC 3_
  - [ ] 13.4 更新 docs/state/install-behavior.md
    - Deploy Scope subsection 仅保留 project scope 路径解析
    - 移除 `global-setup` 和 `--scope` 相关引用
    - _Ref: Requirement 9, AC 5_
  - [ ] 13.5 更新 docs/manual/usage.md
    - 移除 `--scope` 使用示例
    - 移除 `global-setup` section
    - 更新首次安装流程为两阶段：`apm bootstrap` + `/apm init`
    - _Ref: Requirement 9, AC 1-2_
  - [ ] 13.6 更新 init-workflow.md（两个平台）
    - 更新 `.cursor/skills/apm/references/init-workflow.md`
    - 更新 `.kiro/skills/apm/references/init-workflow.md`
    - Bootstrap Phase：移除关于 Global Setup List 的禁止说明，更新为两阶段首次安装
    - _Ref: Requirement 9, AC 5_
  - [ ] 13.7 文档验证
    - 对 7 个受影响文件执行 case-insensitive 搜索：`--scope`、`global-setup`、`user scope`、`ScopeGuard`
    - 应返回零匹配（排除明确标记为 removed/deprecated 的行）
    - _Ref: Requirement 9, AC 6_
  - [ ] 13.8 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - 更新 `docs/state/deploy-scope.md`、`docs/state/cli-commands.md`、`docs/state/abilities-model.md`、`docs/state/install-behavior.md`
    - commit: `docs: converge documentation for scope deprecation`

- [ ] 14. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 流程
- commit 随 checkpoint 一起执行
- Test First 编排：每个功能 sub-task 内部先写测试确认失败（RED），再写实现确认通过（GREEN）
- abilities.yaml 迁移（Task 4）安排在 AbilityEntry/AbilityRegistry 代码变更（Task 5）之前，确保 parse 运行时不会因遗留字段 fail-fast 而阻断开发
- Task 11（删除废弃模块）安排在所有解耦工作完成之后，避免编译错误
- E2E 测试脚本存放于 `.kiro/specs/deprecate-scope/tests/`

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2"] },
  { "id": 1, "tasks": ["1.3"] },
  { "id": 2, "tasks": ["2.1"] },
  { "id": 3, "tasks": ["2.2"] },
  { "id": 4, "tasks": ["2.3"] },
  { "id": 5, "tasks": ["3.1"] },
  { "id": 6, "tasks": ["3.2"] },
  { "id": 7, "tasks": ["3.3"] },
  { "id": 8, "tasks": ["4.1"] },
  { "id": 9, "tasks": ["4.2"] },
  { "id": 10, "tasks": ["5.1", "5.4"] },
  { "id": 11, "tasks": ["5.2", "5.3"] },
  { "id": 12, "tasks": ["5.5"] },
  { "id": 13, "tasks": ["6.1", "6.2"] },
  { "id": 14, "tasks": ["6.3"] },
  { "id": 15, "tasks": ["7.1", "7.2"] },
  { "id": 16, "tasks": ["7.3"] },
  { "id": 17, "tasks": ["8.1"] },
  { "id": 18, "tasks": ["8.2"] },
  { "id": 19, "tasks": ["8.3"] },
  { "id": 20, "tasks": ["9.1", "9.2"] },
  { "id": 21, "tasks": ["9.3"] },
  { "id": 22, "tasks": ["10.1"] },
  { "id": 23, "tasks": ["10.2", "10.3", "10.4"] },
  { "id": 24, "tasks": ["10.5"] },
  { "id": 25, "tasks": ["11.1", "11.2", "11.3", "11.4"] },
  { "id": 26, "tasks": ["11.5"] },
  { "id": 27, "tasks": ["12.1", "12.2", "12.3", "12.4"] },
  { "id": 28, "tasks": ["12.5"] },
  { "id": 29, "tasks": ["13.1", "13.2", "13.3", "13.4", "13.5", "13.6"] },
  { "id": 30, "tasks": ["13.7"] },
  { "id": 31, "tasks": ["13.8"] },
  { "id": 32, "tasks": ["14"] }
]}
```
