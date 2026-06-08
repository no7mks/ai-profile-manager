# Requirements Document

## Introduction

Release 0.10.0 包含两项主要交付：

1. **Cursor Skill 拆分**：将已废弃的 `plan:quick-plan-conventions` rule 替换为两个独立 Cursor Skill（`quick-plan` 和 `build-plan`），更新 Ability_Registry 以反映拆分结果。
2. **Release 收口**：对已合并的 PRP-003（Deprecate Scope）和 Skill 拆分进行版本号同步、CHANGELOG 收敛、文档更新和 tag 标记。

### Non-scope

- Kiro 侧 `quick-plan` / `build-plan` Skill 内容变更（已独立维护）
- 旧 rule 的自动迁移逻辑（用户手动清理）
- 新 CLI 命令或新 scope 概念

---

## Glossary

- **Ability_Registry**: `abilities.yaml` 文件，作为所有 ability（skill / rule / agent / hook）的注册表与单一事实来源
- **Bootstrap_Include_List**: Ability_Registry 中 `bootstrap.includes` 列表，定义 `apm bootstrap` 自动安装的条目集合
- **Cursor_Skill**: 安装到 `.cursor/skills/<name>/` 目录的 ability 条目，包含 SKILL.md 及可选 references 子目录
- **Quick_Plan_Skill**: 在 Cursor Plan mode 下运行的 Skill，负责收集需求并生成 plan.md
- **Build_Plan_Skill**: 在 Cursor Agent mode 下运行的 Skill，负责读取已确认的 plan.md 并执行实施
- **Old_Rule**: Ability_Registry 中 `path: plan:quick-plan-conventions` 的 rule 条目及其对应源文件
- **Plan_Output_Path**: 两个 Skill 共享的 plan.md 路径约定 `.cursor/specs/<slug>-plan.md`
- **Version_Source**: 项目中记录版本号的位置（`composer.json` 的 `version` 字段和 `src/Core/Application.php` 的 SymfonyApplication 构造参数）
- **CHANGELOG**: 面向用户的版本摘要文件 `/CHANGELOG.md`

---

## Requirements

### Requirement 1: Cursor quick-plan Skill 注册

**User Story:** As a developer, I want the quick-plan Skill registered as a Cursor Skill in the Ability_Registry, so that it can be installed and managed via apm.

#### Acceptance Criteria

1. THE Ability_Registry SHALL contain a skills section entry with path `quick-plan` and a targets mapping that includes `cursor: .cursor/skills/quick-plan/` and does not include a `kiro` key
2. THE Ability_Registry skills entry for `quick-plan` SHALL include a description field containing at least 5 characters that summarizes the skill's purpose
3. THE Ability_Registry skills entry for `quick-plan` SHALL contain all three required fields: `path`, `description`, and `targets`

---

### Requirement 2: Cursor build-plan Skill 注册

**User Story:** As a developer, I want the build-plan Skill registered as a Cursor Skill in the Ability_Registry, so that it can be installed and managed via apm.

#### Acceptance Criteria

1. THE Ability_Registry SHALL contain a skills section entry with path `build-plan` and a targets mapping that includes `cursor: .cursor/skills/build-plan/` and does not include a `kiro` key
2. THE Ability_Registry skills entry for `build-plan` SHALL include a description field containing at least 5 characters that summarizes the skill's purpose
3. THE Ability_Registry skills entry for `build-plan` SHALL contain all three required fields: `path`, `description`, and `targets`

---

### Requirement 3: Old Rule 删除

**User Story:** As a developer, I want the deprecated quick-plan-conventions rule removed from the Ability_Registry and its source package, so that stale configuration is eliminated.

#### Acceptance Criteria

1. THE Ability_Registry SHALL NOT contain a rules section entry with path `plan:quick-plan-conventions`
2. THE Bootstrap_Include_List SHALL NOT contain the entry `rule:plan:quick-plan-conventions`
3. THE abilities source package SHALL NOT contain the file `.cursor/rules/plan/quick-plan-conventions.mdc`
4. THE Ability_Registry presets section SHALL NOT contain any includes entry referencing `rule:plan:quick-plan-conventions`
5. WHEN the Old_Rule source file is deleted, THE cursor-scope rule SHALL NOT contain instructions or path references directing the agent to load `plan/quick-plan-conventions.mdc`

---

### Requirement 4: Bootstrap Include List 更新

**User Story:** As a developer, I want the new Cursor Skills included in the Bootstrap_Include_List, so that `apm bootstrap` automatically installs them.

#### Acceptance Criteria

1. THE Bootstrap_Include_List SHALL contain the entry `skill:quick-plan`
2. THE Bootstrap_Include_List SHALL contain the entry `skill:build-plan`
3. WHEN `apm bootstrap` is executed with target `cursor`, THE bootstrap process SHALL install Quick_Plan_Skill and Build_Plan_Skill such that their respective Cursor target directories (`.cursor/skills/quick-plan/` and `.cursor/skills/build-plan/`) each contain a `SKILL.md` file
4. IF `apm bootstrap` is executed with a target that does not include `cursor`, THEN THE bootstrap process SHALL NOT install Quick_Plan_Skill or Build_Plan_Skill

---

### Requirement 5: Quick Plan Skill 内容结构

**User Story:** As a developer, I want the quick-plan Cursor Skill to contain proper structure and mode declaration, so that agents can correctly identify and activate it.

