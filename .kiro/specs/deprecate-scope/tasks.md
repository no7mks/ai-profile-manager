# Implementation Plan: deprecate-scope

## Overview

按依赖拓扑执行（AD-1 决策 C）：R4（abilities.yaml 格式变更）→ R3（bootstrap 扩展）→ R1/R2（--scope 废弃）→ R5-R7（show/update/cleanup 简化）→ R8（代码清理）→ R9（文档同步）。每个功能 sub-task 内 TDD（AD-2 决策 A）：RED → GREEN。代码清理为单一 task（AD-3 决策 A），abilities.yaml 迁移与 R4 验证逻辑同 task（AD-4 决策 A）。Checkpoint 验证：`./vendor/bin/phpstan analyse` + `./vendor/bin/phpunit`。

## Tasks

- [-] 1. abilities.yaml 格式变更与验证
  - [x] 1.1 `InvalidScopeException` 新增 factory methods
    - 新增 `legacyScopesField(string $path)` 和 `legacyGlobalSetupKey()` factory methods
    - 移除 `unknownScope()` 和 `projectOnlyInUserScope()`，仅保留废弃消息工厂
    - RED → GREEN：`./vendor/bin/phpunit --filter InvalidScopeExceptionTest`
    - _Ref: Requirement 4, AC 1-2; Requirement 8, AC 9_
  - [x] 1.2 `AbilityEntry` 移除 `$scopes` 属性
    - 移除构造函数中的 `$scopes` 参数和属性声明
    - 更新所有测试中 AbilityEntry 的构造调用
    - RED → GREEN：`./vendor/bin/phpunit --filter AbilityEntryTest`
    - _Ref: Requirement 4, AC 1; Requirement 8, AC 10_
  - [x] 1.3 `AbilityRegistry::parse()` fail-fast 验证
    - 检测 entry 含 `scopes` 字段时抛 `InvalidScopeException::legacyScopesField()`
    - 检测 `global-setup` 顶层 key 时抛 `InvalidScopeException::legacyGlobalSetupKey()`
    - 首个违规即终止（fail-fast）
    - RED → GREEN：`./vendor/bin/phpunit --filter AbilityRegistryTest`
    - _Ref: Requirement 4, AC 1-2, 5_
  - [x] 1.4 `AbilityRegistry::bootstrapIncludes()` 实现
    - 从 `bootstrap.includes` 读取 `type:path` 条目列表
    - `bootstrap` section 不存在或 `includes` 为空时返回空数组
    - RED → GREEN：`./vendor/bin/phpunit --filter AbilityRegistryTest`
    - _Ref: Requirement 4, AC 3-4_
  - [x] 1.5 `AbilityRegistry::validateBootstrapIncludes()` 实现
    - 校验 `bootstrap.includes` 中所有引用在 registry 中存在
    - 任一引用不存在则抛 `AbilityRegistryException::invalidBootstrapReference()`
    - RED → GREEN：`./vendor/bin/phpunit --filter AbilityRegistryTest`
    - _Ref: Requirement 3, AC 2（前置校验）_
  - [x] 1.6 迁移项目 `abilities.yaml` 文件
    - 将 `global-setup` section 改名为 `bootstrap`
    - 移除所有 entry 的 `scopes` 字段
    - _Ref: Requirement 4, AC 1-3_
  - [x] 1.7 Property 2 + Property 5 属性测试
    - **Property 2: Bootstrap includes 解析正确性** — 随机 `type:path` 组合验证 round-trip
    - **Property 5: 遗留字段 fail-fast 终止** — 随机违规位置验证仅首个报错
    - 标签：`Feature: deprecate-scope, Property 2/5`
    - _Ref: Requirement 4, AC 1-5_
  - [-] 1.8 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - 更新 `docs/state/abilities-model.md`：移除 `scopes` 字段文档
    - commit: `+(registry) deprecate-scope: abilities.yaml format migration`

