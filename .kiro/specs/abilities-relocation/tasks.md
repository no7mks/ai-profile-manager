# Implementation Plan: abilities-relocation

## Overview

本计划将 design 拆解为 6 个自动化实现 task + E2E 测试 + 文档收敛 + Code Review。

**执行策略**（基于 CR-3 决策 B — 先移除后新增）：

1. 先移除 Capture/Ingest 模块，清理代码库
2. 新增 AbilityRegistry 并一步到位替换 Installer 解析逻辑（CR-5 决策 A）
3. 新增 HookInstaller + HookChecker
4. 修改 Installer/CheckService 集成 hook 支持
5. 修改 ShowCommand 展示 hook
6. State 文档统一补写（CR-6 决策 A）

**验证策略**（CR-4 决策 C）：移除后运行全量测试 + grep 搜索已删除类名确认无残留。

---

## Tasks

- [ ] 1. Capture/Ingest 模块移除
  - [ ] 1.1 移除 Capture/Ingest 源文件与测试
    - 删除 `src/Capture/` 目录
    - 删除 `src/Service/CaptureService.php`
    - 删除 `src/Command/` 下的 CaptureCommand、SkillCaptureCommand、RuleCaptureCommand、AgentCaptureCommand、IngestCaptureChangeCommand
    - 删除对应的测试文件与 fixture
    - 修改 ConsoleRegistration：移除 Capture/Ingest 命令注册和相关依赖注入参数
    - _Ref: Requirement 6, AC 1-5; Requirement 7, AC 1-4_
  - [ ] 1.2 验证无残留引用
    - 运行全量测试确认通过
    - grep 搜索已删除类名（CaptureService、CaptureCommand、SkillCaptureCommand、RuleCaptureCommand、AgentCaptureCommand、IngestCaptureChangeCommand、CaptureChangeIngestor）确认无残留
    - _Ref: Requirement 6, AC 4; Requirement 7, AC 4_
  - [ ] 1.3 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（移除 Capture/Ingest 模块条目）
    - commit: `refactor: remove deprecated Capture/Ingest modules`

- [ ] 2. AbilityRegistry 新增与 Installer 集成
  - [ ] 2.1 实现 AbilityRegistry + AbilityEntry + AbilityRegistryException
    - 编写测试：解析正常 YAML、未知 section 忽略、缺失字段收集所有错误后一次性报告、文件不存在、YAML 无效
    - 确认测试失败（RED）
    - 实现 `AbilityRegistry::parse()` — 按顶层 section 名称分类，仅识别 knownSections()，忽略未知 section
    - 实现 `AbilityEntry` 值对象
    - 实现 `AbilityRegistryException` 三种工厂方法
    - 确认测试通过（GREEN）
    - _Ref: Requirement 1, AC 1-4; Requirement 8, AC 1-3, 6_
  - [ ] 2.2 替换 Installer 解析逻辑
    - 编写测试：Installer 构造函数注入 AbilityRegistry 后 installTyped/uninstallTyped 正确分发
    - 确认测试失败（RED）
    - 修改 Installer 构造函数注入 AbilityRegistry
    - `listAvailableItems()` 委托给 `AbilityRegistry.parse()` 结果
    - 移除 Installer 中所有后缀解析逻辑（`findRuleSourceFiles` 中的后缀匹配）
    - installTyped/uninstallTyped 参数扩展为包含 `hooks` 键
    - 确认测试通过（GREEN）
    - _Ref: Requirement 1, AC 1, 4; Requirement 2, AC 1-3_
  - [ ] 2.3 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（新增 AbilityRegistry 模块条目）
    - commit: `feat: add AbilityRegistry and integrate with Installer`

- [ ] 3. HookInstaller 新增
  - [ ] 3.1 实现 HookInstaller Kiro 平台逻辑
    - 编写测试：installKiro 文件复制成功、目录自动创建、uninstallKiro 删除文件、卸载时文件不存在跳过
    - 确认测试失败（RED）
    - 实现 `installKiro()` 和 `uninstallKiro()`
    - 确认测试通过（GREEN）
    - _Ref: Requirement 9, AC 1, 3, 4_
  - [ ] 3.2 实现 HookInstaller Cursor 平台逻辑
    - 编写测试：installCursor 目录递归复制 + JSON merge（新建 hooks.json、追加条目、去重跳过）、uninstallCursor 条目移除 + 目录删除、hooks.json 无效 JSON 抛异常
    - 确认测试失败（RED）
    - 实现 `installCursor()` 和 `uninstallCursor()`
    - 实现 `HookRegistryException`
    - 确认测试通过（GREEN）
    - _Ref: Requirement 10, AC 1-4, 6-8_
  - [ ] 3.3 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（新增 HookInstaller 模块条目）
    - commit: `feat: add HookInstaller for Kiro and Cursor platforms`

