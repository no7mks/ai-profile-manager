# Implementation Plan: prp001-remaining-work

## Overview

本计划将 PRP-001 Phase 2 剩余工作拆分为 11 个顶层任务，按 CR5+CR8 决策的执行顺序排列：

1. **死代码清理优先**（独立、无依赖）
2. **Installer 重构**（影响面最大）
3. **AbilityDiffService 重构**
4. **PresetRegistry 重构**
5. **Gitignore 模板路径修复**
6. **统一测试修复**（全套件零失败）
7. **E2E 测试修复**
8. **Preset 命令 DI 重构**（修复测试污染生产 abilities.yaml 问题）
9. **引入 PHPStan Level 8**（静态分析质量门禁）
10. **文档收敛**（SSOT 同步 + apm init reference + PRP-001 状态）
11. **Code Review**

每个顶层任务结束时设置 checkpoint，验证当前阶段的测试通过状态并提交。

---

## Tasks

- [x] 1. Capture/Ingest 死代码移除
  - [x] 1.1 移除 `AbilityDiffService::diffForCapture()` 方法及其调用链
    - 删除 `diffForCapture()` 公共方法
    - 删除 `diffTyped()` 中仅为 capture 服务的参数/分支（如 `$installedLayout = false` 路径）
    - 搜索全项目确认无其他 production code 引用 `diffForCapture`
    - _Ref: Requirement 4, AC 1_
  - [x] 1.2 移除测试中所有 capture/ingest 相关测试方法
    - 搜索 `tests/` 目录中包含 `capture`、`ingest`、`diffForCapture` 的测试方法
    - 删除对应测试方法（不删除整个测试文件，除非文件仅含 capture 测试）
    - _Ref: Requirement 4, AC 2_
  - [x] 1.3 搜索并移除所有 capture/ingest 相关 import、class、interface
    - 全项目 grep `capture`、`ingest`（排除 docs/、.kiro/specs/）
    - 确认无残留引用
    - _Ref: Requirement 4, AC 3_
  - [x] 1.4 Checkpoint — 死代码清理验证
    - 运行 `vendor/bin/phpunit --filter "AbilityDiffService"` 确认无引用错误
    - commit: `-(service): remove capture/ingest dead code`

- [x] 2. Installer 路径适配重构
  - [x] 2.1 重构 `installAbilityBundle()` 统一路径解析
    - 新增内部方法 `resolveSourcePath(string $type, string $name, string $target): ?string`：从 AbilityRegistry 查找 AbilityEntry，返回 `$packageRoot/<targets[target]>` 或 null
    - 新增内部方法 `resolveDestPath(string $type, string $name, string $target, string $workspace): string`：返回 `$workspace/<targets[target]>`
    - 重写 `installAbilityBundle()` 统一处理 skill/agent/rule：查找 entry → 检查 targets 是否包含 target → 构造源/目标路径 → 复制
    - 当 entry 的 targets 不含请求的 target 时，静默跳过（不输出、不报错）
    - 当源路径不存在时，输出 `[fail] Missing ability: <type> <name> (expected <path>)`
    - _Ref: Requirement 1, AC 1-3, 4-8_
  - [x] 2.2 移除已废弃的私有方法
    - 删除 `findRuleSourceFiles()`
    - 删除 `pickPreferredRuleSource()`
    - 删除 `installRuleBundle()`
    - 删除 `installAgentFile()`
    - 删除 `resolveInstallTargetRuleFile()`
    - 删除 `resolveInstallTargetDir()` 中的 `default` 分支（指向 `abilities/unknown-items/`）
    - _Ref: Requirement 1, AC 4-5_
  - [x] 2.3 移除构造函数中的 `$templatePath` 参数
    - 从构造函数签名中移除 `$templatePath`
    - `installGitIgnore()` 中模板路径改为 `$this->packageRoot . '/.gitignore'`
    - _Ref: Requirement 5, AC 1; Design/Components/GitIgnoreTemplateService_
  - [x] 2.4 Checkpoint — Installer 重构验证
    - 运行 `vendor/bin/phpunit --filter "Installer"` 查看当前失败情况（预期有 fixture 相关失败，将在 Task 6 统一修复）
    - commit: `*(Installer): adopt Source-is-Target path resolution`

