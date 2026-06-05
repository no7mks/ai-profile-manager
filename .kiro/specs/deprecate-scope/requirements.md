# Requirements Document

## Introduction

本文档定义 apm CLI 工具废弃 `--scope` 参数、`user` scope 概念及 `global-setup` 命令的需求。废弃后逻辑上仅保留 project scope，简化用户心智模型与代码路径。`bootstrap` 命令扩展为统一入口，承接原 `global-setup` 的 ability 安装职责。

### Non-scope

- 不引入新的 scope 概念（如 workspace scope）。
- 不修改 `/apm init` Agent 端交互逻辑（仅更新其参考文档）。
- 不提供向后兼容 shim（`--scope project` 不静默通过）。
- 不自动清理用户主目录下已安装的 user scope 文件。

---

## Glossary

- **CLI**：apm 命令行界面程序，用户通过终端与之交互。
- **Scope_Parameter**：CLI 命令的 `--scope` 选项，原用于指定操作的部署范围。
- **Project_Scope**：以当前工作目录为根的部署范围，ability 安装到项目工作区内。
- **Deprecated_Error**：CLI 输出的废弃错误消息，通知用户某功能已移除，并给出替代方案。
- **Ability**：在注册表中登记、可安装且可卸载的条目（skill / rule / agent / hook / gitignore / prompt）。
- **Bootstrap_Command**：`apm bootstrap` 命令，执行项目骨架搭建与 ability 批量安装。
- **Scaffold**：Bootstrap_Command 的第一阶段，复制项目骨架文件并创建目录结构。
- **Bootstrap_Includes**：Ability_Registry 中 `bootstrap` section 的 `includes` 列表，定义 Bootstrap_Command 需安装的 ability 清单。
- **Ability_Registry**：`abilities.yaml` 文件，作为 ability 元数据的单一事实来源。
- **Validation_Error**：Ability_Registry 解析时检测到违规字段后抛出的异常，导致程序终止。
- **Global_Setup_Command**：`apm global-setup` 命令，原用于将 ability 安装到用户主目录。
- **Show_Command**：`apm show` 命令，展示 ability 安装状态。
- **Update_Command**：`apm update` 命令，检测并更新已安装 ability 与 baseline 的差异。
- **Cleanup_Command**：`apm cleanup` 命令，卸载 project scope 已安装的 ability。
- **Install_Command**：`apm install` 命令（含别名 `add`），安装 preset 中的 ability。
- **Typed_Install_Command**：`apm skill:install`、`apm rule:install`、`apm agent:install` 等按类型安装 ability 的命令。
- **Typed_Uninstall_Command**：`apm skill:uninstall`、`apm rule:uninstall`、`apm agent:uninstall`、`apm preset:uninstall` 等按类型卸载 ability 的命令。
- **Force_Flag**：`--force` 选项，指示命令在幂等检查时覆盖已有文件。
- **Exit_Failure**：CLI 以非零退出码终止，表示操作失败。

---

## Requirements

### Requirement 1: Scope_Parameter 废弃

**User Story:** As a CLI user, I want the CLI to clearly reject the deprecated `--scope` option, so that I understand all operations now target project scope only.

#### Acceptance Criteria

1. WHEN a user passes Scope_Parameter with any value to Install_Command, THEN THE CLI SHALL output a Deprecated_Error to stderr indicating the option has been removed and all operations now target project scope only, produce no other output, and exit with Exit_Failure code 1.
2. WHEN a user passes Scope_Parameter with any value to Show_Command, THEN THE CLI SHALL output a Deprecated_Error to stderr indicating the option has been removed and all operations now target project scope only, produce no other output, and exit with Exit_Failure code 1.
3. WHEN a user passes Scope_Parameter with any value to any Typed_Install_Command, THEN THE CLI SHALL output a Deprecated_Error to stderr indicating the option has been removed and all operations now target project scope only, produce no other output, and exit with Exit_Failure code 1.
4. WHEN a user passes Scope_Parameter with any value to any Typed_Uninstall_Command, THEN THE CLI SHALL output a Deprecated_Error to stderr indicating the option has been removed and all operations now target project scope only, produce no other output, and exit with Exit_Failure code 1.
5. IF Scope_Parameter is passed without a value (e.g., `--scope` with no argument), THEN THE CLI SHALL output the same Deprecated_Error to stderr and exit with Exit_Failure code 1.
6. IF Scope_Parameter is not passed to any of the above commands, THEN THE CLI SHALL execute the command targeting Project_Scope, processing the request as if scope were never a supported option.
7. WHEN a user passes Scope_Parameter set to "project" to any of the above commands, THEN THE CLI SHALL output the same Deprecated_Error and exit with Exit_Failure code 1, with no special handling for the previously-default value.

---

### Requirement 2: Global_Setup_Command 废弃

**User Story:** As a CLI user, I want the CLI to reject the deprecated `global-setup` command and guide me to the replacement, so that I can adopt the new workflow.