#### Acceptance Criteria

1. THE Quick_Plan_Skill directory (`.cursor/skills/quick-plan/`) SHALL contain a `SKILL.md` file
2. THE Quick_Plan_Skill SKILL.md SHALL contain a mode declaration section stating that the Skill requires Cursor Plan mode, and SHALL instruct the agent to refuse execution if the current mode is not Plan mode
3. THE Quick_Plan_Skill SKILL.md SHALL include a trigger conditions section listing the user phrases or contexts that activate the skill, and an operational workflow section defining the ordered steps the agent must follow
4. THE Quick_Plan_Skill SHALL instruct the agent to produce file output only to Plan_Output_Path (`.cursor/specs/<slug>-plan.md`) and SHALL explicitly prohibit creating, modifying, or deleting any other project files
5. THE Quick_Plan_Skill SKILL.md SHALL contain a plan template section that defines the markdown structure for generated plan files, including at minimum: Goal, Scope, Plan (with checkboxes), and Validation sections

---

### Requirement 6: Build Plan Skill 内容结构

**User Story:** As a developer, I want the build-plan Cursor Skill to contain proper structure and mode declaration, so that agents can correctly identify and activate it.

#### Acceptance Criteria

1. THE Build_Plan_Skill directory (`.cursor/skills/build-plan/`) SHALL contain a `SKILL.md` file
2. THE Build_Plan_Skill SKILL.md SHALL declare that the Skill requires Cursor Agent mode and that the agent must refuse execution if the current mode is not Agent mode
3. THE Build_Plan_Skill SKILL.md SHALL contain a workflow section defining operational steps including: locating plan.md from Plan_Output_Path (`.cursor/specs/<slug>-plan.md`), reviewing plan content, executing implementation with file read/write and command execution capabilities, updating step progress, and reporting results
4. IF no plan.md file exists at Plan_Output_Path when Build_Plan_Skill is activated, THEN THE Build_Plan_Skill SHALL instruct the agent to inform the user and suggest using quick-plan to create a plan first

---

### Requirement 7: 版本号同步

**User Story:** As a maintainer, I want all Version_Source locations updated to 0.10.0, so that the release is consistently identified.

#### Acceptance Criteria

1. WHEN the release is prepared, THE Version_Source `version` field in `composer.json` SHALL contain the exact string `0.10.0`
2. WHEN the release is prepared, THE Version_Source version argument in the `new SymfonyApplication('apm', ...)` call in `src/Core/Application.php` SHALL contain the exact string `0.10.0`
3. THE version string in `composer.json` and the version string in `src/Core/Application.php` SHALL be identical

---

### Requirement 8: CHANGELOG 收敛

**User Story:** As a user, I want a CHANGELOG entry for 0.10.0 that summarizes all included changes, so that I can understand what the release contains.

#### Acceptance Criteria

1. THE CHANGELOG SHALL contain a section headed `## [0.10.0] - <YYYY-MM-DD>` where the date is the actual release date in ISO 8601 format
2. THE CHANGELOG 0.10.0 section SHALL list under appropriate category headers the PRP-003 Deprecate Scope changes including: deprecation of `--scope` parameter, removal of user scope concept, deprecation of `global-setup` command, bootstrap unifying scaffold and ability installation, and three-phase first-install reduced to two-phase
3. THE CHANGELOG 0.10.0 section SHALL list under appropriate category headers the Cursor Skill split: `quick-plan-conventions` rule replaced by two independent skills (`quick-plan` and `build-plan`)
4. THE CHANGELOG 0.10.0 section SHALL follow the Keep a Changelog format consistent with existing entries, using the category headers (Added, Changed, Breaking, Removed, Fixed) as applicable

---

### Requirement 9: Release Tag

**User Story:** As a maintainer, I want a git tag created for the release, so that the release point is permanently recorded in version control.

#### Acceptance Criteria

1. WHEN all release closure tasks are complete, THE repository SHALL have an annotated tag named `v0.10.0` pointing to the merge commit on the master branch
2. THE annotated tag `v0.10.0` SHALL contain a tag message indicating the release version
3. WHEN the tag is created, THE tag SHALL be pushed to the remote repository together with the master branch

---

## Requirement Discussion

| # | 问题 | 选项 | 回答 |
|---|------|------|------|
| RD-1 | R5/R6 规定了 SKILL.md 必须包含的 section（mode 声明、触发条件、工作流等），但未指定各 section 的命名与排列顺序。Design 阶段是否需要规定固定的 heading 名称和排列顺序？ | A) 由 design 规定固定 heading 名称和顺序 B) 仅规定必须存在的内容块 C) 参照现有 Skill 结构作为模板 | **C** — 参照已有 Skill（如 `spec-planning`）的 SKILL.md 结构，保持项目内一致性 |
| RD-2 | quick-plan 和 build-plan 是否需要 `references/` 子目录？ | A) 均不需要 B) 均需要 C) quick-plan 需要，build-plan 不需要 | **条件判断** — SKILL.md 超过 100 行则拆出 references 子目录，否则单文件自包含 |
| RD-3 | cursor-scope rule 中对旧 rule 引用的处理范围？ | A) 仅删除指向旧 rule 的引用 B) 删除并增加新 Skill 引用说明 C) 不主动修改 | **A** — 仅删除明确指向 `plan/quick-plan-conventions.mdc` 的路径引用和加载指令 |
