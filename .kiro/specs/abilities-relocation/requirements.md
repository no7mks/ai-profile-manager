# Requirements Document

## Introduction

本文档定义 PRP-001 Phase 2 的需求：CLI 代码适配新布局、废弃代码移除、Hook Ability 支持，以及 State 文档补写。

Phase 1 已完成文件迁移与 abilities.yaml 注册表建立；本阶段使 CLI 完全基于新布局运行，新增 hook 类型 ability，并移除已废弃的 capture/ingest 功能。

### Non-scope

- 跨项目 ability 分发
- ability 版本管理
- capture/ingest 功能的替代方案
- Kiro ↔ Cursor hook 事件类型的自动映射

---

## Glossary

- **APM**: AI Profile Manager，本项目的 CLI 工具
- **Ability**: APM 管理的最小配置单元，包含 rule、agent、skill、hook、gitignore 五种类型
- **Ability_Registry**: `abilities.yaml` 文件，记录所有 ability 的路径、描述与目标平台映射
- **Target**: ability 在特定平台上的安装目标路径
- **Platform**: APM 支持的目标 IDE 平台（Cursor 或 Kiro）
- **Installer**: 负责将 ability 源文件复制到目标项目的模块
- **CheckService**: 对比已安装 ability 与源文件差异的模块
- **GitignoreManager**: 负责操作 `.gitignore` 文件中 Marker_Block 的模块
- **Marker_Block**: `.gitignore` 文件中由 APM 管理的标记区块，以 `@apm:block` 标识
- **Hook**: 一种 ability 类型，定义 IDE 生命周期事件的自动化响应
- **Hook_Registry_File**: Cursor 平台的 `.cursor/hooks.json` 集中配置文件
- **Preset**: 多个 ability 的组合引用，支持批量安装
- **Directory_Duplicate**: skill 类型 ability 安装时的目录级重复检测
- **Capture**: 已废弃的变更捕获功能模块
- **Ingest**: 已废弃的变更导入功能模块

---

## Requirements

### Requirement 1: Ability 列表解析

**User Story:** As a 开发者, I want APM 从 Ability_Registry 解析 ability 列表并基于顶层 section 名称判定类型, so that CLI 命令能正确识别和操作所有 ability。

#### Acceptance Criteria

1. WHEN install、check 或 uninstall 命令执行时, THE APM SHALL 从 Ability_Registry 文件读取 ability 列表，并将每个 ability 所在的顶层 section 名称（rules、agents、skills、hooks、gitignore、presets）作为其类型判定依据
2. WHEN Ability_Registry 包含 rules、agents、skills、hooks、gitignore、presets 以外的未知顶层 section 时, THE APM SHALL 忽略该 section 中的条目且不中断命令执行
3. IF Ability_Registry 文件不存在或内容无法解析为合法 YAML, THEN THE APM SHALL 终止当前命令并输出包含文件路径的错误信息
4. THE APM SHALL 仅通过 Ability_Registry 的顶层 section 名称判定 ability 类型，不使用文件后缀解析逻辑作为类型判定手段

---

### Requirement 2: Ability 安装

**User Story:** As a 开发者, I want APM 从 ability 的真实路径读取源文件并安装到目标位置, so that 安装结果与新目录布局一致。

#### Acceptance Criteria

1. WHEN 安装 rule 或 agent 类型 ability 时, THE Installer SHALL 从 Ability_Registry 中声明的源路径读取文件并复制到该 ability 在 abilities.yaml 中为当前 Platform 定义的 Target 路径，若目标文件已存在则覆盖
2. WHEN 安装 skill 类型 ability 时, THE Installer SHALL 将源目录递归复制到该 ability 在 abilities.yaml 中为当前 Platform 定义的 Target 路径，保留目录结构，若目标文件已存在则覆盖
3. WHEN 安装任意类型 ability 且目标路径的父目录不存在时, THE Installer SHALL 自动创建所需的父目录层级
4. WHEN 安装 skill 类型 ability 且目标目录已存在时, THE Installer SHALL 执行 Directory_Duplicate 检测并在输出中报告冲突的目录路径
5. IF 源文件或源目录路径不存在, THEN THE Installer SHALL 返回 exit code 1 并输出包含缺失路径的错误信息
6. IF 文件复制过程中发生写入失败, THEN THE Installer SHALL 返回 exit code 1 并输出包含失败原因的错误信息