#### Acceptance Criteria

1. WHEN a user executes Global_Setup_Command, THE CLI SHALL output a Deprecated_Error that names `apm bootstrap` as the replacement and exit with Exit_Failure.
2. WHEN a user executes Global_Setup_Command, THE CLI SHALL not perform any file installation operations.
3. WHEN a user executes Global_Setup_Command with any combination of flags or arguments (including `--force` and `--target`), THE CLI SHALL still output the Deprecated_Error and exit with Exit_Failure without processing the flags.

---

### Requirement 3: Bootstrap_Command 扩展

**User Story:** As a CLI user, I want `apm bootstrap` to set up both project scaffold and essential abilities in one step, so that project initialization is simpler and faster.

#### Acceptance Criteria

1. WHEN a user executes Bootstrap_Command, THE CLI SHALL first perform Scaffold operations (copy skeleton files, create directory structure) and complete all scaffold file writes before proceeding to ability installation.
2. WHEN Scaffold completes successfully, THE CLI SHALL read Bootstrap_Includes from the `bootstrap.includes` section of Ability_Registry and install each listed Ability to Project_Scope using the same typed-install mechanism as `apm add`.
3. WHILE an Ability in Bootstrap_Includes is already installed and Force_Flag is not provided, THE CLI SHALL skip that Ability and output a line indicating the Ability was skipped (no error, exit code unaffected).
4. WHILE an Ability in Bootstrap_Includes is already installed and Force_Flag is provided, THE CLI SHALL overwrite the installed Ability files from baseline.
5. IF one or more Ability installations from Bootstrap_Includes fail, THEN THE CLI SHALL mark each failed item with `[fail]` in the output, retain all Scaffold files already written, and exit with code 1.
6. IF one Ability installation fails, THEN THE CLI SHALL continue attempting all remaining Ability installations in Bootstrap_Includes before exiting.
7. IF the `bootstrap.includes` section is absent or empty in Ability_Registry, THEN THE CLI SHALL complete successfully after Scaffold without attempting any Ability installations.
8. IF Scaffold fails (e.g., existing scaffold files without Force_Flag), THEN THE CLI SHALL exit with code 1 without attempting any Ability installations from Bootstrap_Includes.

---

### Requirement 4: Ability_Registry 遗留字段验证

**User Story:** As a CLI user, I want the registry to reject legacy fields immediately, so that I am prompted to update my configuration file before encountering unexpected behavior.

#### Acceptance Criteria

1. WHEN Ability_Registry is parsed and an ability entry contains a `scopes` field, THEN THE CLI SHALL raise a Validation_Error whose message identifies the offending entry and indicates that the `scopes` field is no longer supported, and SHALL terminate with a non-zero exit code.
2. WHEN Ability_Registry is parsed and a top-level `global-setup` key is present, THEN THE CLI SHALL raise a Validation_Error whose message indicates that the `global-setup` key is no longer supported and must be renamed to `bootstrap`, and SHALL terminate with a non-zero exit code.
3. THE CLI SHALL stop validation at the first encountered legacy-field violation and terminate without processing further entries or sections (fail-fast); if both a `scopes` field and a `global-setup` key exist, whichever is encountered first during top-down parsing SHALL be the single reported violation.
4. IF a Validation_Error is raised due to a legacy field, THEN THE CLI SHALL NOT execute any ability installation, resolution, or command logic beyond the parse phase.

---

### Requirement 5: Ability_Registry bootstrap section

**User Story:** As a CLI user, I want the registry's `bootstrap` section to replace the old `global-setup` section, so that configuration aligns with the CLI command name.

#### Acceptance Criteria

1. WHEN Ability_Registry parses an `abilities.yaml` containing a top-level `bootstrap` key with an `includes` list, THE Ability_Registry SHALL return each entry as a typed ability reference with `type` and `path` fields, using the `type:path` format (e.g., `skill:apm`, `rule:git:git-conventions` where the first colon separates type from path).
2. WHEN Bootstrap_Command reads Bootstrap_Includes, THE CLI SHALL look up each ability reference from `bootstrap.includes` in Ability_Registry and install the resolved abilities to Project_Scope.
3. IF the `bootstrap` key is absent from `abilities.yaml` or its `includes` sub-key is missing, THEN THE Ability_Registry SHALL return an empty list without raising an error.
4. IF a `bootstrap.includes` entry does not match a registered ability in Ability_Registry (no entry found for the given type and path), THEN THE CLI SHALL report an error message indicating which ability reference could not be resolved and skip that entry.
5. IF a `bootstrap.includes` entry is not a string or does not contain a colon separator, THEN THE Ability_Registry SHALL skip that entry silently and continue processing the remaining entries.

---

### Requirement 6: Show_Command 简化

**User Story:** As a CLI user, I want `apm show` to display only project scope status, so that output is clear and free of obsolete scope merge information.

#### Acceptance Criteria

