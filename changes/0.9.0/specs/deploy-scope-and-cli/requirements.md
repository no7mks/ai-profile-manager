# Requirements Document

## Introduction

本文档定义 `deploy-scope-and-cli` feature 的可验收需求：在单一 feature 发布中，按 **Phase 1 → Phase 2** 顺序交付 ISS-31532、ISS-01592 与 PRP-002（Deploy Scope and CLI Onboarding）。

- **Phase 1**：修复 macOS XDG 环境下 **Baseline** 无法解析，恢复 `check`/`show` 的 diff 能力。
- **Phase 2**：修复 **Installation Fallback** 对 category rule 的误判，并实现 user/project **Deploy Scope**、**Global Setup**、**Bootstrap**、**Cleanup**、重写 **Show Command** / **Update Command**、breaking CLI 变更及文档同步；**Platform Verification** 为 finish 前阻塞项。

### Non-scope

- 省略类型的 `apm add <name>`（须显式 `preset|skill|rule|…`）
- 自动迁移既有 project 安装到 user scope 或反向
- **Global Setup** 安装 preset；**Cleanup** 修改 scaffold、`PROJECT.md`、`docs/state|manual` 正文、user 目录
- capture/ingest 功能
- 为 ISS-31532 / ISS-01592 单独发 patch 或 hotfix
- Phase 2 取消 baseline 不可用时的 **Installation Fallback**（默认保留并修复）

---

## Glossary

- **APM CLI**: 用户通过 Composer 安装并执行的 `apm` 命令行工具
- **Ability**: 在 **Ability Registry** 登记、可安装且可卸载的条目（skill / rule / agent / hook / gitignore / prompt）；preset 名不是 Ability
- **Ability Registry**: 包内 `abilities.yaml`，声明 ability 元数据、**Deploy Scope** 与 **Global Setup List**
- **Conventional Ability**: **Show Command** 与 **Update Command** 操作的 ability 类型（skill / rule / agent / hook / gitignore / prompt），不含 preset 名与 scaffold
- **Baseline**: 用于与已安装内容 diff 的 global apm 包根路径
- **Baseline Resolver**: 解析 **Baseline** 安装路径的组件（环境变量、Composer 全局目录等）
- **Bootstrap**: 仅搭建项目 scaffold 的命令，不安装 ability 或 preset
- **Check Service**: 对已安装 **Conventional Ability** 执行状态检查的服务
- **Cleanup Command**: 仅卸载 **Project Scope** 已安装 ability 的命令
- **Deploy Scope**: 安装目标根——**Project Scope** 或 **User Scope**
- **Global APM Installation**: Composer global 安装的 apm，**Update Command** 的唯一合法执行环境
- **Global Setup**: 向 **User Scope** 安装 **Global Setup List** 的流程
- **Global Setup List**: `abilities.yaml` 顶层 `global-setup:` typed includes，仅供 **Global Setup Command** 使用
- **Global Setup Command**: `global-setup` CLI 命令
- **Installation Fallback**: **Show Command** 在无法自 **Check Service** 得到 installed/drift 结果时的磁盘探测备用逻辑
- **Platform Verification**: finish 前在真实 `~/.cursor` / `~/.kiro` 的阻塞验收
- **Preset**: **Ability Registry** `presets` 段中的 ability 集合
- **Project Scope**: **Deploy Scope** 为当前工作区根
- **Project-Only Ability**: 仅允许 **Project Scope** 的 ability（清单以 PRP-002 为准）
- **Show Command**: 列出 **Conventional Ability** 安装状态的命令
- **Target**: IDE 平台（cursor / kiro）
- **Update Command**: 对比 **Baseline** 并可选覆盖已安装内容的命令
- **User Scope**: **Deploy Scope** 为用户主目录
- **User Root**: **User Scope** 目录路径
- **Workspace Root**: **Project Scope** 目录路径
- **Phase**: 规划实现批次（Phase 1 先行）
- **Wave**: 仅 `tasks.md` 执行编排批次（与 phase 对应，tasks 专用术语）
- **SSOT**: `docs/state/` 下与代码共同构成系统唯一事实来源的文档
- **APM Skill**: Cursor/Kiro 中引导 `/apm init` 等流程的 skill 文档

---

## Requirements

### Requirement 1 (Phase 1): Baseline Resolver 兼容 XDG Composer 目录

**User Story:** As a macOS user with Composer 2.x XDG layout, I want the Baseline Resolver to find my global apm package, so that check and show use baseline diff instead of reporting everything as unknown.

#### Acceptance Criteria

1. WHEN `APM_BASELINE_ROOT` is set to an existing directory, THE Baseline Resolver SHALL use it before any Composer path fallback.
2. WHEN `COMPOSER_HOME` is set, THE Baseline Resolver SHALL resolve `installed.json` under that home.
3. WHEN neither is set, THE Baseline Resolver SHALL try `$HOME/.composer` then `$HOME/.config/composer` and use the first where `vendor/composer/installed.json` exists.
4. WHEN the resolved `installed.json` contains the global apm package, THE Baseline Resolver SHALL return a non-null **Baseline** path.
5. WHEN **Baseline** resolves on XDG layout, THE Check Service SHALL NOT mark every **Conventional Ability** as `unknown` solely due to legacy `$HOME/.composer`-only fallback.
6. THE **SSOT** for install and check behavior SHALL document the XDG fallback order.

