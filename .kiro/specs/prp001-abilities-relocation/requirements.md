# Requirements Document

## Introduction

apm 项目正在将 ability 文件从独立模板仓库（`abilities/` + `scaffold/`）迁移为"源即目标"结构——文件直接放在 `.cursor/` 和 `.kiro/` 下生效。本需求覆盖：完成 Phase 1 文件迁移验证与补齐、Phase 2 代码适配（CLI 全面适配新布局）、移除 capture/ingest 功能、以及补写完整的 state 文档体系。

**不涉及的内容**：跨项目 ability 分发、ability 版本管理、capture/ingest 兼容层或迁移脚本、旧格式数据兼容。

---

## Glossary

- **APM**: AI Profile Manager，管理 AI IDE abilities 的 PHP CLI 工具
- **Ability**: 可安装到目标项目的配置单元，包含 rule、agent、skill 三种类型
- **abilities.yaml**: apm 唯一的 ability 注册表文件，定义所有 ability 的路径、描述和目标映射
- **Target**: ability 安装的目标平台，当前支持 cursor（`.cursor/`）和 kiro（`.kiro/`）
- **Path_Identifier**: abilities.yaml 中 ability 的唯一标识符，使用冒号分隔的路径格式（如 `git:branch-overview`）
- **Marker_Block**: `.gitignore` 中由 `@apm:block` 标记包围的受管理区域
- **Scaffold**: 项目脚手架模板，由 `apm init` 硬编码路径安装，不纳入 abilities.yaml
- **Preset**: abilities.yaml 中定义的 ability 组合，使用 `type:path` 格式引用成员
- **Suffix_Parsing**: 旧逻辑中通过文件后缀（`.cursor.mdc` / `.kiro.md`）判定 target 的方式
- **Path_Prefix_Determination**: 新逻辑中通过路径前缀（`.cursor/` / `.kiro/`）判定 target 的方式
- **Directory_Duplicate**: skill 安装时在 cursor 和 kiro 两个 target 下保持完全相同副本的策略
- **Installer**: 从 abilities.yaml 解析 ability 列表并复制到目标项目的服务模块
- **CheckService**: 对比已安装 ability 与源文件 diff 状态的服务模块
- **GitignoreManager**: 负责 `.gitignore` marker block 读写操作的服务模块
- **ProjectInitializer**: 负责 scaffold 模板安装（`apm init`）的服务模块
- **ConsoleRegistration**: 注册所有 CLI 命令到应用容器的核心模块
- **Capture**: 已废弃的功能，用于捕获本地变更回流到 ability 仓库
- **Ingest**: 已废弃的功能，用于从外部导入变更

---

## Requirements

### Requirement 1: Phase 1 文件迁移验证与补齐

**User Story:** As a developer, I want all ability files to reside at their real effective paths, so that the project itself can directly use these abilities without a separate template directory.

#### Acceptance Criteria

1. THE APM project SHALL have all files listed in the PRP-001 migration mapping table present at their designated target paths under `.cursor/` and `.kiro/`
2. WHEN a file from the PRP-001 mapping table is missing at its target path, THE migration process SHALL create the file at the correct location with content identical to the corresponding source file in `abilities/` or `scaffold/`, excluding any target-suffix in the filename
3. THE APM project SHALL NOT retain the `abilities/` directory after migration is complete
4. THE APM project SHALL NOT retain the `scaffold/` directory after migration is complete
5. WHEN a skill is migrated, THE migration process SHALL place byte-for-byte identical copies of all files and subdirectories under both `.cursor/skills/<name>/` and `.kiro/skills/<name>/`
6. IF a scaffold target file already exists at the destination path with non-empty content, THEN THE migration process SHALL preserve the existing file and skip overwriting it
7. WHEN migration is complete, THE APM project SHALL contain an `abilities.yaml` file at the project root whose `targets` paths reference only files that exist at the declared locations under `.cursor/` and `.kiro/`

### Requirement 2: abilities.yaml 路径解析适配

**User Story:** As a developer, I want the install and check commands to read ability definitions from abilities.yaml using path-based identifiers, so that the system no longer depends on file suffix conventions.