1. THE Show_Command SHALL evaluate installation status exclusively against Project_Scope and format each ability line as `{type}:{name}  {status}  {targets}` where status is one of "installed", "installed with local change", or "not installed".
2. THE Show_Command SHALL not include scope labels (e.g., "(user)", "(project)", "(user+project)"), merged scope indicators, or dual-scope warnings (e.g., "[warn] installed in both user and project") in any output line.
3. WHEN an ability registered in Ability_Registry has no installation present in Project_Scope, THE Show_Command SHALL display that ability with status "not installed" and omit the scope label column.

---

### Requirement 7: Update_Command 简化

**User Story:** As a CLI user, I want `apm update` to only process project scope abilities, so that the update scope matches the simplified model.

#### Acceptance Criteria

1. THE Update_Command SHALL enumerate and compare only abilities installed in Project_Scope against the baseline.
2. THE Update_Command SHALL not enumerate or compare abilities in user scope.
3. WHEN Update_Command reports changed abilities with Force_Flag, THE CLI SHALL overwrite only Project_Scope installed files from baseline and SHALL NOT attempt to overwrite any user scope files.

---

### Requirement 8: Cleanup_Command 文案清理

**User Story:** As a CLI user, I want cleanup output to omit references to the removed user scope, so that messaging is consistent with the current model.

#### Acceptance Criteria

1. WHEN Cleanup_Command completes successfully, THE Cleanup_Command SHALL display a success message that does not contain the substrings "user-scope", "user scope", "global-setup", or "global setup".
2. WHEN Cleanup_Command completes successfully, THE Cleanup_Command SHALL include guidance text directing the user to run `/apm init` for reinstallation.
3. IF Cleanup_Command encounters uninstallation failures, THEN THE Cleanup_Command SHALL exit with Exit_Failure and output per-item failure lines without referencing user-scope or global-setup.

---

### Requirement 9: 文档同步

**User Story:** As a developer, I want all affected documentation to reflect the scope deprecation, so that docs remain the single source of truth.

#### Acceptance Criteria

1. WHEN code changes for scope deprecation are complete, THE documentation SHALL remove `--scope` parameter (including `[--scope project|user]`) from all command signatures and error condition tables in `docs/state/cli-commands.md`, and remove `--scope` usage examples from `docs/manual/usage.md`.
2. WHEN code changes for scope deprecation are complete, THE documentation SHALL mark the `global-setup` command section in `docs/state/cli-commands.md` as removed, state that `bootstrap` is the replacement for first-install, and remove the `global-setup` section from `docs/manual/usage.md` (converting the three-phase first-install to a two-phase flow: `apm bootstrap` + `/apm init`).
3. WHEN code changes for scope deprecation are complete, THE documentation SHALL remove the `scopes` field row from the Ability Entry format table in `docs/state/abilities-model.md`, remove the `global-setup` section (including `global-setup.includes` description), and remove the Project-Only concept (ScopeGuard references, `projectOnlyPaths()` listing).
4. WHEN code changes for scope deprecation are complete, THE documentation SHALL rewrite `docs/state/deploy-scope.md` to describe project-scope-only path resolution, removing User Root resolution, User Scope rows, ScopeGuard section, and `InvalidScopeException::projectOnlyInUserScope` factory method documentation.
5. WHEN code changes for scope deprecation are complete, THE documentation SHALL remove all `global-setup` references and `--scope` parameters from `docs/state/install-behavior.md` (Deploy Scope subsection: retain only project scope path resolution) and from both `.cursor/skills/apm/references/init-workflow.md` and `.kiro/skills/apm/references/init-workflow.md` (Bootstrap Phase: remove prohibition about Global Setup List; update first-install description to two-phase).
6. WHEN documentation updates for criteria 1–5 are complete, THE documentation SHALL pass a text search verification: a case-insensitive search for `--scope`, `global-setup`, `user scope`, and `ScopeGuard` across all 7 affected files SHALL return zero matches (excluding lines that explicitly mark these terms as removed/deprecated).


---

## Clarification Round

> 以下问题聚焦 requirements → design 衔接。

### CR-1: Deprecated_Error 消息的统一性与差异化

**A: A) 统一模板** — 所有命令共用一个 `deprecated(featureName, replacement)` 工厂方法，消息格式完全一致，仅参数不同。

### CR-2: Ability_Registry 验证的触发时机

**A: A) IO 层立即验证** — 在 `abilities.yaml` 文件读取后立即验证 schema，任何命令启动时都经过此校验。

### CR-3: Bootstrap_Command 的幂等判定粒度

**A: B) 内容 hash 比对** — 文件存在且内容 hash 与 baseline 一致才视为已安装（内容变更 = 未安装/需更新）。

### CR-4: Show_Command 状态格式与 installed with local change 的判定

**A: A) baseline 源文件 diff** — 与 Ability_Registry 中记录的 baseline（源文件）逐文件 diff 比对。