---

### Requirement 2 (Phase 2): Installation Fallback 正确识别 category rule

**User Story:** As a user running show when Baseline is unavailable, I want category-prefixed rules detected when on disk, so that I do not see false not-installed results.

#### Acceptance Criteria

1. WHEN **Installation Fallback** evaluates a `category:name` rule, THE Installation Fallback SHALL check the **Ability Registry** `targets` path relative to **Workspace Root** or **User Root**.
2. THE Installation Fallback SHALL NOT match by basename alone while ignoring category.
3. WHEN two rules share basename under different categories, THE Installation Fallback SHALL distinguish by full relative path.
4. WHEN **Baseline** is unavailable and the registry path exists on disk, THE Show Command SHALL NOT show not installed for that rule.
5. THE Installation Fallback SHALL remain until a later spec explicitly removes it.

---

### Requirement 3 (Phase 2): Deploy Scope 模型

**User Story:** As a developer, I want abilities in user or project scope, so that shared tooling is not duplicated per repo.

#### Acceptance Criteria

1. THE APM CLI SHALL support **Deploy Scope** `project` and `user`.
2. WHEN scope is omitted, THE APM CLI SHALL default to **Project Scope** first.
3. WHEN **Project Scope** is selected, THE APM CLI SHALL resolve paths under **Workspace Root**; WHEN **User Scope** is selected, THE APM CLI SHALL resolve paths under **User Root**.
4. WHEN a **Project-Only Ability** is requested for **User Scope**, THE APM CLI SHALL reject it.
5. WHEN `--scope` is invalid, THE APM CLI SHALL fail with an explicit error.
6. WHEN a batch includes any illegal user-scope **Project-Only Ability**, THE APM CLI SHALL fail with zero partial writes.
7. THE **Ability Registry** SHALL declare **Project-Only Ability** entries matching PRP-002（gitignore、prompt、tga-query、lark-sheets、cursor-scope、kiro-scope、superpowers-integration）为仅 **Project Scope**。

---

### Requirement 4 (Phase 2): Ability Registry 的 Global Setup List 与 preset default 移除

**User Story:** As a maintainer, I want global user tooling separate from project presets, so that new projects are not forced through a monolithic default preset.

#### Acceptance Criteria

1. THE **Ability Registry** SHALL define **Global Setup List** as top-level typed includes without wildcards.
2. THE **Global Setup List** SHALL NOT be invokable as a preset via `apm install <preset>`.
3. THE **Ability Registry** SHALL NOT contain a preset named `default`.
4. EACH **Global Setup List** entry SHALL allow **User Scope** per PRP-002 closed decisions.
5. OTHER presets SHALL keep independent **Deploy Scope** lists.

---

### Requirement 5 (Phase 2): Global Setup Command

**User Story:** As a user who globally installed apm, I want one command for user-level abilities before project init.

#### Acceptance Criteria

1. THE **Global Setup Command** SHALL install only **Global Setup List** entries.
2. THE **Global Setup Command** SHALL install only to **User Scope** and SHALL NOT accept `--scope` or preset arguments.
3. THE **Global Setup Command** SHALL succeed from any working directory.
4. WHEN already installed in **User Scope**, THE **Global Setup Command** SHALL be idempotent; WHEN `--force` is passed, THE **Global Setup Command** SHALL overwrite installed ability files.
5. WHEN a **Global Setup List** entry lacks cursor **Target**, THE **Global Setup Command** with `-t cursor` SHALL emit `[skip]` with reason without failing the run.
6. WHEN the **Global Setup Command** completes successfully, THE APM CLI SHALL prompt the user to run `/apm init` in a business repo.

---

### Requirement 6 (Phase 2): Bootstrap Command

**User Story:** As a user initializing a repo, I want scaffold separated from ability install.

#### Acceptance Criteria

1. THE APM CLI SHALL provide **Bootstrap** that runs scaffold initialization only.
2. **Bootstrap** SHALL NOT install **Conventional Ability** or presets.
3. **Bootstrap** SHALL preserve existing idempotency and `--force` rules for `docs/`, `issues/`, `AGENTS.md`.
4. `/apm init` documentation SHALL require **Bootstrap** as the first step.

---

### Requirement 7 (Phase 2): Cleanup Command

**User Story:** As a developer resetting project tooling, I want project abilities removed without touching user profile or docs.

#### Acceptance Criteria

1. THE **Cleanup Command** SHALL uninstall **Conventional Ability** only under **Project Scope**.
2. THE **Cleanup Command** SHALL NOT modify **User Scope**, scaffold, `PROJECT.md`, or `docs/state|manual` content.
3. THE documented reinstall path SHALL be **Cleanup Command** then `/apm init` without repeating **Global Setup Command**.

