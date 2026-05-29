# Requirements Document

## Introduction

本文档定义 PRP-001 Phase 2 剩余工作的需求：将 apm CLI 的路径解析逻辑从已废弃的 `abilities/` 目录迁移到"源即目标"布局，清理 capture/ingest 死代码，修复测试套件，同步 SSOT 文档，并补充 apm init 的详细流程参考文档。

### Non-scope

- abilities.yaml schema 变更
- 跨项目 ability 分发机制
- ability 版本管理
- apm init 的代码实现变更（仅补文档）
- capture/ingest 功能的重新引入

---

## Glossary

- **Source-is-Target（源即目标）**: 设计原则——ability 文件在 package root 中的存放路径即为安装到目标项目时的路径，无需路径转换
- **Ability Registry**: `abilities.yaml` 文件，声明所有 ability 的元数据与 targets 映射
- **Target**: 目标 IDE 平台（cursor 或 kiro）
- **Package Root**: apm 包的安装根目录，即 ability 源文件所在的仓库根
- **Workspace**: 用户项目的工作目录，即 ability 安装的目标位置
- **Baseline**: 通过 ComposerBaselineResolver 解析的全局安装包路径，用于 diff 对比
- **Preset**: 一组预定义的 ability 集合，声明在 abilities.yaml 的 presets section 中
- **SSOT（Single Source of Truth）**: `docs/state/` 下的文档，与代码共同构成系统唯一事实来源
- **EARS 格式**: Easy Approach to Requirements Syntax，用于撰写验收标准的结构化语法

---

## Requirements

### Requirement 1: Installer 路径适配

**User Story:** As a developer using apm, I want the install command to resolve ability source paths from abilities.yaml targets, so that abilities are correctly installed from their actual locations.

#### Acceptance Criteria

1. WHEN installing a skill, THE Installer SHALL read the skill's target path from Ability Registry and use `$packageRoot/<targets[target]>` as the source directory.
2. WHEN installing an agent, THE Installer SHALL read the agent's target path from Ability Registry and use `$packageRoot/<targets[target]>` as the source file.
3. WHEN installing a rule, THE Installer SHALL read the rule's target path from Ability Registry and use `$packageRoot/<targets[target]>` as the source file.
4. THE Installer SHALL NOT perform suffix-based file discovery or preferred source ranking.
5. THE Installer SHALL NOT reference any path under `abilities/` directory.
6. WHEN a source path declared in Ability Registry does not exist on disk, THE Installer SHALL report a `[fail]` status with the expected path.
7. WHEN an ability has targets for only one platform, THE Installer SHALL skip that ability for the other platform without error.
8. THE Installer SHALL resolve the install target path as `$workspace/<targets[target]>`, matching the source path structure.

---

### Requirement 2: AbilityDiffService 路径适配

**User Story:** As a developer using apm check, I want the diff service to resolve baseline paths from abilities.yaml targets, so that ability status checks work correctly against the new layout.

#### Acceptance Criteria

1. WHEN diffing a skill for installed targets, THE AbilityDiffService SHALL resolve the baseline path as `$baselineRoot/<targets[target]>` (directory).
2. WHEN diffing an agent for installed targets, THE AbilityDiffService SHALL resolve the baseline path as `$baselineRoot/<targets[target]>` (file).
3. WHEN diffing a rule for installed targets, THE AbilityDiffService SHALL resolve the baseline path as `$baselineRoot/<targets[target]>` (file).
4. THE AbilityDiffService SHALL NOT perform suffix-based file discovery or preferred source ranking.
5. THE AbilityDiffService SHALL NOT reference any path under `abilities/` directory.
6. WHEN the baseline path does not exist, THE AbilityDiffService SHALL report status as `unknown`.
7. WHEN the installed path exists but differs from baseline, THE AbilityDiffService SHALL report status as `modified`.

---

### Requirement 3: PresetRegistry 迁移