- [ ] 2. Bootstrap 命令扩展
  - [ ] 2.1 `Installer` 新增 `$skipExisting` 参数支持
    - `installTyped()` 增加 `bool $skipExisting = false` 参数
    - 已安装且 `$skipExisting = true` 时输出 `[skip]` 标记
    - RED → GREEN：`./vendor/bin/phpunit --filter InstallerTest`
    - _Ref: Requirement 3, AC 3_
  - [ ] 2.2 `BootstrapCommand` 扩展 ability 安装阶段
    - scaffold 完成后读取 `bootstrapIncludes()`，调用 `validateBootstrapIncludes()`
    - 逐项安装到 project scope；`--force` 覆盖已安装项
    - 失败项输出 `[fail]` 并继续剩余；存在失败则 exit 1
    - 空 includes 仅 scaffold，exit 0
    - RED → GREEN：`./vendor/bin/phpunit --filter BootstrapCommandTest`
    - _Ref: Requirement 3, AC 1-9_
  - [ ] 2.3 Property 3 + Property 4 属性测试
    - **Property 3: Bootstrap 幂等性** — 预装/未装 × force/no-force 组合验证
    - **Property 4: Bootstrap 部分失败容错** — 随机失败位置验证剩余项仍尝试
    - 标签：`Feature: deprecate-scope, Property 3/4`
    - _Ref: Requirement 3, AC 3-5, 7_
  - [ ] 2.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(bootstrap) deprecate-scope: ability install phase`

- [ ] 3. 废弃 --scope 参数
  - [ ] 3.1 `HandlesDeployScopeOption` trait 重构
    - 保留 `configureDeployScopeOption()` 注册 --scope 选项
    - 新增 `rejectIfScopeOptionPresent(InputInterface $input)` 检测并抛废弃异常
    - 移除 `resolveDeployScopeOption()`、`guardInstallBatch()`、`guardPresetInstall()`
    - RED → GREEN：`./vendor/bin/phpunit --filter HandlesDeployScopeOptionTest`
    - _Ref: Requirement 1, AC 1-6; Requirement 8, AC 7_
  - [ ] 3.2 `InvalidScopeException::scopeOptionDeprecated()` factory
    - 消息含 `--scope` 已移除 + 所有操作仅针对 project scope
    - RED → GREEN：`./vendor/bin/phpunit --filter InvalidScopeExceptionTest`
    - _Ref: Requirement 1, AC 5_
  - [ ] 3.3 各命令 `handle()` 顶部集成 `rejectIfScopeOptionPresent()`
    - 涉及命令：InstallCommand、ShowCommand、Typed Install 命令、Typed Uninstall 命令
    - 传入 `--scope` 任何值立即报错，不执行业务逻辑
    - RED → GREEN：`./vendor/bin/phpunit --filter "InstallCommandTest|ShowCommandTest|SkillInstallCommandTest|RuleInstallCommandTest|AgentInstallCommandTest|SkillUninstallCommandTest|RuleUninstallCommandTest|AgentUninstallCommandTest|PresetUninstallCommandTest"`
    - _Ref: Requirement 1, AC 1-4, 6_
  - [ ] 3.4 Property 1 属性测试
    - **Property 1: --scope 参数全面拒绝** — 随机字符串作为 scope 值，验证所有命令 reject
    - 标签：`Feature: deprecate-scope, Property 1`
    - _Ref: Requirement 1, AC 1-6_
  - [ ] 3.5 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(cli) deprecate-scope: reject --scope option`

