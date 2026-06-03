# Implementation Plan: deploy-scope-and-cli

## Overview

D-CR1：Task 1（R1）单独 commit 后再做 Phase 2。依赖链：Resolver → Registry/Guard → Probe → Check/Show → Update → 新命令 → Install breaking。sub-task 内 RED→GREEN；Checkpoint 含 `./vendor/bin/phpunit` + `docs/state/`。R13 为 E2E 人工段；R14 在 task 14 关闭 issue。

## Tasks

- [ ] 1. Phase 1：Baseline Resolver XDG
  - [ ] 1.1 扩展 `ComposerBaselineResolverTest`（`~/.composer` → `~/.config/composer` 回退）
    - RED→GREEN：`./vendor/bin/phpunit --filter ComposerBaselineResolverTest`
    - _Ref: Requirement 1, AC 3–4_
  - [ ] 1.2 实现 `candidateComposerHomes()` + 重构 `installedJsonPath()` in `ComposerBaselineResolver.php`
    - _Ref: Requirement 1, AC 1–4_
  - [ ] 1.3 `CheckServiceTest`：XDG fixture 下非全员 `unknown`
    - _Ref: Requirement 1, AC 5_
  - [ ] 1.4 更新 `docs/state/install-behavior.md` baseline 顺序
    - _Ref: Requirement 1, AC 6_
  - [ ] 1.5 Checkpoint — `./vendor/bin/phpunit`；commit: `+(baseline) by Cursor: XDG composer home fallback`

- [ ] 2. DeployRootResolver
  - [ ] 2.1 `DeployScope` enum（`src/Config/DeployScope.php`）
    - _Ref: Requirement 3, AC 1_
  - [ ] 2.2 `DeployRootResolver` + `DeployRootResolverTest`（resolve/parseScopeOption/absoluteTargetPath；非法 scope 抛 `InvalidScopeException`）
    - _Ref: Requirement 3, AC 2–3, 5_
  - [ ] 2.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(scope) by Cursor: DeployRootResolver`

- [ ] 3. Registry：scopes、global-setup、删 default
  - [ ] 3.1 `AbilityEntry.scopes` 解析，默认 `[project]`
    - _Ref: Requirement 3, AC 7; Requirement 4, AC 5_
  - [ ] 3.2 `globalSetupIncludes()`、`projectOnlyPaths()`、`getEntry()` + 测试
    - _Ref: Requirement 4, AC 1, 4, 7_
  - [ ] 3.3 `abilities.yaml`：`global-setup.includes`、per-entry `scopes`、删 preset `default`
    - _Ref: Requirement 4, AC 1–3_
  - [ ] 3.4 Checkpoint — `./vendor/bin/phpunit`；commit: `+(registry) by Cursor: global-setup and scopes`

- [ ] 4. ScopeGuard
  - [ ] 4.1 `InvalidScopeException`
    - _Ref: Requirement 3, AC 4–6_
  - [ ] 4.2 `ScopeGuard::assertBatchAllowed()` + 测试（零部分写入）
    - _Ref: Requirement 3, AC 6; Requirement 8, AC 4_
  - [ ] 4.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(scope) by Cursor: ScopeGuard`

- [ ] 5. InstallationProbe + Installer
  - [ ] 5.1 `InstallationProbe::isPresent()` + category rule 测试
    - _Ref: Requirement 2, AC 1–3_
  - [ ] 5.2 `Installer` 委托 probe + `DeployRootResolver`；`uninstallProjectScope()`
    - _Ref: Requirement 2, AC 4–5; Requirement 7, AC 1_
  - [ ] 5.3 Checkpoint — `./vendor/bin/phpunit`；commit: `*(install) by Cursor: scope-aware InstallationProbe`

