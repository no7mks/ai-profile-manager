## Requirements Phase — Socratic Review

**日期**: 2026-06-05 16:10

### Q&A

> **Q1**: requirements 是否完整覆盖 goal.md 中全部 9 项目标？
> **A1**: 是。Requirement 1–9 逐一对应 goal 中的 9 项目标：--scope 废弃、global-setup 废弃、bootstrap 扩展、abilities.yaml 变更、show 简化、update 简化、cleanup 文案、代码清理、文档同步。无遗漏。

> **Q2**: Non-scope 是否与 goal 的"不做的事情"一致？是否有 AC 越界？
> **A2**: Introduction 中 4 项 Non-scope 与 goal 完全对齐。检查全部 AC 未发现有涉及新 scope 概念引入、init Agent 逻辑修改、向后兼容 shim、或自动清理 user scope 文件的行为描述。

> **Q3**: Glossary 中定义的术语是否在 AC 中全部被引用？AC 中是否有未定义术语？
> **A3**: 全部 13 个术语在 AC 中至少被引用一次。AC 中使用的领域概念（如 `type:path`、`target`、`baseline`）为通用描述而非专属术语，未发现需额外定义的孤立术语。

> **Q4**: AC 是否包含实现细节（类名、方法签名等）？
> **A4**: Requirement 8（代码清理）提及具体组件名称（UserHomeResolver、ScopeGuard、DeployRootResolver 等）。这些是外部可观察的"系统不应包含的组件"约束，描述的是最终状态而非实现过程。鉴于此 requirement 的用户角色是"开发者"且目标明确是代码清理，组件级粒度在此场景合理，不违反内容边界。

> **Q5**: bootstrap 命令 AC 中的失败策略是否与 goal 中"scaffold 不回滚"决策一致？
> **A5**: AC 7 明确声明"保留已完成的 Scaffold（不回滚）"，与 goal Clarification #2 和约束表中 "scaffold 先完成；ability install 逐项执行" 决策完全对齐。AC 1 确保 scaffold 先于 ability 安装执行。

> **Q6**: abilities.yaml 验证 AC 是否正确反映 fail-fast 语义？
> **A6**: AC 1、2、5 三条共同确保：首个违规（scopes 字段或 global-setup key）即终止解析。AC 4 处理边界情况（bootstrap section 缺失时不报错）。与 goal Clarification #3 "首个违规即抛异常" 一致。

> **Q7**: Requirement 1 中 `--scope project` 也触发 hard fail 是否已明确？
> **A7**: AC 1 括号中明确说明"任何值，包括 `--scope project`"，对应 goal "不做向后兼容 shim（--scope project 不静默通过，一律 hard fail）"。

### 结论

Requirements 完整覆盖 goal 全部目标与关键决策，AC 粒度合理，EARS 格式规范，未发现遗漏或越界问题。文档可进入 GK 校验或 design 阶段。


## Requirements Phase — Gatekeep Log

