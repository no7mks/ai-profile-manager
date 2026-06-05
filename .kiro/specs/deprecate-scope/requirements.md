# Requirements Document

## Introduction

本文档定义 apm CLI 工具"废弃 Scope"功能的需求。该功能废弃 `--scope` 参数、`user` scope 概念和 `global-setup` 命令，逻辑上仅保留 project scope，简化代码路径与用户心智模型。

主要变更范围：

- CLI 命令行为变更（`--scope` 参数拒绝、`global-setup` 命令拒绝）
- `bootstrap` 命令扩展（scaffold + ability 安装）
- `abilities.yaml` 格式变更（移除 `scopes` 字段、`global-setup` section 改名为 `bootstrap`）
- `show`、`update`、`cleanup` 命令简化
- 代码清理与文档同步

Non-scope：

- 不引入新 scope 概念（如 workspace scope）
- 不修改 `apm init` Agent 端逻辑
- 不做向后兼容 shim（`--scope project` 不静默通过）
- 不自动清理用户主目录下已安装的 user scope 文件

---

## Glossary

- **CLI**: apm 命令行工具入口（`bin/apm`）
- **Ability**: 在 registry 登记、可安装可卸载的条目（skill / rule / agent / hook / gitignore / prompt）
- **Ability_Registry**: 解析 `abilities.yaml` 并提供 ability 条目查询的组件
- **Bootstrap_Command**: `apm bootstrap` 命令，执行项目初始化 scaffold 及 ability 安装
- **Install_Command**: `apm install` 命令（别名 `add`），按 preset 安装 ability
- **Show_Command**: `apm show` 命令，展示 ability 安装状态
- **Update_Command**: `apm update` 命令，检测或更新已安装 ability 与 baseline 的差异
- **Cleanup_Command**: `apm cleanup` 命令，批量卸载 project scope 已安装 ability
- **Global_Setup_Command**: `apm global-setup` 命令（已废弃）
- **Typed_Install_Command**: `skill:install`、`rule:install`、`agent:install` 命令的统称
- **Typed_Uninstall_Command**: `skill:uninstall`、`rule:uninstall`、`agent:uninstall`、`preset:uninstall` 命令的统称
- **Project_Scope**: 以当前工作目录为根的部署范围
- **Scaffold**: `bootstrap` 执行的项目骨架搭建（目录结构与模板文件）
- **Bootstrap_Includes**: `abilities.yaml` 中 `bootstrap.includes` 列表定义的 ability 清单

---

## Requirements

### Requirement 1: 废弃 --scope 参数

**User Story:** As a CLI 用户, I want 传入 `--scope` 时得到明确的废弃错误, so that 我能了解该参数已被移除并调整使用方式。

#### Acceptance Criteria

1. WHEN 用户向 Install_Command 传入 `--scope` 参数（任何值，包括 `--scope project`、`--scope user`、`--scope=<value>` 等形式）, THE CLI SHALL 输出废弃错误消息到 stderr 并以非零退出码退出，且不执行安装操作
2. WHEN 用户向 Show_Command 传入 `--scope` 参数（任何值）, THE CLI SHALL 输出废弃错误消息到 stderr 并以非零退出码退出，且不执行查询操作
3. WHEN 用户向 Typed_Install_Command 传入 `--scope` 参数（任何值）, THE CLI SHALL 输出废弃错误消息到 stderr 并以非零退出码退出，且不执行安装操作
4. WHEN 用户向 Typed_Uninstall_Command 传入 `--scope` 参数（任何值）, THE CLI SHALL 输出废弃错误消息到 stderr 并以非零退出码退出，且不执行卸载操作
5. THE CLI SHALL 在废弃错误消息中包含以下两项信息：(a) `--scope` 选项已被移除；(b) 所有操作现在仅针对 Project_Scope
6. WHEN 用户向上述命令传入 `--scope` 参数同时附带其他有效参数, THE CLI SHALL 在解析到 `--scope` 后立即报错，不对其他参数进行业务处理

---

### Requirement 2: 废弃 global-setup 命令

**User Story:** As a CLI 用户, I want 执行 `global-setup` 时得到明确的废弃错误和迁移指引, so that 我能迁移到 `bootstrap` 命令。

#### Acceptance Criteria