---

### Requirement 3: Ability 状态检查

**User Story:** As a 开发者, I want APM 检查已安装 ability 与源文件的一致性, so that 我能发现配置漂移。

#### Acceptance Criteria

1. WHEN check 命令执行时, THE CheckService SHALL 逐字节对比 Ability_Registry 中每个 ability 的 baseline 源文件与 workspace 已安装文件，并为每个 ability 在每个 Target 上产生独立的状态结果
2. WHEN 已安装文件与 baseline 源文件内容不一致时, THE CheckService SHALL 报告该 ability 为 "drift" 状态
3. WHEN 已安装文件不存在时（对 skill 类型为整个目录不存在，对 rule/agent 类型为单文件不存在）, THE CheckService SHALL 报告该 ability 为 "missing" 状态
4. WHEN 已安装文件与 baseline 源文件内容一致时, THE CheckService SHALL 报告该 ability 为 "ok" 状态
5. IF baseline 源路径无法解析（ComposerBaselineResolver 返回 null）, THEN THE CheckService SHALL 报告所有 ability 为 "unknown" 状态
6. WHEN skill 类型 ability 执行 check 时, THE CheckService SHALL 对比该 skill 目录下所有文件（递归），任一文件存在差异即判定整个 skill 为 "drift"
7. WHEN check 结果中存在任一 "drift" 或 "missing" 状态时, THE CheckService SHALL 返回 exit code 2；否则返回 exit code 0

---

### Requirement 4: Ability 卸载

**User Story:** As a 开发者, I want APM 从目标项目移除已安装的 ability, so that 我能清理不再需要的配置。

#### Acceptance Criteria

1. WHEN uninstall 命令执行时, THE Installer SHALL 遍历所有指定 Target，对每个指定 ability 执行删除操作，并为每个 ability 输出包含 ability 名称与 Target 名称的操作结果信息
2. WHEN 卸载 skill 类型 ability 时, THE Installer SHALL 删除该 skill 在 Target 下对应的整个安装目录（含所有子文件与子目录）
3. WHEN 卸载 rule 或 agent 类型 ability 时, THE Installer SHALL 仅删除该 ability 对应的单个目标文件
4. IF 目标文件或目录在卸载时已不存在, THEN THE Installer SHALL 跳过该项并输出包含 ability 名称的未找到提示信息，继续处理其余 ability，且命令退出码保持为 0
5. IF CheckService 检测到已安装 ability 存在内容变更（drift）且未指定 force 选项, THEN THE Installer SHALL 中止卸载并返回失败退出码

---

### Requirement 5: Gitignore 管理

**User Story:** As a 开发者, I want APM 直接操作 `.gitignore` 中的 Marker_Block, so that gitignore 规则与 ability 安装保持同步。

#### Acceptance Criteria

1. WHEN gitignore install 命令执行时, THE GitignoreManager SHALL 在 `.gitignore` 文件中插入或更新对应的 Marker_Block
2. WHEN gitignore uninstall 命令执行时, THE GitignoreManager SHALL 从 `.gitignore` 文件中移除对应的 Marker_Block
3. WHEN gitignore check 命令执行时, THE GitignoreManager SHALL 检查 `.gitignore` 中是否存在对应的 Marker_Block 且内容一致
4. IF `.gitignore` 文件不存在, THEN THE GitignoreManager SHALL 创建该文件并写入 Marker_Block
5. IF 无任何 Marker_Block 与当前 ability key 匹配, THEN THE GitignoreManager SHALL 跳过 `.gitignore` 写入并返回提示信息表明无匹配模板块
6. THE GitignoreManager SHALL 保留 `.gitignore` 中 Marker_Block 之外的用户手动维护内容不做任何修改

---

### Requirement 6: Capture 功能移除

**User Story:** As a 开发者, I want 已废弃的 Capture 功能被完全移除, so that 代码库不包含无用模块。

#### Acceptance Criteria