- [ ] 3. AbilityDiffService 路径适配重构
  - [x] 3.1 注入 AbilityRegistry 并重写路径解析
    - 构造函数新增 `AbilityRegistry $registry` 参数
    - 重写 `diffSkill()`、`diffAgent()`、`diffRule()` 统一为：查找 AbilityEntry → `$baselineRoot/<targets[target]>` vs `$workspaceRoot/<targets[target]>`
    - 移除 `resolveRuleRelativePath()`、`pickPreferredRuleSourcePath()`
    - 移除 `resolveInstalledSkillDir()`、`resolveInstalledAgentFile()`、`resolveInstalledRuleRelativePath()`（这些方法的逻辑被 targets 字段替代）
    - _Ref: Requirement 2, AC 1-5_
  - [x] 3.2 扩展 `resolveStatus()` 支持 `no-baseline` 和 `new` 状态
    - 当 baselineRoot 本身不可用（ComposerBaselineResolver 返回 null）时，所有 ability 状态为 `no-baseline`
    - 当 baselineRoot 存在但该 ability 的 `targets[target]` 路径在 baseline 中不存在时，状态为 `new`
    - `no-baseline` → exit 2（环境错误）；`new` → exit 0（用户新增，不算 drift）
    - _Ref: Requirement 2, AC 6-7; CR3, CR6_
  - [x] 3.3 适配 CheckService 的状态渲染和 exit code
    - `CheckService::renderResults()` 新增 `no-baseline` 和 `new` 的 prefix 映射
    - `CheckService::evaluateExitCode()` 中 `no-baseline` → exit 2，`new` → exit 0
    - _Ref: Requirement 2, AC 6; CR6_
  - [x] 3.4 Checkpoint — AbilityDiffService 重构验证
    - 运行 `vendor/bin/phpunit --filter "AbilityDiffService|CheckService"` 查看当前状态
    - commit: `*(AbilityDiffService): adopt Source-is-Target baseline resolution`

- [ ] 4. PresetRegistry 迁移到 abilities.yaml
  - [x] 4.1 重写 PresetRegistry 构造函数和读取逻辑
    - 构造函数从 `string $workspaceRoot` 改为 `AbilityRegistry $registry`
    - 在 AbilityRegistry 上新增 `public function getRegistryPath(): string` getter（返回 `$this->registryPath`）
    - `allPresets()` 从 `$registry->parse()['presets']` 读取
    - `getPreset()` 解析 `includes` 条目（第一个 `:` 为分隔符）
    - 移除 `PRESETS_RELATIVE_PATH` 常量、`loadFromWorkspace()`、`saveToWorkspace()` 方法
    - _Ref: Requirement 3, AC 1-3, 5_
  - [x] 4.2 实现 YAML 写入方法（createPreset / deletePreset / addAbility / removeAbility）
    - 使用 `Yaml::parse()` 读取整个 abilities.yaml → 修改 presets section → `Yaml::dump()` 整体重写
    - `createPreset()`: 新增 preset 条目到 presets 数组
    - `deletePreset()`: 从 presets 数组中移除指定 name 的条目
    - `addAbility()` / `removeAbility()`: 操作指定 preset 的 includes 列表
    - 引用验证：includes 中引用的 ability 必须存在于 AbilityRegistry 对应 section
    - _Ref: Requirement 3, AC 4, 6; CR1, CR7_
  - [x] 4.3 适配 PresetCreateCommand 和 PresetDeleteCommand
    - 更新命令 description（移除 `abilities/_presets.json` 引用）
    - 更新命令逻辑调用 PresetRegistry 新接口
    - _Ref: Requirement 3, AC 4_
  - [x] 4.4 Checkpoint — PresetRegistry 迁移验证
    - 运行 `vendor/bin/phpunit --filter "PresetRegistry|PresetManifest"` 查看当前状态
    - commit: `*(PresetRegistry): migrate from JSON to abilities.yaml presets section`