**User Story:** As a developer using apm presets, I want preset definitions to be read from abilities.yaml, so that the system uses a single authoritative source for all ability metadata.

#### Acceptance Criteria

1. THE PresetRegistry SHALL read preset definitions from the `presets` section of Ability Registry.
2. THE PresetRegistry SHALL NOT read from or write to `abilities/_presets.json`.
3. WHEN a preset name is requested, THE PresetRegistry SHALL resolve its included abilities by parsing the `includes` list from Ability Registry's presets section.
4. THE preset:create and preset:delete commands SHALL operate on the Ability Registry file instead of `abilities/_presets.json`.
5. THE PresetRegistry SHALL parse `includes` entries in the format `<type>:<path>` (e.g., `skill:gitflow`, `rule:git:branch-overview`).
6. WHEN a preset references an ability not found in Ability Registry, THE PresetRegistry SHALL report an error.

---

### Requirement 4: Capture/Ingest 死代码移除

**User Story:** As a maintainer, I want all capture/ingest dead code removed, so that the codebase has no references to deprecated functionality.

#### Acceptance Criteria

1. THE system SHALL NOT contain any method named `diffForCapture` in production code.
2. THE system SHALL NOT contain any test method referencing capture or ingest functionality.
3. THE system SHALL NOT contain any import, class, or interface related to capture or ingest.

---

### Requirement 5: Gitignore 模板路径适配

**User Story:** As a developer using apm install, I want the gitignore template to be resolved from the correct location, so that .gitignore managed sections are properly maintained.

#### Acceptance Criteria

1. THE Installer SHALL resolve the gitignore template from a path that exists in the current package layout.
2. WHEN no matching gitignore template blocks are found, THE Installer SHALL output `[skip]` without error.
3. THE Installer SHALL continue to use `@apm:block` marker syntax for conditional gitignore block rendering.
4. IF the gitignore template file does not exist at the resolved path, THE Installer SHALL output `[skip]` without error.

---

### Requirement 6: 测试套件修复

**User Story:** As a developer, I want all tests to pass against the new layout, so that the test suite validates the current system behavior.

#### Acceptance Criteria

1. THE unit test fixtures SHALL create source files at paths matching the abilities.yaml targets layout (e.g., `$packageRoot/.cursor/skills/<name>/` instead of `$packageRoot/abilities/skills/<name>/`).
2. THE unit test fixtures SHALL create baseline files at paths matching the abilities.yaml targets layout (e.g., `$baseline/.cursor/rules/<category>/<name>.mdc` instead of `$baseline/abilities/rules/<category>/<name>.cursor.mdc`).
3. THE E2E test fixtures SHALL NOT reference `abilities/` directory paths.
4. THE E2E test fixtures for presets SHALL use abilities.yaml presets section instead of `abilities/_presets.json`.
5. WHEN the full test suite is executed, THE system SHALL report zero failures and zero errors.

---

### Requirement 7: SSOT 文档同步

**User Story:** As a team member, I want the state documentation to accurately reflect the current system behavior, so that SSOT remains trustworthy.

#### Acceptance Criteria

1. THE `docs/state/install-behavior.md` SHALL describe source paths using the Source-is-Target pattern (e.g., `<packageRoot>/<targets[target]>`).
2. THE `docs/state/architecture.md` SHALL describe PresetRegistry as reading from abilities.yaml presets section.
3. THE `docs/state/cli-commands.md` SHALL describe preset:create and preset:delete as operating on abilities.yaml.
4. THE `docs/state/abilities-model.md` SHALL describe preset runtime storage as the presets section of abilities.yaml.
5. THE `docs/state/gitignore.md` SHALL describe the template file location consistent with the actual implementation.
6. THE `docs/design.md` SHALL describe ability path conventions using the Source-is-Target pattern.

---

### Requirement 8: apm init Reference 文档