**校验时间**: 2026-06-05 16:13
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`（严格匹配）
- [x] Introduction 存在，描述 feature 范围，明确 Non-scope
- [x] Glossary 存在且非空（13 项术语）
- [x] Requirements section 存在且包含 9 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 术语在 AC 中被实际使用，无孤立术语
- [x] AC 中领域概念在 Glossary 中有定义
- [x] 术语格式为 `- **Term**: 定义`
- [x] 所有 requirement 包含 User Story 和 Acceptance Criteria
- [x] AC 使用 EARS 语体（THE...SHALL / WHEN...SHALL / IF...THEN...SHALL）
- [x] Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续无跳号
- [x] 内容边界合规（R8 组件名为外部可观察的最终状态约束，非实现路径）
- [x] Socratic Review 存在于 gk-logs.md
- [x] Goal Clarification 5 项决策在 requirements 中体现
- [x] Goal 清晰度：Introduction 概括了 feature 目标
- [x] Non-goal / Scope 边界明确（4 项）
- [x] AC 构成充分验收条件
- [x] 可 design 性：足够信息进入 design 阶段


## Design Phase — Socratic Review

**日期**: 2026-06-05 17:26

### Q&A

> **Q1**: design.md 是否完整覆盖 requirements.md 中全部 9 条 requirement 的所有 AC？
> **A1**: 是。Components and Interfaces 中 16 个组件逐一对应 R1–R9 的变更需求：R1(--scope 拒绝) → HandlesDeployScopeOption.rejectIfScopeOptionPresent()；R2(global-setup 废弃) → GlobalSetupCommand 改为废弃壳；R3(bootstrap 扩展) → BootstrapCommand + AbilityRegistry.validateBootstrapIncludes()；R4(abilities.yaml 变更) → AbilityRegistry.parse() fail-fast；R5(show 简化) → ShowStatusPresenter.lines() 无 scope 标签；R6(update 简化) → AbilityUpdateService 仅 project；R7(cleanup 文案) → CleanupCommand 移除文案；R8(代码清理) → 4 个删除文件 + 组件接口精简；R9(文档同步) → Impact Analysis 文档清单。

> **Q2**: Correctness Properties 是否覆盖了关键的可测试行为？是否存在重要行为未被 property 化？
> **A2**: 10 条 property 覆盖了 R1–R7 的核心行为。R8（代码清理）和 R9（文档同步）属于静态验证（源码/文档扫描），Testing Strategy 中通过 Integration/Smoke Tests 覆盖。无遗漏的可 PBT 化行为。

> **Q3**: 接口签名是否足够清晰，能独立指导 task 实现？是否有参数或返回类型模糊的地方？
> **A3**: 所有 public 方法均标注了参数类型、返回类型和异常声明。DeployRootResolver::resolve() 返回 string，absoluteTargetPath() 接受 string 返回 string；Installer::installTyped() 的 $skipExisting 布尔参数用于 bootstrap 场景跳过已安装项。签名清晰度足以独立实现。

> **Q4**: 设计是否引入了 requirements 范围外的新功能或概念？
> **A4**: 未越界。设计引入 BootstrapInstallService（架构图中出现）实际在 Components 中以 BootstrapCommand 直接调用 Installer 实现，无新抽象层。AbilityRegistry::validateBootstrapIncludes() 是对 RD-3 决策的直接实现（fail-fast 前置校验），在 requirements 范围内。

> **Q5**: Impact Analysis 中的文档清单是否与 Requirement 9 的 AC 逐条对齐？
> **A5**: 对齐。R9.AC1 → deploy-scope.md 重写；R9.AC2 → cli-commands.md 更新；R9.AC3 → abilities-model.md 更新；R9.AC4 → install-behavior.md 更新；R9.AC5 → usage.md 更新；R9.AC6 → init-workflow.md（cursor + kiro）更新。R9.AC7（零残留验证）在 Testing Strategy 的 Smoke Tests 中对应。

> **Q6**: Alternatives Considered 是否覆盖了 RD 讨论中的备选方案？
> **A6**: 方案 A（shim）对应 RD-1 延伸讨论；方案 B（删除 DeployRootResolver）对应 RD-4；方案 D（warn-and-skip）对应 RD-3；方案 E（全局拦截）对应 RD-1。方案 C（渐进废弃）覆盖了 goal 中的 non-goal 决策。5 个方案覆盖了 4 个 RD 讨论点 + goal 中的 hard-fail 决策。

> **Q7**: 设计与现有 state 文档描述的架构是否一致？是否有矛盾？
> **A7**: 与 deploy-scope.md、cli-commands.md、abilities-model.md、install-behavior.md 中描述的现有架构一致。设计明确标注了变更点（移除/重写），不存在与 SSOT 描述的矛盾。变更后的状态将在 R9 文档同步中体现。

### 结论

Design 完整覆盖全部 requirements 与 RD 决策，接口签名清晰可执行，correctness properties 覆盖关键行为，impact analysis 与文档清单对齐 R9。未发现遗漏或越界问题。文档可进入 GK 校验或 tasks 阶段。


## Design Phase — Gatekeep Log

**校验时间**: 2026-06-05 17:31
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] Architecture 图中 `BootstrapInstallService` 节点在 Components 中未定义（BootstrapCommand 直接调用 Installer），已将图中 `BS_NEW --> BSI[BootstrapInstallService] --> INSTALLER` 修正为 `BS_NEW --> INSTALLER`，与组件定义一致

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在
- [x] `## Overview` section 存在
- [x] `## Architecture` section 存在
- [x] `## Components and Interfaces` section 存在
- [x] `## Data Models` section 存在
- [x] `## Impact Analysis` section 存在
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中有对应实现描述（R1-R9 全覆盖）
- [x] 无遗漏的 requirement
- [x] design 未超出 requirements 范围
- [x] Impact Analysis 列出受影响 state 文档（6 份）
- [x] 现有 model/service/CLI 行为变化已描述
- [x] 数据模型变更（abilities.yaml）已说明旧数据兼容（fail-fast + 迁移路径）
- [x] 配置项变更已说明（bootstrap section 替代 global-setup）
- [x] 技术选型有明确理由（4 个 RD 决策引用）
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致
- [x] Socratic Review 存在于 gk-logs.md
- [x] Requirement Discussion 决策（RD-1 至 RD-4）在 design 中体现
- [x] 所有关键技术选型有明确结论和理由
- [x] 接口定义可执行
- [x] Requirements 全覆盖
- [x] Impact 充分评估
- [x] 可 task 化
- [○] Correctness Properties 使用自然语言规约格式（含条件+保证），未显式分列"违反后果"小节——当前格式清晰可测试，未强制修正