#### Acceptance Criteria

1. THE Installer SHALL read ability definitions exclusively from the `abilities.yaml` file located at the apm package root (resolved via Composer autoload or vendor path), with target paths resolved relative to the destination project root
2. WHEN the Installer resolves an ability's install destination, THE Installer SHALL use the `targets` mapping of the matching entry in abilities.yaml to determine the real file path for each platform (cursor or kiro)
3. THE Installer SHALL NOT use file suffix patterns (`.cursor.mdc`, `.cursor.md`, `.kiro.md`, `.kiro.mdc`) to determine target platform or resolve source/destination paths
4. WHEN the CheckService verifies ability installation status, THE CheckService SHALL use the `targets` mapping of the matching entry in abilities.yaml to determine the expected file path for each platform
5. THE CheckService SHALL NOT use file suffix patterns to determine target platform or resolve expected file paths
6. WHEN an ability entry in abilities.yaml contains a `targets` mapping with only one platform key (either `cursor` or `kiro`), THE Installer SHALL install that ability only to the single specified platform
7. WHEN an ability entry in abilities.yaml contains a `targets` mapping with both `cursor` and `kiro` keys, THE Installer SHALL install that ability to both platforms
8. IF a requested ability path is not found in abilities.yaml, THEN THE Installer SHALL report a failure indicating the unresolved ability path and SHALL NOT attempt installation for that ability
9. IF a requested ability path is not found in abilities.yaml, THEN THE CheckService SHALL report the ability status as unknown for that entry
10. WHEN the target directory for an ability does not exist in the destination project, THE Installer SHALL automatically create the required directory hierarchy (mkdir -p semantics) and output a notice informing the user that the directory was created

### Requirement 3: 路径前缀判定替代后缀解析

**User Story:** As a developer, I want target platform determination to use path prefixes instead of file suffixes, so that the naming convention is simpler and files can live at their real effective paths.

#### Acceptance Criteria

1. THE APM CLI SHALL determine target platform by inspecting the path prefix (`.cursor/` for cursor, `.kiro/` for kiro) from abilities.yaml target values
2. THE APM CLI SHALL NOT contain any logic that parses file suffixes (`.cursor.mdc`, `.kiro.md`, `.cursor.md`) to determine target platform
3. WHEN a target path starts with `.cursor/`, THE APM CLI SHALL classify the ability target as cursor platform
4. WHEN a target path starts with `.kiro/`, THE APM CLI SHALL classify the ability target as kiro platform
5. IF a target path does not start with `.cursor/` or `.kiro/`, THEN THE APM CLI SHALL report an error indicating an unrecognized platform prefix and skip that target entry

### Requirement 4: Skill 目录级安装

**User Story:** As a developer, I want skill installation to handle directory-level duplication correctly, so that skills with multiple files and subdirectories are installed as complete directory copies.

#### Acceptance Criteria

1. WHEN a skill target path in abilities.yaml ends with `/`, THE Installer SHALL treat the ability as a directory-level skill and resolve the source from the corresponding directory at the package root
2. WHEN installing a directory-level skill, THE Installer SHALL recursively copy all files and subdirectories from the source directory to the target path, preserving the relative directory structure
3. IF the source directory for a directory-level skill does not exist, THEN THE Installer SHALL report a failure message indicating the missing source path and skip that skill without aborting the remaining installations
4. WHEN checking a directory-level skill, THE CheckService SHALL compare the content of every file in the installed target directory against the corresponding source file using byte-for-byte content comparison, and report the skill as "modified" if any file differs or is missing
5. WHEN checking a directory-level skill, IF the target directory contains files that do not exist in the source directory, THEN THE CheckService SHALL report those files separately as "extra files" without affecting the modified/unchanged determination of the skill
6. WHEN a directory-level skill has both cursor and kiro targets, THE Installer SHALL produce byte-for-byte identical file contents and identical relative path structures under both target paths

### Requirement 5: Gitignore Marker Block 操作

**User Story:** As a developer, I want the gitignore command to directly operate on `.gitignore` marker blocks, so that managed ignore rules are maintained in-place without a separate template file.

#### Acceptance Criteria