- [ ] 6. CheckService scope
  - [ ] 6.1 `checkTypedForScope()` + 测试
    - _Ref: Requirement 9, AC 4_
  - [ ] 6.2 user scope `HookChecker` 路径
    - _Ref: Requirement 3, AC 3; design P2 Check_
  - [ ] 6.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(check) by Cursor: scope-aware checks`

- [ ] 7. Show 重写
  - [ ] 7.1 `ShowStatusPresenter` + 映射/双 scope/targets 测试
    - _Ref: Requirement 9, AC 1–6; GK CR1_
  - [ ] 7.2 重写 `ShowCommand` + `ShowCommandTest`（`--scope`；fallback R2）
    - _Ref: Requirement 9; Requirement 2, AC 4_
  - [ ] 7.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(show) by Cursor: three-state presenter`

- [ ] 8. Update 重写 + 删 KnowledgeBaseUpdater
  - [ ] 8.1 `GlobalInstallDetector` + 测试
    - _Ref: Requirement 10, AC 1_
  - [ ] 8.2 `AbilityUpdateService`（双侧枚举；`--force` 各自覆盖）
    - _Ref: Requirement 10, AC 2–4_
  - [ ] 8.3 重写 `UpdateCommand`；删 `KnowledgeBaseUpdater`、测试、DI、`architecture.md` 引用
    - _Ref: Requirement 10; design D-CR3 A_
  - [ ] 8.4 Checkpoint — `./vendor/bin/phpunit`；commit: `+(update) by Cursor: global-only baseline update`

- [ ] 9. global-setup 命令
  - [ ] 9.1 `GlobalSetupCommandTest`（仅列表、user、幂等/force、skip、提示 init）
    - _Ref: Requirement 5, AC 1–6_
  - [ ] 9.2 `GlobalSetupCommand` + `ConsoleRegistration` DI
    - _Ref: Requirement 5, AC 1–6_
  - [ ] 9.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(cli) by Cursor: global-setup command`

- [ ] 10. bootstrap + 无参 install 废弃
  - [ ] 10.1 `BootstrapCommand`（仅 `ProjectInitializer`）；更新 `BootstrapLifecycleTest`
    - _Ref: Requirement 6, AC 1–3_
  - [ ] 10.2 `InstallCommand` 无参失败 + `default` 迁移文案
    - _Ref: Requirement 8, AC 1, 4; Requirement 4, AC 3_
  - [ ] 10.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(cli) by Cursor: bootstrap and reject bare install`

- [ ] 11. cleanup 命令
  - [ ] 11.1 `CleanupCommandTest`（registry 全枚举，仅 project 卸载）
    - _Ref: Requirement 7, AC 1–2; design D-CR4 A_
  - [ ] 11.2 `CleanupCommand` + 注册
    - _Ref: Requirement 7, AC 3_
  - [ ] 11.3 Checkpoint — `./vendor/bin/phpunit`；commit: `+(cli) by Cursor: cleanup command`

- [ ] 12. install 族 scope 与同义词
  - [ ] 12.1 install/uninstall 族 `--scope` + `ScopeGuard`（D-CR2 A）
    - _Ref: Requirement 3, AC 1–6_
  - [ ] 12.2 `remove` 别名；typed add 缺 type 失败
    - _Ref: Requirement 8, AC 2–3, 5_
  - [ ] 12.3 preset 全量校验测试
    - _Ref: Requirement 8, AC 4_
  - [ ] 12.4 Checkpoint — `./vendor/bin/phpunit`；commit: `+(cli) by Cursor: scope and breaking install`

- [ ] 13. E2E 测试
  - [ ] 13.1 _脚本_ `tests/test-task-13a.sh`：global-setup → bootstrap → typed add
    - _Ref: Requirement 5, 6, 8_
  - [ ] 13.2 _脚本_ `tests/test-task-13b.sh`：show 双 scope、cleanup、update 非 global 失败
    - _Ref: Requirement 7, 9, 10_
  - [ ] 13.3 _人工_：IDE 验证 → `docs/notes/deploy-scope-platform-verification.md`
    - _Ref: Requirement 13, AC 1–4_
  - [ ] 13.4 Checkpoint — `./vendor/bin/phpunit --testsuite e2e`；commit: `+(e2e) by Cursor: deploy scope flows`