## Tasks Phase — Socratic Review

**日期**: 2026-06-05 17:47

### Q&A

> **Q1**: tasks.md 是否覆盖 requirements.md 中全部 9 条 requirement？
> **A1**: 是。逐条追溯：R1 → task 3（3.1-3.4）；R2 → task 4（4.1-4.2）；R3 → task 2（2.1-2.3）；R4 → task 1（1.1-1.7）；R5 → task 5（5.1-5.3）；R6 → task 6（6.1-6.3）；R7 → task 7（7.1-7.2）；R8 → task 8（8.1-8.6）；R9 → task 10（10.1-10.4）。每条 requirement 至少被一个 sub-task 通过 `_Ref:` 引用。

> **Q2**: 任务顺序是否与 AD-1 决策（依赖拓扑 C）一致？
> **A2**: 一致。执行顺序为 task 1（R4）→ task 2（R3）→ task 3/4（R1/R2）→ task 5/6/7（R5-R7）→ task 8（R8）→ task 9（E2E）→ task 10（文档/R9）→ task 11（Review）。固定尾部顺序（E2E → 文档收敛 → Code Review）也已满足。

> **Q3**: 每个功能 sub-task 是否遵循 TDD（RED → GREEN）编排？
> **A3**: 是。所有功能 sub-task（1.1-1.5、2.1-2.2、3.1-3.3、4.1-4.2、5.1-5.2、6.1-6.2、7.1、8.2-8.5）均标注 `RED → GREEN` 及对应的 `--filter` 命令。纯删除类 sub-task（1.6、8.1）和文档类（10.x）无 TDD 要求，符合规则。

> **Q4**: TDG 中同一 wave 内的 task 是否真正可并行？
> **A4**: 检查关键并行点：wave 12 的 3.1 + 3.2 分别修改 HandlesDeployScopeOption.php 和 InvalidScopeExceptionTest（不同文件，无数据依赖）；wave 16 的 4.1 + 4.2 分别修改 GlobalSetupCommand.php 和 InvalidScopeExceptionTest；wave 35 的 9.1 + 9.2 + 9.3 为独立 E2E 脚本；wave 37 的 10.1 + 10.2 + 10.3 为独立文档文件。均满足并行条件。

> **Q5**: sub-task 粒度是否符合"单一职责"？是否有超过 3 个文件的 sub-task？
> **A5**: 大部分 sub-task 修改 1-2 个文件。task 8.1（删除废弃类）涉及 4 个文件删除 + 引用清理，但属于"删除一组紧密耦合的废弃方法"例外。task 3.3 涉及多个命令文件，但为同一模式的重复集成（trait 调用），描述清晰。未发现违规。