- [ ] 4. 废弃 global-setup 命令
  - [ ] 4.1 `GlobalSetupCommand` 改为废弃壳
    - 移除 `DefaultGlobalSetupService` 注入
    - `execute()` 输出废弃消息（含命令名 `global-setup` + 替代命令 `apm bootstrap`）到 stderr
    - 以非零退出码退出；不写文件、不创目录、不发网络请求
    - RED → GREEN：`./vendor/bin/phpunit --filter GlobalSetupCommandTest`
    - _Ref: Requirement 2, AC 1-3_
  - [ ] 4.2 `InvalidScopeException::globalSetupDeprecated()` factory
    - 消息含已移除命令名称和替代命令名称
    - RED → GREEN：`./vendor/bin/phpunit --filter InvalidScopeExceptionTest`
    - _Ref: Requirement 2, AC 2_
  - [ ] 4.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(cli) deprecate-scope: global-setup deprecated shell`

- [ ] 5. Show 命令简化
  - [ ] 5.1 `ShowStatusPresenter` 移除 scope 合并逻辑
    - `lines()` 仅评估 project scope，输出格式 `{type}:{name}  {status}   {targets}`
    - 移除 `dualScopeWarning`、`scopeLabel`、多 scope 状态聚合
    - 移除 `?DeployScope $scopeFilter` 参数
    - RED → GREEN：`./vendor/bin/phpunit --filter ShowStatusPresenterTest`
    - _Ref: Requirement 5, AC 1-3; Requirement 8, AC 8_
  - [ ] 5.2 `ShowCommand` 集成简化后的 presenter
    - `--type` 过滤正常传递
    - 空 registry 输出空列表且 exit 0
    - RED → GREEN：`./vendor/bin/phpunit --filter ShowCommandTest`
    - _Ref: Requirement 5, AC 4-5_
  - [ ] 5.3 Property 6 + Property 7 属性测试
    - **Property 6: Show 输出无 scope 标记** — 随机 registry 内容验证无 scope 标签
    - **Property 7: Show type 过滤器正确性** — 随机 type 过滤验证输出一致性
    - 标签：`Feature: deprecate-scope, Property 6/7`
    - _Ref: Requirement 5, AC 1-5_
  - [ ] 5.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(show) deprecate-scope: project-only presenter`

- [ ] 6. Update 命令简化
  - [ ] 6.1 `AbilityUpdateService` 仅遍历 project scope
    - `reportChanges()` 仅检查 project scope 已安装 ability
    - 移除 `DeployScope::User` 遍历循环
    - 输出格式 `changed: {type}:{name} {target}`（无 scope 标签）
    - RED → GREEN：`./vendor/bin/phpunit --filter AbilityUpdateServiceTest`
    - _Ref: Requirement 6, AC 1-3_
  - [ ] 6.2 `UpdateCommand` 集成简化后的 service
    - `--force` 时使用 baseline 覆盖 project scope 文件
    - RED → GREEN：`./vendor/bin/phpunit --filter UpdateCommandTest`
    - _Ref: Requirement 6, AC 4_
  - [ ] 6.3 Property 8 + Property 9 属性测试
    - **Property 8: Update 仅遍历 project scope** — 验证不访问 user home 路径
    - **Property 9: Update 输出格式无 scope 标签** — 随机 changed 项验证格式
    - 标签：`Feature: deprecate-scope, Property 8/9`
    - _Ref: Requirement 6, AC 1-4_
  - [ ] 6.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(update) deprecate-scope: project-only traversal`

- [ ] 7. Cleanup 命令文案清理
  - [ ] 7.1 `CleanupCommand` 移除遗留用语
    - 移除输出中 "user-scope global-setup is unchanged" 文案
    - 成功时输出 `/apm init` 引导提示
    - 失败时不输出引导提示
    - RED → GREEN：`./vendor/bin/phpunit --filter CleanupCommandTest`
    - _Ref: Requirement 7, AC 1-3_
  - [ ] 7.2 Property 10 属性测试
    - **Property 10: Cleanup 输出无遗留用语** — 随机执行结果验证无禁用字符串
    - 标签：`Feature: deprecate-scope, Property 10`
    - _Ref: Requirement 7, AC 1_
  - [ ] 7.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(cleanup) deprecate-scope: remove scope wording`