- [x] 5. Gitignore 模板路径修复
  - [x] 5.1 验证 Installer 中 gitignore 模板路径已正确指向 `$packageRoot/.gitignore`
    - 确认 Task 2.3 已完成此变更
    - 确认 `renderManagedBlock()` 在模板文件不存在时返回空字符串（已有逻辑）
    - 确认 `installGitIgnore()` 在 managedBody 为空时输出 `[skip]`（已有逻辑）
    - _Ref: Requirement 5, AC 1-4_
  - [x] 5.2 Checkpoint — Gitignore 路径验证
    - 运行 `vendor/bin/phpunit --filter "GitIgnore"` 确认无回归
    - commit: `*(Installer): gitignore template path now uses packageRoot/.gitignore`

- [x] 6. 统一测试套件修复（Unit + Integration）
  - [x] 6.1 修复 InstallerTest fixture 路径
    - 将 fixture 中 `$packageRoot/abilities/skills/<name>/` 改为 `$packageRoot/.cursor/skills/<name>/` 或 `$packageRoot/.kiro/skills/<name>/`
    - 将 fixture 中 `$packageRoot/abilities/agents/<name>.<target>.md` 改为 `$packageRoot/.cursor/agents/<name>.md`
    - 将 fixture 中 `$packageRoot/abilities/rules/` 改为对应 targets 路径
    - 更新 gitignore fixture 从 `abilities/gitignore/template.gitignore` 改为 `$packageRoot/.gitignore`
    - _Ref: Requirement 6, AC 1, 3_
  - [x] 6.2 修复 AbilityDiffServiceTest fixture 路径
    - 将 baseline fixture 从 `$baseline/abilities/skills/<name>/` 改为 `$baseline/.cursor/skills/<name>/`
    - 将 baseline fixture 从 `$baseline/abilities/agents/` 改为 `$baseline/.cursor/agents/`
    - 将 baseline fixture 从 `$baseline/abilities/rules/` 改为对应 targets 路径
    - 更新 AbilityDiffService 构造调用以注入 AbilityRegistry
    - _Ref: Requirement 6, AC 2_
  - [x] 6.3 修复 PresetRegistryTest 和 PresetManifestCommandsTest
    - 移除 `abilities/_presets.json` fixture 创建
    - 改为在 abilities.yaml fixture 中添加 presets section
    - 更新 PresetRegistry 构造调用（传入 AbilityRegistry 而非 workspaceRoot）
    - _Ref: Requirement 6, AC 4_
  - [x] 6.4 修复 InstallerRegistryIntegrationTest 和其他集成测试
    - 更新所有引用旧路径的集成测试 fixture
    - 确保 AbilityRegistry mock/fixture 与新接口一致
    - _Ref: Requirement 6, AC 1-2_
  - [x] 6.5 修复 CheckServiceTest 和 TypedCheckCommandsTest
    - 适配 `no-baseline`/`new` 状态的新行为
    - 更新 exit code 断言（`no-baseline` → 2，`new` → 0）
    - _Ref: Requirement 6, AC 1-2; CR6_
  - [x] 6.6 修复 Service/ 子目录下的扩展测试
    - `AbilityDiffRuleResolutionTest` — 移除后缀搜索相关测试，改为 targets 路径测试
    - `AbilityDiffServiceExtendedTest` — 适配新构造函数和路径逻辑
    - `InstallerExtendedTest` — 适配新路径解析
    - _Ref: Requirement 6, AC 1-2_
  - [x] 6.7 Checkpoint — 单元/集成测试全绿验证
    - 运行 `vendor/bin/phpunit --exclude-group e2e` 确认零失败零错误
    - 修复任何 pre-existing failure（CR4 决策：全部修复）
    - commit: `*(test): fix all unit/integration test fixtures for Source-is-Target layout`