1. THE GitignoreManager SHALL read marker definitions from the `gitignore` section of abilities.yaml, where each entry contains a `marker` name and a `content` field listing the ignore patterns for that marker block
2. WHEN installing a gitignore ability, THE GitignoreManager SHALL insert the marker block into the project `.gitignore` file using the delimiter format `## @apm:block ability=<marker> target=*` and `## @apm:end`, with the `content` lines from abilities.yaml placed between the delimiters
3. WHEN installing a gitignore ability whose marker block already exists in `.gitignore`, THE GitignoreManager SHALL replace the content between the existing delimiters with the current `content` from abilities.yaml, preserving the block's position in the file
4. WHEN uninstalling a gitignore ability, THE GitignoreManager SHALL remove the corresponding marker block including both delimiter lines and all content lines between them from the project `.gitignore` file
5. THE GitignoreManager SHALL NOT read gitignore content from a separate template file in `abilities/gitignore/`
6. IF the `.gitignore` file does not exist, THEN THE GitignoreManager SHALL create the file before inserting the marker block
7. IF the specified marker name does not exist in the `gitignore` section of abilities.yaml, THEN THE GitignoreManager SHALL report an error indicating the marker is undefined and perform no file modification

### Requirement 6: Scaffold 硬编码路径安装

**User Story:** As a developer, I want scaffold installation to read templates from hardcoded project root paths, so that scaffold files are maintained at their real locations without abilities.yaml registration.

#### Acceptance Criteria

1. THE ProjectInitializer SHALL read scaffold templates from the following hardcoded paths relative to the apm package root: AGENTS.md, CHANGELOG.md, docs/README.md, issues/README.md, docs/state/.gitkeep, docs/manual/.gitkeep, docs/notes/.gitkeep, docs/proposals/.gitkeep, docs/changes/.gitkeep
2. THE ProjectInitializer SHALL NOT read scaffold definitions from abilities.yaml
3. WHEN a scaffold target file already exists in the destination project, THE ProjectInitializer SHALL skip that file without overwriting and continue processing the remaining scaffold files
4. WHEN a scaffold target directory already exists in the destination project, THE ProjectInitializer SHALL skip directory creation without error and continue processing the remaining scaffold entries
5. IF a hardcoded scaffold source file is not found at the expected apm package root path, THEN THE ProjectInitializer SHALL raise an error indicating which source path is missing

### Requirement 7: 移除 Capture/Ingest 功能

**User Story:** As a developer, I want all capture and ingest code removed from the codebase, so that system complexity is reduced and no dead code remains.

#### Acceptance Criteria

1. THE APM codebase SHALL NOT contain the `src/Capture/` directory or any files within it
2. THE APM codebase SHALL NOT contain the following command files: `AgentCaptureCommand.php`, `CaptureCommand.php`, `IngestCaptureChangeCommand.php`, `RuleCaptureCommand.php`, `SkillCaptureCommand.php`
3. THE APM codebase SHALL NOT contain `src/Service/CaptureService.php`
4. THE ConsoleRegistration SHALL NOT register any capture or ingest commands, and its method signature SHALL NOT accept CaptureService or CaptureChangeIngestor parameters
5. THE APM test suite SHALL NOT contain tests for capture or ingest functionality, including test files: `CaptureServiceUnitTest.php`, `CaptureChangeIngestorTest.php`, `CaptureChangeSignerTest.php`, `CaptureChangeSchemaTest.php`, `CommandCheckCaptureTest.php`, `CaptureCommandBranchesTest.php`, and capture-related test methods in `TypedCaptureCheckCommandsTest.php`
6. THE APM CLI SHALL NOT expose any `capture` or `ingest` subcommands to the user
7. WHEN CaptureService is removed, THE Preset commands (PresetCreateCommand, PresetAddAbilityCommand, PresetRemoveAbilityCommand, PresetDeleteCommand) SHALL be refactored to remove their CaptureService dependency while preserving preset registry read/write functionality
8. THE APM codebase SHALL compile without errors and pass all remaining tests after capture/ingest removal is complete

### Requirement 8: State 文档 — CLI 命令体系