1. THE APM SHALL 不再注册以下命令: `capture`、`skill:capture`、`rule:capture`、`agent:capture`、`ingest`
2. THE APM SHALL 不再包含 `src/Capture/` 目录及 `src/Service/CaptureService.php`、`src/Command/` 下的 CaptureCommand、SkillCaptureCommand、RuleCaptureCommand、AgentCaptureCommand、IngestCaptureChangeCommand 源文件
3. THE APM SHALL 不再包含 Capture 相关的测试文件与 fixture
4. THE APM 代码库中保留的命令 SHALL 不再引用或依赖任何已移除的 Capture 类
5. WHEN 用户尝试执行 `capture`、`skill:capture`、`rule:capture`、`agent:capture` 或 `ingest` 命令时, THE APM SHALL 返回 "command not found" 错误

---

### Requirement 7: Ingest 功能移除

**User Story:** As a 开发者, I want 已废弃的 Ingest 功能被完全移除, so that 代码库不包含无用模块。

#### Acceptance Criteria

1. THE APM SHALL 不再注册 `ingest` 命令（即 IngestCaptureChangeCommand 不再被添加到命令注册）
2. THE APM SHALL 不再包含 Ingest 模块的源代码文件
3. THE APM SHALL 不再包含 Ingest 模块的专属测试文件及其它测试文件中针对 `ingest` 命令的测试方法与断言
4. IF 移除完成后执行全量测试, THEN THE APM SHALL 通过所有剩余测试且无因 Ingest 移除导致的引用错误或导入失败

---

### Requirement 8: Hook Ability 注册

**User Story:** As a 开发者, I want 在 Ability_Registry 中定义 hook 类型 ability, so that APM 能管理 IDE hook 配置。

#### Acceptance Criteria

1. THE Ability_Registry SHALL 支持 `hooks` 段用于声明 hook 类型 ability，其结构与 `rules` 段相同（YAML 列表，每项为一个 ability 条目）
2. THE Ability_Registry 中每个 hook ability SHALL 包含 path、description 和 targets 字段，其中 targets 至少声明 1 个 Platform（cursor 或 kiro）的 Target 路径
3. THE Ability_Registry SHALL 允许 hook ability 仅声明单个 Platform 的 Target（无需双平台覆盖）
4. THE hook ability 的源文件 SHALL 使用对应 Platform 的官方格式：Kiro 平台为 JSON 文件（含 name、version、when、then 结构），Cursor 平台为 hooks.json 片段格式（含事件类型键与 command 字段）
5. THE 每个 hook ability SHALL 在每个 Platform 上对应一个独立条目：Kiro 平台为一个独立 hook 文件，Cursor 平台为 Hook_Registry_File 中一个 (event_type_key, command) 条目
6. IF hook ability 条目缺少 path、description 或 targets 中任一必填字段, THEN THE APM SHALL 在解析阶段返回错误信息并标明缺失的字段名与对应条目

---

### Requirement 9: Hook 安装 — Kiro 平台

**User Story:** As a 开发者, I want APM 将 hook 安装到 Kiro 平台, so that Kiro IDE 能加载自定义 hook。

#### Acceptance Criteria

1. WHEN 安装 hook 类型 ability 到 Kiro 平台时, THE Installer SHALL 将源文件复制到 `.kiro/hooks/` 目录下的 Target 路径；若 `.kiro/hooks/` 目录不存在，THE Installer SHALL 先创建该目录再执行复制
2. WHEN check 命令检查 Kiro 平台 hook 时, THE CheckService SHALL 对比源文件与 Target 路径下已安装文件的内容，并按 Requirement 3 定义的状态模型报告结果（ok、drift 或 missing）
3. WHEN 卸载 hook 类型 ability 从 Kiro 平台时, THE Installer SHALL 删除 Target 路径对应的 hook 文件
4. IF 卸载时目标 hook 文件不存在, THEN THE Installer SHALL 跳过该文件并继续处理其余 ability

---

### Requirement 10: Hook 安装 — Cursor 平台

**User Story:** As a 开发者, I want APM 将 hook 安装到 Cursor 平台, so that Cursor IDE 能加载自定义 hook。

#### Acceptance Criteria