- [ ] 4. HookChecker 新增与 CheckService 集成
  - [ ] 4.1 实现 HookChecker
    - 编写测试：checkKiro ok/drift/missing 三种状态、checkCursor ok/missing（目录/入口脚本/hooks.json 条目三项检查）
    - 确认测试失败（RED）
    - 实现 `HookChecker::checkKiro()` 和 `HookChecker::checkCursor()`
    - 确认测试通过（GREEN）
    - _Ref: Requirement 9, AC 2; Requirement 10, AC 5_
  - [ ] 4.2 修改 CheckService 集成 HookChecker
    - 编写测试：CheckService 对 hook 类型分发到 HookChecker、exit code 2 当存在 drift/missing
    - 确认测试失败（RED）
    - 修改 CheckService 构造函数注入 HookChecker
    - checkTyped 参数扩展为包含 `hooks` 键，hook 类型分发到 HookChecker
    - 确认测试通过（GREEN）
    - _Ref: Requirement 3, AC 1-7_
  - [ ] 4.3 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（新增 HookChecker 模块条目）
    - commit: `feat: add HookChecker and integrate with CheckService`

- [ ] 5. Installer hook 分发与 --force 卸载
  - [ ] 5.1 Installer hook 类型安装分发
    - 编写测试：installTyped 对 hook 类型调用 HookInstaller（Kiro/Cursor 分别验证）、Directory_Duplicate 不对 hook 触发
    - 确认测试失败（RED）
    - 修改 Installer 构造函数注入 HookInstaller
    - installTyped 内部对 hook 类型分发到 HookInstaller.installKiro/installCursor
    - 确认测试通过（GREEN）
    - _Ref: Requirement 2, AC 1-6; Requirement 9, AC 1; Requirement 10, AC 1-3_
  - [ ] 5.2 Installer hook 类型卸载分发与 --force 选项
    - 编写测试：uninstallTyped 对 hook 类型调用 HookInstaller.uninstall*、drift 时无 --force 中止、drift 时有 --force 继续
    - 确认测试失败（RED）
    - uninstallTyped 内部对 hook 类型分发到 HookInstaller.uninstallKiro/uninstallCursor
    - 新增 --force 选项传递到 uninstall 流程
    - 确认测试通过（GREEN）
    - _Ref: Requirement 4, AC 1-5; Requirement 9, AC 3-4; Requirement 10, AC 6-7_
  - [ ] 5.3 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（Installer 模块描述更新）
    - commit: `feat: integrate hook dispatch and --force uninstall in Installer`

- [ ] 6. ShowCommand 修改
  - [ ] 6.1 实现 ShowCommand hook 展示与类型过滤
    - 编写测试：show 输出包含 Hooks 分区、--type hook 仅输出 hook 分区、未知类型返回错误、无 hook 时显示空状态、无过滤时包含所有分区
    - 确认测试失败（RED）
    - 修改 ShowCommand：新增 `renderTypeSection('Hooks', 'hook', ...)` 调用
    - 新增 `--type` 选项，支持类型过滤（已知类型：rule、agent、skill、hook、gitignore、preset）
    - 确认测试通过（GREEN）
    - _Ref: Requirement 11, AC 1-5_
  - [ ] 6.2 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（ShowCommand 描述更新）
    - commit: `feat: add hook display and --type filter to ShowCommand`

- [ ] 7. E2E 测试
  - [ ] 7.1 验证 Capture/Ingest 命令不可达
    - 执行 `php bin/apm capture`、`php bin/apm skill:capture`、`php bin/apm rule:capture`、`php bin/apm agent:capture`、`php bin/apm ingest`
    - 确认每个命令返回 "command not found" 错误
    - _Ref: Requirement 6, AC 5; Requirement 7, AC 1_
  - [ ] 7.2 验证 hook install → check → uninstall 完整流程（Kiro 平台）
    - 准备测试用 hook ability（在 abilities.yaml 中注册）
    - 执行 `php bin/apm install --target kiro`
    - 确认 `.kiro/hooks/` 下出现对应 hook 文件
    - 执行 `php bin/apm check --target kiro`，确认 hook 状态为 ok
    - 手动修改已安装 hook 文件，再次 check 确认状态为 drift
    - 执行 `php bin/apm uninstall --target kiro`，确认 hook 文件被删除
    - _Ref: Requirement 9, AC 1-4_
  - [ ] 7.3 验证 hook install → check → uninstall 完整流程（Cursor 平台）
    - 执行 `php bin/apm install --target cursor`
    - 确认 `.cursor/hooks/<name>/` 目录存在且 `.cursor/hooks.json` 中包含对应条目
    - 执行 `php bin/apm check --target cursor`，确认 hook 状态为 ok
    - 执行 `php bin/apm uninstall --target cursor`，确认目录删除且 hooks.json 中条目移除
    - _Ref: Requirement 10, AC 1-7_
  - [ ] 7.4 验证 show 命令展示 hook
    - 执行 `php bin/apm show`，确认输出包含 Hooks 分区
    - 执行 `php bin/apm show --type hook`，确认仅输出 hook 分区
    - 执行 `php bin/apm show --type unknown`，确认返回错误并列出已知类型
    - _Ref: Requirement 11, AC 1-3_
  - [ ] 7.5 验证 abilities.yaml 错误处理
    - 构造含多个格式错误条目的 abilities.yaml
    - 执行任意命令，确认一次性报告所有错误
    - _Ref: Requirement 1, AC 3; Requirement 8, AC 6_