- [ ] 14. 文档收敛
  - [ ] 14.1 SSOT：`install-behavior.md`、`cli-commands.md`、`abilities-model.md`
    - _Ref: Requirement 12, AC 3–4_
  - [ ] 14.2 `README.md`、`docs/manual/usage.md`、`docs/README.md`
    - _Ref: Requirement 12, AC 1–2_
  - [ ] 14.3 迁移指南：无参 install/add、删 preset `default`、三阶段首装（`README` + `docs/manual/usage.md` 专节；对齐 requirements GK CR2）
    - _Ref: Requirement 4, AC 3; Requirement 8, AC 1_
  - [ ] 14.4 APM skill + init-workflow（cursor/kiro 副本）
    - _Ref: Requirement 11, AC 1–7; Requirement 7, AC 3_
  - [ ] 14.5 关闭 ISS-31532、ISS-01592（`Fixed In` = shipping version）
    - _Ref: Requirement 14, AC 1–3_
  - [ ] 14.6 Checkpoint — commit: `+(docs) by Cursor: deploy scope onboarding`

- [ ] 15. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `.cursor/skills/spec-execution/SKILL.md`；按 TDG wave 执行；测试输出写日志（`PROJECT.md`）。
- 每个 Checkpoint sub-task 完成验证后**在同一 sub-task 内**执行 commit（message 见该 sub-task）。
- Task 1 Checkpoint 须先于 task 2 合入（D-CR1）。
- E2E 脚本：`.cursor/specs/deploy-scope-and-cli/tests/test-task-13*.sh`。
- **TDG 并行**：同 wave 内 sub-task 可并行执行（多 agent / 多会话）；Checkpoint 仍独占 wave。实现链（TDD、同文件）保持串行。

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1"] },
  { "id": 1, "tasks": ["1.2"] },
  { "id": 2, "tasks": ["1.3"] },
  { "id": 3, "tasks": ["1.4"] },
  { "id": 4, "tasks": ["1.5"] },
  { "id": 5, "tasks": ["2.1"] },
  { "id": 6, "tasks": ["2.2"] },
  { "id": 7, "tasks": ["2.3"] },
  { "id": 8, "tasks": ["3.1"] },
  { "id": 9, "tasks": ["3.2"] },
  { "id": 10, "tasks": ["3.3"] },
  { "id": 11, "tasks": ["3.4"] },
  { "id": 12, "tasks": ["4.1"] },
  { "id": 13, "tasks": ["4.2"] },
  { "id": 14, "tasks": ["4.3"] },
  { "id": 15, "tasks": ["5.1"] },
  { "id": 16, "tasks": ["5.2"] },
  { "id": 17, "tasks": ["5.3"] },
  { "id": 18, "tasks": ["6.1"] },
  { "id": 19, "tasks": ["6.2"] },
  { "id": 20, "tasks": ["6.3"] },
  { "id": 21, "tasks": ["7.1"] },
  { "id": 22, "tasks": ["7.2"] },
  { "id": 23, "tasks": ["7.3"] },
  { "id": 24, "tasks": ["8.1", "8.2"] },
  { "id": 25, "tasks": ["8.3"] },
  { "id": 26, "tasks": ["8.4"] },
  { "id": 27, "tasks": ["9.1"] },
  { "id": 28, "tasks": ["9.2"] },
  { "id": 29, "tasks": ["9.3"] },
  { "id": 30, "tasks": ["10.1", "10.2"] },
  { "id": 31, "tasks": ["10.3"] },
  { "id": 32, "tasks": ["11.1"] },
  { "id": 33, "tasks": ["11.2"] },
  { "id": 34, "tasks": ["11.3"] },
  { "id": 35, "tasks": ["12.1"] },
  { "id": 36, "tasks": ["12.2", "12.3"] },
  { "id": 37, "tasks": ["12.4"] },
  { "id": 38, "tasks": ["13.1", "13.2"] },
  { "id": 39, "tasks": ["13.3"] },
  { "id": 40, "tasks": ["13.4"] },
  { "id": 41, "tasks": ["14.1", "14.2"] },
  { "id": 42, "tasks": ["14.3"] },
  { "id": 43, "tasks": ["14.4", "14.5"] },
  { "id": 44, "tasks": ["14.6"] }
]}
```