**User Story:** As a developer, I want a complete CLI commands reference document, so that all command signatures, parameters, behaviors, and error scenarios are documented in one place.

#### Acceptance Criteria

1. THE APM project SHALL contain a `docs/state/cli-commands.md` file
2. THE cli-commands.md SHALL document every command in the final registered set: `install`, `check`, `show`, `update`, `rule:install`, `rule:check`, `rule:uninstall`, `agent:install`, `agent:check`, `agent:uninstall`, `skill:install`, `skill:check`, `skill:uninstall`, `preset:create`, `preset:delete`, `preset:add-ability`, `preset:remove-ability`, `preset:uninstall`
3. FOR each command, THE cli-commands.md SHALL document the command name, all positional arguments with their types and whether they are required or optional, and all options with their types and default values
4. FOR each command, THE cli-commands.md SHALL document the observable behavior on success including filesystem side effects and output to the user
5. FOR each command, THE cli-commands.md SHALL document error scenarios including: invalid or missing arguments, referenced ability not found in abilities.yaml, target file or directory conflicts, and the resulting error indication shown to the user
6. THE cli-commands.md SHALL NOT document any capture or ingest commands

### Requirement 9: State 文档 — Abilities 数据模型

**User Story:** As a developer, I want a complete abilities model reference document, so that the abilities.yaml format, field constraints, and preset reference format are clearly specified.

#### Acceptance Criteria

1. THE APM project SHALL contain a `docs/state/abilities-model.md` file
2. THE abilities-model.md SHALL document the abilities.yaml format including version field and all sections (rules, agents, skills, gitignore, presets)
3. THE abilities-model.md SHALL document field constraints for each ability type including: which fields are required, the path identifier format (colon-separated namespace such as `git:branch-overview`), the valid target platform identifiers (`cursor`, `kiro`), and the target value format (relative file path for rules/agents, directory path ending with `/` for skills)
4. THE abilities-model.md SHALL document the preset reference format (`type:path` syntax such as `rule:git:branch-overview`, `skill:gitflow`, `agent:code-reviewer`) including the valid type prefixes and how each reference resolves to an ability entry in its corresponding section
5. THE abilities-model.md SHALL document the gitignore section entry structure including the `marker` field (used as the `@apm:block` identifier) and the `description` field
6. THE abilities-model.md SHALL document the structural difference between ability types: rules and agents use file path targets, while skills use directory path targets (ending with `/`) indicating directory-level installation

### Requirement 10: State 文档 — 安装行为

**User Story:** As a developer, I want a complete install behavior reference document, so that install, check, and uninstall logic including boundary conditions and diff determination are clearly specified.

#### Acceptance Criteria

1. THE APM project SHALL contain a `docs/state/install-behavior.md` file
2. THE install-behavior.md SHALL document the install logic for each ability type (rule, agent, skill, gitignore) including source resolution from abilities.yaml targets, copy mechanism (single-file copy for rules and agents, directory-tree copy for skills, marker block insertion for gitignore), and success/failure output per operation
3. THE install-behavior.md SHALL document the check logic including the four diff status categories (unchanged, modified, missing, unknown), the content-based comparison method between source and installed files, and the baseline resolution mechanism
4. THE install-behavior.md SHALL document the uninstall logic for each ability type including file/directory removal for rules, agents, and skills, and marker block removal for gitignore
5. THE install-behavior.md SHALL document boundary conditions: missing source file (ability referenced in abilities.yaml but file absent), missing target directory (target platform directory not yet created), permission errors during copy or delete, and partial install state (some files in a multi-file skill copied successfully while others failed)
6. THE install-behavior.md SHALL document the force flag semantics including: when force is required (target file exists with local modifications detected by check), what force overrides (allows overwriting locally modified targets), and the default behavior without force when local modifications exist

### Requirement 11: State 文档 — Gitignore 管理

**User Story:** As a developer, I want a complete gitignore management reference document, so that the marker block format and operation rules are clearly specified.

#### Acceptance Criteria