**User Story:** As an agent executing `/apm init`, I want a detailed reference document describing the init workflow, so that I can produce consistent, high-quality project initialization output.

#### Acceptance Criteria

1. THE apm skill SHALL contain a reference file documenting the init workflow.
2. THE reference file SHALL define a detection phase listing files and commands to inspect for project metadata.
3. THE reference file SHALL define a confirmation phase distinguishing auto-fillable fields from user-confirmation-required fields.
4. THE reference file SHALL define a generation phase specifying minimum content requirements for PROJECT.md, state baseline, and manual baseline.
5. THE reference file SHALL define idempotency rules: repeated execution SHALL only fill missing content without overwriting existing content.
6. THE reference file SHALL define the existing-content strategy (merge/skip/overwrite decision rules).

---

### Requirement 9: PRP-001 状态修正

**User Story:** As a project maintainer, I want the PRP-001 proposal status to accurately reflect completion state, so that project tracking is truthful.

#### Acceptance Criteria

1. THE PRP-001 proposal status SHALL be changed from `implemented` to `in-progress`.
2. IF Phase 2 is fully completed by this feature, THEN THE PRP-001 proposal status SHALL be changed to `implemented` with a note confirming Phase 2 completion.


---

## Clarification Round

> **CR1**: R3 AC4 要求 preset:create/delete 操作 abilities.yaml（YAML 格式）。当前 PresetRegistry 使用 JSON 读写。迁移到 YAML 写入时，如何处理格式保持？
>
> - A) 使用 symfony/yaml 的 dump 方法整体重写文件，接受格式可能与手写不同（注释丢失、排序变化）
> - B) 仅对 presets section 做局部替换（正则或行定位），保留文件其余部分的格式和注释
> - C) 引入 YAML 操作库（如 ruamel 风格的保留注释方案），确保格式完全不变
>
> **A:** A) 使用 symfony/yaml dump 整体重写，接受格式差异。

> **CR2**: R5（Gitignore 模板路径）的 AC1 说"从正确位置解析"但未指定具体路径。Design 阶段需要确定模板文件的实际位置：
>
> - A) 在 abilities.yaml 中新增一个 `gitignore` 条目，模板路径由 targets 字段声明（与其他 ability 一致）
> - B) 硬编码为 package root 下的固定路径（如 `templates/gitignore.template`）
> - C) 复用现有 rule 类型的 targets 机制，将 gitignore 模板视为一种特殊 rule
>
> **A:** package root 下的 `.gitignore` 文件本身即为模板来源——apm repo 自己用的 .gitignore 也是 gitignore template source（源即目标原则的延伸）。

> **CR3**: R2 AC6 规定 baseline 路径不存在时报告 `unknown`。但 baseline 可能因多种原因不存在（包未全局安装、版本不匹配、ability 是新增的）。是否需要区分这些情况？
>
> - A) 统一报告 `unknown`，不区分原因（当前 AC 的表述）
> - B) 区分 `no-baseline`（包未安装）和 `new`（ability 在 baseline 版本中不存在），分别报告不同状态
> - C) 仅在 verbose 模式下输出详细原因，默认仍报告 `unknown`
>
> **A:** B) 区分原因——区分 `no-baseline`（包未安装）和 `new`（ability 在 baseline 版本中不存在）。

> **CR4**: R6 AC5 要求全套件零失败。如果存在与本次变更无关的预先失败测试（pre-existing failures），design 阶段应如何处理？
>
> - A) 本次 scope 内必须修复所有测试，无论是否由本次变更引起
> - B) 预先失败的测试如果与 abilities 路径无关，可标记为 `@skip` 并在 issues/ 中记录，不阻塞本次交付
> - C) 在 design 阶段先运行测试套件确认 baseline 状态，仅对本次变更导致的新增失败负责
>
> **A:** A) 全部修复——本次 scope 内必须修复所有测试，无论是否由本次变更引起。