- [ ] 8. 代码清理（单一 task，AD-3）
  - [ ] 8.1 删除废弃类文件
    - 删除 `UserHomeResolver.php` 及其测试
    - 删除 `ScopeGuard.php` 及其测试
    - 删除 `DefaultGlobalSetupService.php` / `GlobalSetupService.php` 及其测试
    - 移除源码中所有对上述类的 `use` 声明和实例化
    - _Ref: Requirement 8, AC 1-3_
  - [ ] 8.2 `DeployRootResolver` 简化
    - 构造函数注入 `?string $rootPath = null`（默认 `getcwd()`）
    - `resolve()` 仅返回 project root
    - 移除 `parseScopeOption()`、`resolve(DeployScope)` 方法
    - 不接受 `DeployScope` 参数
    - RED → GREEN：`./vendor/bin/phpunit --filter DeployRootResolverTest`
    - _Ref: Requirement 8, AC 4_
  - [ ] 8.3 `DeployScope` 枚举简化
    - 移除 `User` case，仅保留 `Project` 单值
    - RED → GREEN：`./vendor/bin/phpunit --filter DeployScopeTest`
    - _Ref: Requirement 8, AC 11_
  - [ ] 8.4 `InstallationProbe` / `Installer` 移除 `$scope` 参数
    - 所有方法硬编码使用 project scope
    - 更新调用方
    - RED → GREEN：`./vendor/bin/phpunit --filter "InstallationProbeTest|InstallerTest"`
    - _Ref: Requirement 8, AC 5_
  - [ ] 8.5 `CheckService` 移除 `checkTypedForScope()` 方法
    - `checkTyped()` 直接使用 project root
    - 更新调用方
    - RED → GREEN：`./vendor/bin/phpunit --filter CheckServiceTest`
    - _Ref: Requirement 8, AC 6_
  - [ ] 8.6 集成验证：源码无废弃引用
    - 验证源码中无 `UserHomeResolver`、`ScopeGuard`、`DefaultGlobalSetupService`、`GlobalSetupService` 引用
    - 验证 `AbilityEntry` 无 `$scopes` 属性
    - 验证 `DeployScope` 仅含 `Project`
    - _Ref: Requirement 8, AC 1-11_
  - [ ] 8.7 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - 更新 `docs/state/deploy-scope.md`：移除 user scope 全部内容
    - commit: `+(cleanup) deprecate-scope: remove user scope internals`

- [ ] 9. E2E 测试
  - [ ] 9.1 _脚本_ `tests/test-task-9a.sh`：--scope 参数拒绝 + global-setup 废弃
    - 验证 install/show/typed-install/typed-uninstall 传入 --scope 时 exit 1
    - 验证 global-setup 命令输出废弃消息并 exit 1
    - _Ref: Requirement 1, 2_
  - [ ] 9.2 _脚本_ `tests/test-task-9b.sh`：bootstrap 完整流程
    - 验证 scaffold + ability 安装、skip 逻辑、force 覆盖、部分失败
    - _Ref: Requirement 3_
  - [ ] 9.3 _脚本_ `tests/test-task-9c.sh`：show/update/cleanup 简化行为
    - 验证 show 无 scope 标签、update 仅 project、cleanup 无遗留用语
    - _Ref: Requirement 5, 6, 7_
  - [ ] 9.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`；`./vendor/bin/phpunit --testsuite e2e`
    - commit: `+(e2e) deprecate-scope: scope deprecation flows`

- [ ] 10. 文档收敛
  - [ ] 10.1 SSOT 文档更新
    - 重写 `docs/state/deploy-scope.md`：仅描述 project scope
    - 更新 `docs/state/cli-commands.md`：移除 `--scope` 参数，标注 global-setup 废弃
    - 更新 `docs/state/abilities-model.md`：移除 scopes 字段文档
    - 更新 `docs/state/install-behavior.md`：移除 user scope 路径解析内容
    - _Ref: Requirement 9, AC 1-4_
  - [ ] 10.2 Manual 文档更新
    - 更新 `docs/manual/usage.md`：三阶段改两阶段首装，移除 --scope 示例
    - _Ref: Requirement 9, AC 5_
  - [ ] 10.3 APM skill + init-workflow 更新
    - 更新 `.cursor/skills/apm/references/init-workflow.md`
    - 更新 `.kiro/skills/apm/references/init-workflow.md`
    - 移除 global-setup 引用，调整为两阶段首装
    - _Ref: Requirement 9, AC 6_
  - [ ] 10.4 零残留验证
    - grep 全部文档确认无 `user` scope、`--scope` 参数、`global-setup`（非 deprecated 标注）残留引用
    - _Ref: Requirement 9, AC 7_
  - [ ] 10.5 知识图谱更新
    - `/graphify update`：增量更新本次变更涉及的文件
  - [ ] 10.6 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse`；`./vendor/bin/phpunit`
    - commit: `+(docs) deprecate-scope: documentation convergence`