1. THE APM project SHALL contain a `docs/state/gitignore.md` file
2. THE gitignore.md SHALL specify the template block delimiter syntax as `## @apm:block ability=<ability-key> target=<target>` for the start delimiter and `## @apm:end` for the end delimiter, including the allowed values for `ability` (e.g., `skill:<name>`, `rule:<name>`, or plain name) and `target` (platform name or `*` for all platforms)
3. THE gitignore.md SHALL specify the managed section delimiters as `# BEGIN apm-managed-gitignore v1` and `# END apm-managed-gitignore v1`, and describe how the managed section is inserted (appended if absent) or replaced (in-place if already present) in the project `.gitignore` file
4. THE gitignore.md SHALL document the rules for template block rendering: how matching blocks are selected by ability key and target, how matched patterns are collected, and that empty lines within blocks are excluded from output
5. THE gitignore.md SHALL document the behavior when multiple template blocks exist in the same template file, specifying that each block is evaluated independently and matched patterns are concatenated with a blank line separator into a single managed section
6. IF a template block is missing its `## @apm:end` closing delimiter, THEN THE gitignore.md SHALL document that the system raises a RuntimeException indicating an unclosed block

### Requirement 12: State 文档 — Architecture 更新

**User Story:** As a developer, I want the architecture document updated to reflect the new module responsibilities and data flow after code adaptation, so that the system documentation matches the actual implementation.

#### Acceptance Criteria

1. THE `docs/state/architecture.md` SHALL document the following active modules and their single-sentence responsibilities: Command/ (CLI commands), Config/ (AppConfig, PackagePaths), Core/ (Application, ConsoleRegistration), Service/ (Installer, CheckService, AbilityDiffService, DirectoryMirrorService, GitIgnoreTemplateService, PresetRegistry, ProjectInitializer, ComposerBaselineResolver, KnowledgeBaseUpdater)
2. THE architecture.md SHALL document the data flow showing the input source (abilities.yaml), the processing services (Installer, CheckService), and the output destinations (target platform paths), including the direction of data movement between each stage
3. THE architecture.md SHALL document the scaffold initialization flow (ProjectInitializer creating initial project structure) in a separate section from the ability installation flow (Installer copying abilities to target paths)
4. THE architecture.md SHALL NOT reference capture, ingest, CaptureService, or suffix-based parsing as active components
5. WHEN the architecture.md is updated, THE architecture.md SHALL retain the existing target platform mapping table (Cursor and Kiro path conventions)

---

## Socratic Review

**每条 requirement 是否都在描述外部可观察的行为？**
Req 1–6 描述的是文件系统状态和 CLI 可观察行为。Req 7 列出具体文件名用于验证删除完整性，属于可观察的"不存在"断言。Req 8–12 描述文档内容要求，属于可交付物的验收标准。整体合规。

**是否有遗漏的场景？**
- Req 2 未覆盖 abilities.yaml 本身格式校验失败（如 YAML 语法错误、缺少 version 字段）时的行为。但这属于通用 CLI 健壮性，可在 design 阶段补充。
- Req 5 引入了 `content` 字段作为 gitignore 模式的来源，但当前 abilities.yaml 实际结构中 gitignore 条目仅有 `marker` + `description`，无 `content` 字段。需确认：gitignore 的实际 pattern 内容是存储在 abilities.yaml 中还是存储在独立模板文件中。此为 design 阶段需澄清的决策点。
- Req 4 未说明 skill 目录下出现额外文件（目标目录有源中不存在的文件）时 check 的行为。

**各 requirement 之间是否存在矛盾或重叠？**
Req 2 和 Req 3 有部分重叠（都涉及"不使用后缀解析"），但 Req 2 聚焦 Installer/CheckService 的数据源，Req 3 聚焦平台判定逻辑，角度不同，可接受。

**是否有隐含的前置假设没有显式列出？**
- 假设 abilities.yaml 始终存在于 apm package root（Req 2 AC1 说"project root directory"，但 install 命令的 working directory 是目标项目，而 abilities.yaml 在 apm package root）。需在 design 阶段明确 source resolution 的基准路径。
- 假设 `.cursor/` 和 `.kiro/` 目录已存在或由 Installer 自动创建。

**与 proposal 的 scope / non-goals 是否一致？**
一致。Non-goals（跨项目分发、版本管理、capture/ingest）均未出现在 requirements 中。