1. WHEN 用户执行 Global_Setup_Command（无论是否附带其他参数如 `--target`、`--force`）, THE CLI SHALL 输出废弃错误消息并以非零退出码退出
2. THE CLI SHALL 在 Global_Setup_Command 的废弃错误消息中同时包含已移除命令名称（`global-setup`）和替代命令名称（`apm bootstrap`）
3. WHEN 用户执行 Global_Setup_Command, THE CLI SHALL 不写入任何文件、不创建任何目录、不发起任何网络请求

---

### Requirement 3: 扩展 bootstrap 命令

**User Story:** As a CLI 用户, I want `bootstrap` 命令一次完成 scaffold 和 ability 安装, so that 项目初始化流程从三阶段简化为两阶段。

#### Acceptance Criteria

1. WHEN 用户执行 Bootstrap_Command, THE Bootstrap_Command SHALL 先执行 Scaffold 搭建，完成后再进入 ability 安装阶段
2. WHEN Scaffold 搭建完成后, THE Bootstrap_Command SHALL 读取 Bootstrap_Includes 列表，对每个条目按 `type:path` 格式解析，并逐项安装对应 Ability 到 Project_Scope 的各 target 目录（未指定 `-t` 时 target 默认为 `['cursor', 'kiro']`）
3. IF Bootstrap_Includes 中的 Ability 在当前 target 已安装且未传入 `--force`, THEN THE Bootstrap_Command SHALL 跳过该 Ability 并输出 `[skip]` 标记
4. IF Bootstrap_Includes 中的 Ability 在当前 target 已安装且传入 `--force`, THEN THE Bootstrap_Command SHALL 覆盖安装该 Ability
5. IF Bootstrap_Includes 中某项 Ability 安装失败, THEN THE Bootstrap_Command SHALL 输出该项的 `[fail]` 标记并继续安装剩余项
6. IF Bootstrap_Includes 中存在任何安装失败项, THEN THE Bootstrap_Command SHALL 以 exit code 1 退出
7. IF Bootstrap_Includes 中存在安装失败项, THEN THE Bootstrap_Command SHALL 保留已完成的 Scaffold（不回滚）
8. IF Bootstrap_Includes 列表为空或未定义, THEN THE Bootstrap_Command SHALL 仅完成 Scaffold 搭建并以 exit code 0 退出
9. WHEN Bootstrap_Includes 中所有 Ability 安装成功（含被跳过的项）, THE Bootstrap_Command SHALL 以 exit code 0 退出

---

### Requirement 4: abilities.yaml 格式变更

**User Story:** As a CLI 用户, I want `abilities.yaml` 格式统一移除 scope 相关字段, so that 配置文件结构更简洁、与单一 scope 模型一致。

#### Acceptance Criteria

1. WHEN `abilities.yaml` 中任何 conventional ability entry（rules / agents / skills / hooks）仍包含 `scopes` 字段, THE Ability_Registry SHALL 抛出 validation error 并立即终止解析，不再处理后续条目
2. WHEN `abilities.yaml` 中仍存在 `global-setup` 顶层 key, THE Ability_Registry SHALL 抛出 validation error 并立即终止解析，不再处理后续条目
3. THE Ability_Registry SHALL 从顶层 `bootstrap.includes` 列表（格式为 `type:path` 字符串数组）读取 Bootstrap_Includes
4. IF `abilities.yaml` 中 `bootstrap` section 不存在或 `bootstrap.includes` 为空列表, THEN THE Ability_Registry SHALL 返回空的 Bootstrap_Includes 列表（不视为错误）
5. THE Ability_Registry SHALL 在遇到首个验证违规（`scopes` 字段或 `global-setup` key）时立即终止（fail-fast），不收集后续违规

---

### Requirement 5: show 命令简化

**User Story:** As a CLI 用户, I want `show` 命令仅展示 project scope 状态, so that 输出更清晰、无冗余 scope 信息。

#### Acceptance Criteria

1. THE Show_Command SHALL 仅评估 Project_Scope 下每个 Ability 的安装状态，输出每行格式为 `{type}:{name}  {status}   {targets}`，其中 status 取值为 `not installed`、`installed` 或 `installed with local change`
2. THE Show_Command SHALL 不在输出行中包含 scope 标签（`(user)`、`(project)`、`(user+project)`）
3. THE Show_Command SHALL 不输出双 scope 冲突警告（`[warn] installed in both user and project`）
4. WHEN 用户通过 `--type` 参数指定 Ability 类型时, THE Show_Command SHALL 仅输出匹配该类型的 Ability 行
5. IF Project_Scope 中无任何已注册 Ability, THEN THE Show_Command SHALL 输出空列表且以 SUCCESS 退出