- [x] 7. E2E 测试修复
  - [x] 7.1 修复 EndToEndTestCase 基础 fixture
    - `createGitignoreTemplate()` 改为在 packageRoot 下创建 `.gitignore` 文件
    - 移除所有 `abilities/` 目录 fixture 创建
    - 确保 abilities.yaml fixture 包含正确的 targets 路径
    - _Ref: Requirement 6, AC 3_
  - [x] 7.2 修复 PresetLifecycleTest
    - 移除 `abilities/_presets.json` fixture
    - 改为验证 abilities.yaml presets section 的读写
    - _Ref: Requirement 6, AC 4_
  - [x] 7.3 修复 TypedCommandsLifecycleTest 和 ShowAndUpdateTest
    - 更新 fixture 路径到新布局
    - 验证 install/check/uninstall 完整流程
    - _Ref: Requirement 6, AC 3-5_
  - [x] 7.4 Checkpoint — E2E 测试全绿验证
    - 运行 `vendor/bin/phpunit --group e2e` 确认零失败
    - 运行 `vendor/bin/phpunit` 确认全套件零失败零错误
    - commit: `*(test): fix E2E test fixtures for Source-is-Target layout`

- [x] 8. Preset 命令 AbilityRegistry 注入重构
  - [x] 8.1 重构 PresetCreateCommand / PresetDeleteCommand 构造函数
    - 新增构造参数 `?PresetRegistry $presetRegistry = null`
    - `execute()` 中若 `$this->presetRegistry` 已注入则直接使用，否则 fallback 到 `new PresetRegistry(new AbilityRegistry(__DIR__ . '/../../abilities.yaml'))`（保持 CLI 独立运行兼容）
    - _目的：测试可注入 temp registry，不再写入生产 abilities.yaml_
  - [x] 8.2 重构 PresetAddAbilityCommand / PresetRemoveAbilityCommand 构造函数
    - 同 8.1 模式：构造参数注入 + fallback
  - [x] 8.3 更新 ConsoleRegistration 传入 PresetRegistry
    - `ConsoleRegistration::register()` 中 `PresetCreateCommand`、`PresetDeleteCommand`、`PresetAddAbilityCommand`、`PresetRemoveAbilityCommand` 均传入 `$presetRegistry`
  - [x] 8.4 修复 CommandErrorPathsTest
    - 所有测试方法改为注入 temp 目录的 AbilityRegistry/PresetRegistry
    - 移除 `testPresetCreateCommandFailsOnDuplicateName` 中对生产文件的依赖
    - 确认测试不再触碰根目录 `abilities.yaml`
  - [x] 8.5 清理：移除根 abilities.yaml 中被测试污染的 "existing" preset
    - 如果 `abilities.yaml` 中存在 `name: existing` preset，删除该条目
    - 验证 `abilities.yaml` 恢复到预期状态
  - [x] 8.6 Checkpoint — Preset 命令 DI 验证
    - 运行 `vendor/bin/phpunit --filter "CommandErrorPaths|PresetCreate|PresetDelete|PresetAdd|PresetRemove"` 确认全绿
    - 运行 `git diff abilities.yaml` 确认测试未修改生产文件
    - commit: `*(command) by Kiro: inject PresetRegistry into Preset commands, fix test pollution`