- [ ] 8. 文档收敛
  - [ ] 8.1 更新 State 文档
    - 更新 `docs/state/architecture.md`：列出所有当前模块及其职责（含 AbilityRegistry、HookInstaller、HookChecker），移除 Capture/Ingest
    - 新建 `docs/state/cli-commands.md`：为每个已注册命令描述签名、正常行为、错误条件
    - 新建 `docs/state/abilities-model.md`：描述 abilities.yaml 完整格式（含 hooks section）、Preset 引用格式
    - 新建 `docs/state/install-behavior.md`：描述每种 ability 类型的安装/检查/卸载逻辑（含 hook 平台差异）
    - 新建 `docs/state/gitignore.md`：描述 Marker_Block 格式与操作规则
    - _Ref: Requirement 12, AC 1-7_
  - [ ] 8.2 更新 Manual 文档
    - 更新 `docs/manual/usage.md`：新增 hook 类型 ability 的安装/检查/卸载示例，移除 capture/ingest 相关内容，补充 `--type` 过滤和 `apm show --type hook` 示例
    - _Ref: Requirement 11, AC 1-2; Requirement 6, AC 5_
  - [ ] 8.3 Migration Guide
    - 在 `docs/manual/usage.md` 或 `CHANGELOG.md` 中补充迁移说明：capture/ingest 命令已移除，hook 类型为新增功能，无需迁移操作
    - _Ref: Requirement 6, AC 1; Requirement 7, AC 1_
  - [ ] 8.4 Checkpoint
    - 运行 `./vendor/bin/phpunit`
    - 更新 `docs/state/architecture.md`（最终版本确认）
    - commit: `docs: add state documentation for abilities-relocation`

- [ ] 9. Code Review
  - 委托给 code-reviewer sub-agent 执行

---

## Notes

- 遵循 `spec-execution` 规则
- commit 随 checkpoint 一起执行
- 测试命令输出写入文件，后续检索基于该文件（PROJECT.md 约束）
- Capture/Ingest 移除后的 grep 验证使用类名全称搜索，确保无 use/import/instanceof 残留
- AbilityRegistry 替换 Installer 解析逻辑为一步到位（CR-5 决策 A），不做渐进式迁移
- hook 类型不触发 Directory_Duplicate 检测（hook 非目录级安装，Kiro 为单文件，Cursor 虽为目录但语义不同于 skill）
- Requirement 5（Gitignore 管理）：design 明确 GitignoreManager 不变，该 Requirement 描述的是现有已实现行为，无需新增 task

---

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1"] },
  { "id": 1, "tasks": ["1.2"] },
  { "id": 2, "tasks": ["1.3"] },
  { "id": 3, "tasks": ["2.1"] },
  { "id": 4, "tasks": ["2.2"] },
  { "id": 5, "tasks": ["2.3"] },
  { "id": 6, "tasks": ["3.1", "3.2"] },
  { "id": 7, "tasks": ["3.3"] },
  { "id": 8, "tasks": ["4.1"] },
  { "id": 9, "tasks": ["4.2"] },
  { "id": 10, "tasks": ["4.3"] },
  { "id": 11, "tasks": ["5.1", "5.2"] },
  { "id": 12, "tasks": ["5.3"] },
  { "id": 13, "tasks": ["6.1"] },
  { "id": 14, "tasks": ["6.2"] },
  { "id": 15, "tasks": ["7.1", "7.2", "7.3", "7.4", "7.5"] },
  { "id": 16, "tasks": ["8.1"] },
  { "id": 17, "tasks": ["8.2", "8.3"] },
  { "id": 18, "tasks": ["8.4"] },
  { "id": 19, "tasks": ["9"] }
]}
```