---

### Requirement 6: update 命令简化

**User Story:** As a CLI 用户, I want `update` 命令仅检查 project scope 已安装 ability, so that 更新行为与单一 scope 模型一致。

#### Acceptance Criteria

1. THE Update_Command SHALL 仅遍历 Project_Scope 中已安装的 Ability 进行差异检测
2. THE Update_Command SHALL 不遍历 user scope 中已安装的 Ability
3. WHEN Update_Command 检测到差异时, THE Update_Command SHALL 以 `changed: {type}:{name} {target}` 格式输出每条变更行（不包含 scope 标签）
4. WHEN 用户传入 `--force` 且存在差异项, THE Update_Command SHALL 使用 baseline 覆盖 Project_Scope 中对应的已安装文件

---

### Requirement 7: cleanup 命令文案清理

**User Story:** As a CLI 用户, I want `cleanup` 命令的输出不再提及 user scope, so that 文案与当前系统模型一致。

#### Acceptance Criteria

1. THE Cleanup_Command SHALL 不在任何输出文案中包含 "user-scope"、"user scope"、或 "global-setup" 字样
2. WHEN Cleanup_Command 以 SUCCESS 退出时, THE Cleanup_Command SHALL 输出引导用户执行 `/apm init` 以重新安装 project abilities 的提示信息
3. IF Cleanup_Command 以 FAILURE 退出, THEN THE Cleanup_Command SHALL 不输出重新安装引导提示

---

### Requirement 8: 代码清理

**User Story:** As a 开发者, I want 移除所有 user scope 相关的内部组件, so that 代码库与单一 scope 模型保持一致、减少维护负担。

#### Acceptance Criteria

1. THE CLI SHALL 不包含 UserHomeResolver 类文件，且源码中无任何对该类的 use 声明或实例化
2. THE CLI SHALL 不包含 ScopeGuard 类文件，且源码中无任何对该类的 use 声明或实例化
3. THE CLI SHALL 不包含 DefaultGlobalSetupService / GlobalSetupService 类文件，且源码中无任何对该类的 use 声明或实例化
4. THE CLI SHALL 在 DeployRootResolver 中仅返回当前工作目录（project root），不接受 DeployScope 参数且不包含 user scope 路径解析逻辑
5. THE CLI SHALL 在 InstallationProbe 和 Installer 中不接受 `$scope` 参数，硬编码使用 Project_Scope
6. THE CLI SHALL 在 CheckService 中不包含 `checkTypedForScope` 方法，`checkTyped` 直接使用 project root
7. THE CLI SHALL 在 HandlesDeployScopeOption 中仅检测 `--scope` 选项并输出废弃错误消息，不包含 scope 解析或 ScopeGuard 调用逻辑
8. THE CLI SHALL 在 ShowStatusPresenter 中不包含 scope 合并展示逻辑（多 scope 状态聚合或冲突标注）
9. THE CLI SHALL 在 InvalidScopeException 中不包含 `projectOnlyInUserScope` 工厂方法，仅保留废弃消息工厂
10. THE CLI SHALL 在 AbilityEntry 中不包含 `$scopes` 属性
11. THE CLI SHALL 保留 DeployScope 枚举且仅含 `Project` 单值（`User` case 已移除）

---

### Requirement 9: 文档同步

**User Story:** As a 开发者, I want 所有 SSOT 文档反映废弃 scope 后的系统状态, so that 文档与代码实现保持一致。

#### Acceptance Criteria