- [x] 9. 引入 PHPStan Level 8
  - [x] 9.1 安装 PHPStan 并创建配置
    - `composer require --dev phpstan/phpstan`
    - 创建 `phpstan.neon`：level 8，paths 指向 `src/`，排除 `vendor/`
    - 配置 `phpstan.neon` 中 `parameters.memoryLimit: 512M`（避免 OOM 试错）
    - 验证：`vendor/bin/phpstan analyse --memory-limit=512M` 能正常运行（允许有 error 输出）
  - [x] 9.2 生成 baseline 并归类错误
    - 运行 `vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M` 生成 `phpstan-baseline.neon`
    - 分析 baseline 内容，按错误类型（missing type、return type、parameter type、access level 等）和文件维度归类统计
    - 将归类结果记录为任务内注释（供 9.3 参考），不重复执行 analyse 来获取已知信息
  - [x] 9.3 为所有 PHP 文件添加 declare(strict_types=1) 并更新 baseline
    - 扫描 `src/` 和 `tests/` 下所有 `.php` 文件，若缺少 `declare(strict_types=1)` 则在 `<?php` 后插入
    - 运行 `vendor/bin/phpunit` 确认测试仍全绿
    - 运行 `vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M` 更新 baseline
    - 运行 `vendor/bin/phpstan analyse --memory-limit=512M` 确认零错误（baseline 兜底）
  - [x] 9.4 逐步消减 baseline 至零（尽可能）
    - 按 9.2 归类结果，按错误类型批量修复（优先：missing return type → missing param type → 其他）
    - 每批修复后运行 `vendor/bin/phpstan analyse --memory-limit=512M` 验证
    - 如果剩余 error 涉及第三方库或修复成本过高，保留 baseline 并在 Notes 中说明原因
  - [x] 9.5 Checkpoint — PHPStan 验证
    - 运行 `vendor/bin/phpstan analyse --memory-limit=512M` 确认零错误（或仅剩 baseline 中已说明的条目）
    - 运行 `vendor/bin/phpunit` 确认测试仍全绿（类型修复不应破坏行为）
    - commit: `+(quality): introduce PHPStan level 8 with zero (or minimal) baseline`

- [x] 10. 文档收敛
  - [x] 10.1 更新 SSOT 文档（docs/state/ 核心文件）
    - `docs/state/install-behavior.md`：源路径描述改为 `<packageRoot>/<targets[target]>`；gitignore 模板路径改为 `<packageRoot>/.gitignore`
    - `docs/state/architecture.md`：PresetRegistry 描述改为读取 abilities.yaml；数据流图移除 `abilities/_presets.json`
    - `docs/state/cli-commands.md`：preset:create/delete 描述改为操作 abilities.yaml
    - _Ref: Requirement 7, AC 1-3_
  - [x] 10.2 更新 SSOT 文档（docs/state/ 补充文件 + docs/design.md）
    - `docs/state/abilities-model.md`：移除 "Preset 运行时存储（_presets.json）" section，改为说明 presets section 即为唯一存储
    - `docs/state/gitignore.md`：模板文件路径改为 `<packageRoot>/.gitignore`
    - `docs/design.md`：能力路径约定全部改为 Source-is-Target 模式
    - _Ref: Requirement 7, AC 4-6_
  - [x] 10.3 创建 apm init reference 文档
    - 在 `.cursor/skills/apm/references/` 下新建 `init-workflow.md`（同步到 `.kiro/skills/apm/references/`）
    - 定义 detection phase：列出需要检查的文件和命令
    - 定义 confirmation phase：区分 auto-fillable 和 user-confirmation-required 字段
    - 定义 generation phase：PROJECT.md、state baseline、manual baseline 的最低内容要求
    - 定义 idempotency rules：重复执行只填充缺失内容
    - 定义 existing-content strategy：merge/skip/overwrite 决策规则
    - _Ref: Requirement 8, AC 1-6_
  - [x] 10.4 修正 PRP-001 proposal 状态
    - 将 `changes/unreleased/proposals/PRP-001-abilities-relocation.md` 中 status 从 `implemented` 改为 `in-progress`
    - 添加注释说明 Phase 2 代码适配正在进行
    - _Ref: Requirement 9, AC 1-2_
  - [x] 10.5 Checkpoint — 文档收敛验证
    - 检查所有修改的文档无 broken links
    - 确认 `vendor/bin/phpunit` 仍全绿（文档变更不应影响测试）
    - commit: `*(state): sync SSOT with Source-is-Target layout, add apm init reference`