> **Q6**: 每个 top-level task 的 sub-task 数量是否在上限内？
> **A6**: task 1 有 8 个（含 Checkpoint），task 8 有 7 个（含 Checkpoint），其余均 ≤ 5 个。全部在 8 个原则上限内，未触达 10 个硬上限。

> **Q7**: 全部 10 条 Correctness Properties 是否都在 tasks 中有对应的 PBT sub-task？
> **A7**: 是。P1 → 3.4；P2+P5 → 1.7；P3+P4 → 2.3；P6+P7 → 5.3；P8+P9 → 6.3；P10 → 7.2。全部 10 条 property 均被覆盖。

### 结论

Tasks 完整覆盖全部 requirements、design 决策和 correctness properties，TDD 编排正确，TDG 并行分析合理，sub-task 粒度合规。Spec Planning 四阶段全部完成。


## Tasks Phase — Gatekeep Log

**校验时间**: 2026-06-05 17:56
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Task Dependency Graph 缺少 task 11（Code Review），已追加 `{ "id": 41, "tasks": ["11"] }` 作为最终 wave

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Implementation Plan: deprecate-scope`（严格匹配）
- [x] `## Overview` section 存在
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（task 11）
- [x] 倒数第二个是文档收敛（task 10）
- [x] 倒数第三个是 E2E 测试（task 9）
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号，sub-task 有层级序号
- [x] 序号连续无跳号
- [x] 每个实现类 sub-task 引用 Requirement 编号（`_Ref:` 格式）
- [x] requirements.md 9 条 requirement 均被至少一个 task 引用
- [x] 引用编号在 requirements.md 中确实存在
- [x] top-level task 按依赖拓扑排序（AD-1 决策 C）
- [x] 无循环依赖
- [x] 并行计划的并行条件成立
- [x] Checkpoint 作为每个 top-level task 最后一个 sub-task
- [x] 验证命令为可执行完整 shell 命令（phpstan + phpunit）
- [x] 至少列出静态分析与单元测试命令，与 PROJECT.md 一致
- [x] 含 state 文档同步的 checkpoint 写明具体 state 文件路径
- [x] 包含 commit 动作
- [x] 功能 sub-task 遵循 RED → GREEN 编排
- [x] 每个 sub-task 满足单一职责
- [x] 无 sub-task 修改超过 3 个文件（Checkpoint 除外）
- [x] 所有 task 均为 mandatory（无 optional 标记）
- [x] 每个 top-level task sub-task 数量（不含 Checkpoint）≤ 10
- [x] E2E 测试 top-level task 存在（task 9）
- [x] E2E 覆盖关键用户场景
- [x] E2E 测试方式与项目类型匹配（CLI → 命令执行）
- [x] Code Review 是最后一个 top-level task
- [x] Code Review 描述为委托 code-reviewer sub-agent
- [x] `## Notes` section 存在
- [x] 提到遵循 spec-execution 流程
- [x] 说明 commit 随 checkpoint 一起执行
- [x] 包含当前 spec 特有执行要点
- [x] Socratic Review 存在于 gk-logs.md
- [x] AD 决策在 tasks 编排中体现（AD-1 至 AD-4）
- [x] Design 全覆盖（16 个组件均有对应 task）
- [x] 可独立执行（sub-task 描述自包含）
- [x] 验收闭环（Checkpoint + E2E + 文档收敛 + Code Review）
- [x] 执行路径无歧义
- [x] TDG section 存在，使用 `{"waves": [...]}` JSON 格式
- [x] TDG 使用 ```json 代码块包裹
- [x] TDG 中 task ID 与 sub-task 编号一致
- [x] wave 顺序反映正确依赖关系
- [x] 所有 leaf sub-task 出现在 TDG 中（修正后）
- [x] Checkpoint sub-task 单独占一个 wave
- [x] 同 wave 内 sub-task 满足并行安全条件（无文件冲突、无逻辑前置）
- [x] 文档收敛 top-level task 存在（task 10）
- [x] 包含 state 文档更新 sub-task
- [x] 包含 manual 文档更新 sub-task
- [x] 包含知识图谱更新 sub-task
- [x] 包含 checkpoint sub-task
- [x] 文档收敛内容与 design.md Impact Analysis 一致