1. WHEN 代码变更完成后, THE CLI 项目 SHALL 重写 `docs/state/deploy-scope.md` 使其仅描述 Project_Scope（移除 User Root 解析表、`user` scope 枚举值、ScopeGuard 章节、InvalidScopeException 的 `projectOnlyInUserScope` 工厂方法、及 CheckService 中 user scope 相关段落）
2. WHEN 代码变更完成后, THE CLI 项目 SHALL 更新 `docs/state/cli-commands.md` 以移除所有命令签名中的 `[--scope project|user]` 参数，并在 `global-setup` 命令章节顶部标注 `> ⚠️ DEPRECATED — 此命令将在下一主版本移除`，同时更新 `bootstrap` 签名以反映当前实现
3. WHEN 代码变更完成后, THE CLI 项目 SHALL 更新 `docs/state/abilities-model.md` 以移除 ability entry 中 `scopes` 字段的文档（含字段表格行、合法值说明、Project-Only 定义段落及 `projectOnlyPaths()` 列表）
4. WHEN 代码变更完成后, THE CLI 项目 SHALL 更新 `docs/state/install-behavior.md` 以移除「Deploy Scope 与路径解析」章节中所有 user scope 相关内容（含 User Root 路径示例、user scope 探测逻辑、`--scope` 解析说明及 Scope 安装 CLI 段落中的 scope 参数描述）
5. WHEN 代码变更完成后, THE CLI 项目 SHALL 更新 `docs/manual/usage.md` 将「三阶段首装」改写为两阶段首装（仅保留 bootstrap + init，移除 `global-setup` 阶段），并移除正文中所有 `--scope` 参数示例与 `global-setup` 命令引用
6. WHEN 代码变更完成后, THE CLI 项目 SHALL 更新 `.cursor/skills/apm/references/init-workflow.md` 与 `.kiro/skills/apm/references/init-workflow.md` 以移除 Bootstrap Phase 中「禁止在 init 中将 Global Setup List 中的 ability 以 project scope 安装」条目及所有 `global-setup` 引用，并将首装流程描述调整为两阶段（bootstrap + init）
7. IF 更新后的文档中仍存在对 `user` scope、`--scope` 参数或 `global-setup`（非 deprecated 标注）的引用, THEN THE CLI 项目 SHALL 将其视为遗漏并修正，确保文档集内零残留引用


---

## Requirement Discussion

> 以下问题聚焦 requirements 到 design 的衔接——AC 中存在多种合理实现路径的决策点。请在进入 design 阶段前逐一确认。

**RD-1**: Requirement 1 AC6 要求"解析到 `--scope` 后立即报错"。在 Laravel/Symfony Console 体系中，option 的检测时机有多种实现路径。你倾向哪种检测层级？

- A) 在各命令的 `handle()` 方法顶部统一检测（复用 `HandlesDeployScopeOption` trait）
- B) 在 Application 层注册全局 event listener，所有命令执行前统一拦截
- C) 保持现有 trait 方式但将检测移到 `initialize()` 方法（比 handle 更早，在 input binding 之后立即执行）

**RD-2**: Requirement 4 AC1/AC2 要求 fail-fast 抛异常终止解析。对于 `scopes` 字段和 `global-setup` key 这两种违规，错误消息的呈现策略是什么？

- A) 两种违规使用相同的通用错误消息模板（如 "Legacy field '{field}' is no longer supported"）
- B) 分别使用独立的、包含迁移指引的错误消息（如 scopes → "Remove scopes field; all entries are project-only"；global-setup → "Rename to bootstrap"）
- C) 使用相同异常类但通过不同的 factory method 生成消息，各自包含具体迁移建议

**RD-3**: Requirement 3 AC2 中 Bootstrap 安装 ability 时，如果 `bootstrap.includes` 列表中包含了当前 `abilities.yaml` 中不存在的 `type:path`（例如拼写错误或 ability 已被移除），应如何处理？

- A) 视为安装失败，输出 `[fail]` 并继续剩余项（与其他安装失败等同处理）
- B) 在安装前做前置校验，若列表中有无效 entry 则整体拒绝执行（fail-fast）
- C) 输出 `[warn]` 跳过无效条目，不计入失败项（最终仍可能以 exit 0 退出）

**RD-4**: Requirement 8 AC4 要求 DeployRootResolver "仅返回当前工作目录"。现有 DeployRootResolver 的职责是否应进一步简化（甚至内联）？还是保留为独立类以维持可测试性？

- A) 保留 DeployRootResolver 类，简化为仅 `return getcwd()`，保持依赖注入和可测试性
- B) 删除 DeployRootResolver，所有调用方直接使用 `getcwd()`（减少间接层）
- C) 保留类但改为接受构造函数注入的 root path（默认 `getcwd()`），方便测试时传入 fixture 路径


---

## Requirement Discussion

| # | 问题 | 决策 |
|---|------|------|
| RD-1 | `--scope` 检测时机 | 在各命令 `handle()` 顶部检测（复用 HandlesDeployScopeOption trait） |
| RD-2 | `scopes`/`global-setup` 违规错误消息策略 | 相同异常类 + 不同 factory method，各自包含具体迁移建议 |
| RD-3 | `bootstrap.includes` 引用 registry 中不存在的 ability | 前置校验，列表中有无效 entry 则整体拒绝执行（fail-fast） |
| RD-4 | DeployRootResolver 简化策略 | 保留类，构造函数注入 root path（默认 `getcwd()`），方便测试 |