**scope 边界是否清晰？**
Introduction 已补充 Non-scope 说明，边界清晰。


---

## Gatekeep Log

**校验时间**: 2025-01-27
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 Introduction 的 Non-scope 说明（原文未明确列出不涉及的内容）
- [格式] 补充各 section 之间的 `---` 分隔线
- [术语] 补充 Glossary 中缺失的 3 个 AC Subject：GitignoreManager、ProjectInitializer、ConsoleRegistration
- [结构] 补充 `## Socratic Review` section

### 合规检查
- [x] 无 TBD / TODO / 占位符
- [x] 无空 section 或不完整列表
- [x] 内部引用一致
- [x] 无 markdown 格式错误
- [x] 一级标题正确
- [x] Introduction 描述 feature 范围并明确 Non-scope
- [x] Glossary 非空且术语格式正确
- [x] Requirements section 包含 12 条 requirement
- [x] 各 section 使用 `---` 分隔
- [x] AC 使用 EARS 语体（THE/WHEN/IF）
- [x] AC 编号连续无跳号
- [x] Glossary 术语在 AC 中被使用（无孤立术语）
- [x] AC 中使用的 Subject 在 Glossary 中有定义
- [x] Goal CR 决策已在 requirements 中体现
- [x] 完成标准充分
- [x] 可 design 性充分
- [○] Socratic Review 发现 abilities.yaml source resolution 基准路径需 design 阶段澄清

### Clarification Round

以下问题面向 design 阶段，需用户在进入 design 前做出决策：

**CR-1: Gitignore pattern 内容的存储位置**

Req 5 AC1 要求 gitignore 条目包含 `content` 字段列出 ignore patterns，但当前 abilities.yaml 中 gitignore 条目仅有 `marker` + `description`。pattern 内容应存储在哪里？

- A) 直接内联在 abilities.yaml 的 `content` 字段中（多行 YAML 字符串）
- B) 存储在独立模板文件中（如 `.apm/gitignore/<marker>.gitignore`），abilities.yaml 仅引用 marker 名
- C) 存储在项目根目录的约定路径（如 `gitignore/<marker>.gitignore`），abilities.yaml 增加 `source` 字段指向该文件

**A:** A — 直接内联在 abilities.yaml 的 `content` 字段中

**CR-2: abilities.yaml 的 source resolution 基准路径**

Req 2 AC1 说 Installer 从"project root directory (the working directory where the command is executed)"读取 abilities.yaml。但 install 命令的 working directory 是目标项目，而 ability 源文件在 apm package root。source resolution 应如何确定基准？

- A) abilities.yaml 始终从 apm package root 读取（通过 Composer autoload 或 vendor path 定位），target paths 相对于目标项目 root 解析
- B) abilities.yaml 从当前 working directory 读取，source paths 通过 Composer vendor 路径回溯到 apm package root
- C) 两份 abilities.yaml：apm package 内一份作为 source registry，目标项目可选一份作为 local override

**A:** A — abilities.yaml 始终从 apm package root 读取，target paths 相对于目标项目 root 解析

**CR-3: Skill check 时目标目录存在额外文件的处理**

Req 4 AC4 规定 check 比较目标目录中每个文件与源文件的内容，但未说明目标目录中存在源中没有的额外文件时的行为。

- A) 忽略额外文件，仅比较源中存在的文件（宽松模式）
- B) 将额外文件视为 "modified" 状态的一部分（严格模式，目标应与源完全一致）
- C) 单独报告为 "extra files" 状态，不影响 modified/unchanged 判定

**A:** C — 单独报告为 "extra files" 状态，不影响 modified/unchanged 判定

**CR-4: Installer 遇到目标平台目录不存在时的行为**

多条 requirement 假设 `.cursor/` 和 `.kiro/` 目录已存在。当目标项目尚未创建这些目录时，Installer 应如何处理？

- A) 自动创建缺失的目录层级（mkdir -p 语义），静默处理
- B) 自动创建但输出提示信息告知用户
- C) 报错并跳过该 target，要求用户先手动创建目录

**A:** B — 自动创建但输出提示信息告知用户