- [ ] 11. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 流程；按 TDG wave 执行；测试输出写日志（`PROJECT.md` 约束）。
- Checkpoint 验证：`./vendor/bin/phpstan analyse` + `./vendor/bin/phpunit`；task 9.4 另跑 `./vendor/bin/phpunit --testsuite e2e`。
- 每个 Checkpoint sub-task 完成验证后在同一 sub-task 内执行 commit。
- TDD 模式：每个功能 sub-task 内部 RED → GREEN，测试不拆分为独立 task。
- 代码清理（task 8）为单一 top-level task（AD-3），变更高度耦合，拆开增加中间态不一致风险。
- E2E 脚本存放：`.kiro/specs/deprecate-scope/tests/test-task-9*.sh`。
- PBT 使用 eris/eris 库，最少 100 次迭代，标签格式 `Feature: deprecate-scope, Property {N}: {title}`。

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1"] },
  { "id": 1, "tasks": ["1.2"] },
  { "id": 2, "tasks": ["1.3"] },
  { "id": 3, "tasks": ["1.4"] },
  { "id": 4, "tasks": ["1.5"] },
  { "id": 5, "tasks": ["1.6"] },
  { "id": 6, "tasks": ["1.7"] },
  { "id": 7, "tasks": ["1.8"] },
  { "id": 8, "tasks": ["2.1"] },
  { "id": 9, "tasks": ["2.2"] },
  { "id": 10, "tasks": ["2.3"] },
  { "id": 11, "tasks": ["2.4"] },
  { "id": 12, "tasks": ["3.1", "3.2"] },
  { "id": 13, "tasks": ["3.3"] },
  { "id": 14, "tasks": ["3.4"] },
  { "id": 15, "tasks": ["3.5"] },
  { "id": 16, "tasks": ["4.1", "4.2"] },
  { "id": 17, "tasks": ["4.3"] },
  { "id": 18, "tasks": ["5.1"] },
  { "id": 19, "tasks": ["5.2"] },
  { "id": 20, "tasks": ["5.3"] },
  { "id": 21, "tasks": ["5.4"] },
  { "id": 22, "tasks": ["6.1"] },
  { "id": 23, "tasks": ["6.2"] },
  { "id": 24, "tasks": ["6.3"] },
  { "id": 25, "tasks": ["6.4"] },
  { "id": 26, "tasks": ["7.1"] },
  { "id": 27, "tasks": ["7.2"] },
  { "id": 28, "tasks": ["7.3"] },
  { "id": 29, "tasks": ["8.1"] },
  { "id": 30, "tasks": ["8.2", "8.3"] },
  { "id": 31, "tasks": ["8.4"] },
  { "id": 32, "tasks": ["8.5"] },
  { "id": 33, "tasks": ["8.6"] },
  { "id": 34, "tasks": ["8.7"] },
  { "id": 35, "tasks": ["9.1", "9.2", "9.3"] },
  { "id": 36, "tasks": ["9.4"] },
  { "id": 37, "tasks": ["10.1", "10.2", "10.3"] },
  { "id": 38, "tasks": ["10.4"] },
  { "id": 39, "tasks": ["10.5"] },
  { "id": 40, "tasks": ["10.6"] },
  { "id": 41, "tasks": ["11"] }
]}
```