- [x] 11. Code Review
  - [x] 11.1 委托 code-reviewer sub-agent 执行全量 code review
    - 基于当前分支的 diff 进行 review
    - 发现问题直接修复
    - _Ref: 全部 Requirements 的实现质量保证_
  - [x] 11.2 Final Checkpoint — 最终验证
    - 运行 `vendor/bin/phpunit` 确认全套件零失败零错误
    - 运行 `vendor/bin/phpstan analyse` 确认无静态分析错误
    - commit: `*(review): address code review findings`

---

## Notes

- 遵循 `spec-execution` 规则执行所有任务
- 所有任务均为必须执行（无 optional 标记），因为 CR4 要求全套件零失败
- 每个 checkpoint 包含验证命令和 commit message，commit 随 checkpoint 一起执行，确保增量可回滚
- Task 6/7 的测试修复依赖 Task 2-4 的重构完成，因此放在后面
- Task 5 较轻量（主要验证 Task 2.3 的变更），独立列出以确保 R5 的可追溯性
- Property-based tests 使用 PHPUnit DataProvider 模拟（100+ 随机输入），在 Task 6 中随 fixture 修复一并处理
- Task 11 的 code review 委托给 code-reviewer sub-agent，确保第三方视角的质量把关
- `no-baseline` exit code = 2（CR6 决策：环境错误），`new` exit code = 0（用户新增不算 drift）
- PHPStan 运行始终使用 `--memory-limit=512M`（或 neon 中配置 memoryLimit），避免 OOM 试错循环
- PHPStan baseline 生成后先归类错误再修复，不重复执行 analyse 仅为获取已知信息
- PresetRegistry YAML 写入使用整体重写策略（CR7 决策），接受格式差异

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3"] },
    { "id": 2, "tasks": ["1.4"] },
    { "id": 3, "tasks": ["2.1"] },
    { "id": 4, "tasks": ["2.2"] },
    { "id": 5, "tasks": ["2.3"] },
    { "id": 6, "tasks": ["2.4"] },
    { "id": 7, "tasks": ["3.1", "4.1"] },
    { "id": 8, "tasks": ["3.2", "4.2"] },
    { "id": 9, "tasks": ["3.3", "4.3"] },
    { "id": 10, "tasks": ["3.4", "4.4"] },
    { "id": 11, "tasks": ["5.1"] },
    { "id": 12, "tasks": ["5.2"] },
    { "id": 13, "tasks": ["6.1", "6.2", "6.3"] },
    { "id": 14, "tasks": ["6.4", "6.5", "6.6"] },
    { "id": 15, "tasks": ["6.7"] },
    { "id": 16, "tasks": ["7.1"] },
    { "id": 17, "tasks": ["7.2", "7.3"] },
    { "id": 18, "tasks": ["7.4"] },
    { "id": 19, "tasks": ["8.1", "8.2"] },
    { "id": 20, "tasks": ["8.3"] },
    { "id": 21, "tasks": ["8.4", "8.5"] },
    { "id": 22, "tasks": ["8.6"] },
    { "id": 23, "tasks": ["9.1"] },
    { "id": 24, "tasks": ["9.2"] },
    { "id": 25, "tasks": ["9.3"] },
    { "id": 26, "tasks": ["9.4"] },
    { "id": 27, "tasks": ["9.5"] },
    { "id": 28, "tasks": ["10.1", "10.2", "10.3", "10.4"] },
    { "id": 29, "tasks": ["10.5"] },
    { "id": 30, "tasks": ["11.1"] },
    { "id": 31, "tasks": ["11.2"] }
  ]
}
```