---

### Requirement 8 (Phase 2): CLI breaking 变更与命令同义词

**User Story:** As a user, I want no-argument install/add to fail with guidance.

#### Acceptance Criteria

1. WHEN `install` or `add` has no type and no preset, THE APM CLI SHALL fail and direct to **Global Setup Command**, **Bootstrap**, or typed add.
2. THE APM CLI SHALL treat `install`/`add` and `uninstall`/`remove` as synonyms where both exist.
3. WHEN adding by name, THE APM CLI SHALL require explicit type; omitting type SHALL fail with guidance.
4. WHEN installing a preset, THE APM CLI SHALL validate all entries first; IF any invalid, THEN zero writes.
5. THE `check` command SHALL NOT gain new aliases beyond PRP-002 documented equivalence.

---

### Requirement 9 (Phase 2): Show Command 重写

**User Story:** As a user, I want one line per ability with clear user/project state.

#### Acceptance Criteria

1. THE **Show Command** SHALL list only **Conventional Ability** entries, not preset names or scaffold rows.
2. THE **Show Command** SHALL emit one line per ability with **Target** coverage as readable text.
3. THE **Show Command** SHALL label installed (user), installed (project), or both with `[warn] installed in both user and project` and scope hint.
4. THE **Show Command** SHALL use states: `not installed`, `installed`, `installed with local change` relative to **Baseline**.
5. WHEN `--scope` is set, THE **Show Command** SHALL filter to that **Deploy Scope**.
6. THE **Show Command** SHALL NOT share update-availability wording with **Update Command**.

---

### Requirement 10 (Phase 2): Update Command 重写

**User Story:** As a global apm user, I want updates only from the correct baseline.

#### Acceptance Criteria

1. WHEN not run from **Global APM Installation**, THE **Update Command** SHALL fail with guidance to use global binary.
2. THE **Update Command** SHALL enumerate installed **Conventional Ability** in **User Scope** and **Project Scope**.
3. WITHOUT `--force`, THE **Update Command** SHALL print only changed items or an up-to-date message.
4. WITH `--force`, THE **Update Command** SHALL overwrite differing installed files from **Baseline**.
5. THE **Update Command** SHALL NOT add preset-level `--scope` override beyond per-ability defaults in **Ability Registry**.

---

### Requirement 11 (Phase 2): Init 流程文档

**User Story:** As an agent following `/apm init`, I want ordered bootstrap and documented init paths.

#### Acceptance Criteria

1. THE **APM Skill** (cursor and kiro copies) SHALL document **Bootstrap** as the mandatory first init step.
2. THE **APM Skill** SHALL cover detection and planning init paths per PRP-002 §4.
3. THE **APM Skill** SHALL state that init-time `apm add` defaults to `-t cursor -t kiro` unless narrowed.
4. THE **APM Skill** SHALL require listing every `apm` command and outcome during init.
5. THE **APM Skill** SHALL NOT instruct installing **Global Setup List** items into **Project Scope** during init.
6. THE **APM Skill** SHALL document agent decision principles, not a fixed preset mapping table.
7. THE **APM Skill** init workflow reference SHALL reflect the above.

---

### Requirement 12 (Phase 2): 用户文档与 SSOT 同步

**User Story:** As a new user, I want docs to match the three-phase setup flow.

#### Acceptance Criteria

1. `README.md` and `docs/manual/usage.md` SHALL document **Global Setup Command** then `/apm init`, not no-arg `apm install`.
2. `docs/README.md` SHALL reference **Bootstrap** for scaffold.
3. `docs/state/cli-commands.md` and `install-behavior.md` SHALL document new commands and show/update behavior.
4. `docs/state/abilities-model.md` SHALL document **Deploy Scope** and **Global Setup List**.
5. THE **SSOT** and user-facing documentation SHALL match the shipped **Ability Registry** on the feature branch.

---

### Requirement 13 (Phase 2, 阻塞): Platform Verification

**User Story:** As a release owner, I want real IDE verification before finish.

#### Acceptance Criteria

1. BEFORE finish, THE team SHALL verify **User Scope** loads in `~/.cursor` and `~/.kiro`.
2. BEFORE finish, THE team SHALL verify user-scope hooks where listed in **Global Setup List**.
3. WHEN the same ability exists in both scopes, THE team SHALL record IDE behavior and **Show Command** warnings.
4. Results SHALL be recorded in a verification log referenced at finish.

---

### Requirement 14 (Phase 2): Issue 闭环

**User Story:** As a maintainer, I want issues closed with the shipping version.

#### Acceptance Criteria

1. WHEN Phase 1 passes, ISS-31532 SHALL close with **Fixed In** set to the shipping version.
2. WHEN Phase 2 passes, ISS-01592 SHALL close with **Fixed In** set to the shipping version.
3. EACH closed issue SHALL reference this spec or the release changelog.