1. WHEN 安装 hook 类型 ability 到 Cursor 平台时, THE Installer SHALL 读取该 ability 的源文件，将其声明的单个 hook 条目追加到 Hook_Registry_File 中对应事件类型键的数组末尾
2. WHEN Hook_Registry_File 不存在时, THE Installer SHALL 创建该文件，写入包含 version 字段和 hooks 对象的有效 JSON 结构，并在 hooks 对象中写入该 ability 声明的条目
3. WHEN Hook_Registry_File 已包含其他 ability 的 hook 条目时, THE Installer SHALL 保留所有已有事件类型键及其条目，仅在对应事件类型数组中追加新条目
4. IF Hook_Registry_File 中对应事件类型数组已包含与该 ability 相同 command 值的条目, THEN THE Installer SHALL 跳过该重复条目而不产生重复项
5. WHEN check 命令检查 Cursor 平台 hook 时, THE CheckService SHALL 以 (event_type_key, command) 二元组定位该 ability 在 Hook_Registry_File 中的条目，存在即报告 "ok"，不存在即报告 "missing"
6. WHEN 卸载 hook 类型 ability 从 Cursor 平台时, THE Installer SHALL 从 Hook_Registry_File 中对应事件类型数组精确移除与该 ability 源文件声明的 command 值匹配的条目，而不影响其他条目
7. IF 移除条目后 Hook_Registry_File 中所有事件类型数组均为空, THEN THE Installer SHALL 保留文件结构（保留 version 字段和空 hooks 对象）
8. IF Hook_Registry_File 存在但包含无效 JSON, THEN THE Installer SHALL 返回错误信息指明文件解析失败，且不修改该文件

---

### Requirement 11: Show 命令展示 Hook

**User Story:** As a 开发者, I want `apm show` 命令展示 hook 类型 ability, so that 我能查看所有已注册的 hook 信息。

#### Acceptance Criteria

1. WHEN show 命令执行时, THE APM SHALL 在输出中以独立的 "Hooks" 分区列出所有 hook 类型 ability，每条显示 path、description 和 targets 字段
2. WHEN show 命令指定类型过滤且过滤值为 "hook" 时, THE APM SHALL 仅输出 hook 分区而省略其他类型分区
3. IF show 命令指定的类型过滤值不属于已知类型列表（rule、agent、skill、hook、gitignore、preset）, THEN THE APM SHALL 返回错误信息并列出所有已知类型
4. WHEN Ability_Registry 中未注册任何 hook 类型 ability 时, THE APM SHALL 在 Hooks 分区中显示空状态占位文本
5. WHEN show 命令未指定类型过滤时, THE APM SHALL 在输出中同时包含 hook 分区与其他所有类型分区

---

### Requirement 12: State 文档补写

**User Story:** As a 开发者, I want `docs/state/` 文档反映系统最终状态, so that 文档作为系统唯一事实来源保持准确。

#### Acceptance Criteria

1. WHEN 所有代码改动完成后, THE APM 项目 SHALL 包含更新后的 `docs/state/architecture.md`，列出所有当前模块及其职责、数据流与目标平台映射，且不包含已移除模块（Capture、Ingest）
2. WHEN 所有代码改动完成后, THE APM 项目 SHALL 包含 `docs/state/cli-commands.md`，为每个已注册命令描述：命令签名（名称、参数、选项）、正常行为流程、以及每种错误条件的系统响应
3. WHEN 所有代码改动完成后, THE APM 项目 SHALL 包含 `docs/state/abilities-model.md`，描述 Ability_Registry 的完整格式（每种 ability 类型的段名称、必填与可选字段、字段类型与允许值）以及 Preset 引用格式
4. WHEN 所有代码改动完成后, THE APM 项目 SHALL 包含 `docs/state/install-behavior.md`，描述每种 ability 类型（rule、agent、skill、hook、gitignore）的安装、检查、卸载逻辑，包含各平台差异与边界条件处理
5. WHEN 所有代码改动完成后, THE APM 项目 SHALL 包含 `docs/state/gitignore.md`，描述 Marker_Block 的精确格式（起止标记语法）与插入、更新、移除操作规则
6. THE APM 项目 SHALL 确保每份 State 文档覆盖其领域内的 hook 类型 ability 相关行为：`abilities-model.md` 包含 hook 注册格式，`install-behavior.md` 包含 hook 在 Kiro（文件复制）与 Cursor（Hook_Registry_File 合并）平台的安装策略差异
7. WHEN 文档交付时, THE APM 项目 SHALL 确保每份 State 文档中描述的行为与对应代码模块的实际行为一致，不存在文档描述了代码未实现的功能或遗漏了代码已实现的功能


---

## Clarification Round

以下问题面向 design 阶段，需要在进入技术方案设计前确认。

---

### CR-1: Cursor Hook 源文件格式与解析策略

Req 10 AC1 要求"读取源文件中的 hook 条目"并合并到 `hooks.json`。源文件的格式决定了 Installer 的解析逻辑。

**Q:** Hook ability 的源文件应采用什么格式？

- **A)** JSON 格式，结构与 `hooks.json` 中单个事件类型的数组条目一致（Installer 直接 JSON decode 后合并）
- **B)** YAML 格式，与 abilities.yaml 风格统一（Installer 需 YAML→JSON 转换层）
- **C)** JSON 格式，但包含完整的 hooks 对象结构（含事件类型键），Installer 做深度合并
- **D)** Markdown + frontmatter 格式（与 Kiro hook 文件复用同一源文件，Installer 提取 frontmatter 中的结构化数据）

**A:** Hook 源文件直接使用各平台的官方格式——Kiro 平台为 JSON（含 name、version、when、then 结构），Cursor 平台为 hooks.json 片段格式（按事件类型键分组，每条目含 command 字段）。两个平台格式不同，每个 hook ability 可能有两份源文件。

---

### CR-2: Hook Check 的匹配粒度

Req 10 AC5 要求验证 Hook_Registry_File 中"包含该 ability 源文件声明的所有条目且 command 值一致"。但 hook 条目可能包含 command 以外的字段（如 description、events 列表等）。

**Q:** Cursor 平台 hook check 时，判定"一致"的比较范围是什么？

- **A)** 仅比较 command 字段值（存在即视为 ok）
- **B)** 比较 command + 所有其他字段的完整深度相等
- **C)** 比较 command 作为主键定位条目，再逐字段对比其余字段（任一字段不同即 drift）

**A:** Kiro 平台比较文件名（每个 hook 是独立文件），Cursor 平台比较 command 值（Cursor hooks.json 中每个条目的唯一标识就是 command 字段）。需查阅 Cursor 文档确认是否有 name 字段——根据实际格式，Cursor hook 条目仅有 command 字段，因此以 (event_type_key, command) 作为标识。

---

### CR-3: Hook 卸载时的 ability 归属识别

Req 10 AC6 要求"根据源文件声明的 command 值精确移除匹配条目"。如果两个不同的 hook ability 声明了相同的 command 值（不同事件类型下），卸载一个不应影响另一个。

**Q:** 如何确保卸载时精确识别属于当前 ability 的条目？

- **A)** 以 (event_type_key, command) 二元组作为唯一标识，卸载时仅移除匹配的组合
- **B)** 在写入 hooks.json 时为每个条目添加 `_source` 元数据字段标记来源 ability
- **C)** 不做额外标记，卸载时重新读取源文件获取该 ability 声明的所有 (event_type, command) 对，逐一移除

**A:** 每个 hook ability 本身就是一个独立条目（entry）。Kiro 平台每个 hook 是独立文件，卸载即删除文件。Cursor 平台每个 hook ability 的源文件声明了它在 hooks.json 中的条目，卸载时重新读取源文件确定要移除的 (event_type, command) 对。

---

### CR-4: Directory_Duplicate 检测后的行为

Req 2 AC4 要求 skill 安装时执行 Directory_Duplicate 检测并"报告冲突的目录路径"。但未明确检测到冲突后是否继续安装。

**Q:** 检测到 Directory_Duplicate 后，Installer 应如何处理？

- **A)** 仅报告警告信息，继续执行安装（覆盖已有文件）
- **B)** 报告冲突并中止安装，返回非零退出码
- **C)** 交互式询问用户是否继续（但 CLI 工具通常不做交互式确认）
- **D)** 默认中止，但提供 `--force` 选项允许强制覆盖

**A:** B — 报告冲突并中止安装，返回非零退出码。

---

### CR-5: Ability_Registry 解析错误的粒度

Req 1 AC3 定义了文件不存在或无法解析时终止命令。但如果文件整体合法，仅个别 ability 条目格式有误（如缺少 path 字段），行为未明确。

**Q:** 单个 ability 条目格式错误时，应如何处理？

- **A)** 跳过该条目，输出警告，继续处理其余 ability
- **B)** 终止整个命令，报告第一个格式错误的条目
- **C)** 收集所有格式错误后一次性报告，然后终止命令
- **D)** 区分 install（严格，终止）和 check/show（宽松，跳过并警告）

**A:** C — 收集所有格式错误后一次性报告，然后终止命令。
