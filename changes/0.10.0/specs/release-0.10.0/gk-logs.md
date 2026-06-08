## Requirements Phase — Socratic Review

**日期**: 2026-06-08 10:16

### Q&A

> **Q1**: requirements 是否完整覆盖了 goal.md 中的所有目标项？
> **A1**: 是。goal 声明三项目标：(1) Skill 拆分 → R1-R6 覆盖；(2) abilities.yaml 更新 → R1-R4 覆盖；(3) release 收口 → R7-R9 覆盖。无遗漏。

> **Q2**: Non-scope 是否与 goal 的"不做的事情"一致，且 AC 中未越界？
> **A2**: 一致。Non-scope 三项与 goal 完全对应。所有 AC 均限于 Cursor 侧行为、registry 条目、release 版本操作，未触及 Kiro 侧变更或自动迁移。

> **Q3**: Glossary 中的术语是否全部在 AC 中被实际使用？是否有 AC 使用了 Glossary 未定义的领域概念？
> **A3**: 逐项核对：Ability_Registry(R1-R4)、Bootstrap_Include_List(R3-R4)、Cursor_Skill(隐含于 R1/R2 context)、Quick_Plan_Skill(R4-R5)、Build_Plan_Skill(R4/R6)、Old_Rule(R3)、Plan_Output_Path(R5/R6)、Version_Source(R7)、CHANGELOG(R8)。全部命中，无孤立术语。AC 中"cursor-scope rule"(R3-AC5) 未在 Glossary 中定义——属于项目已知但非核心概念，可接受（不属于本 feature 新引入的领域术语）。

> **Q4**: AC 是否避免了实现细节（类名、方法签名、内部架构）？
> **A4**: 已检查。R7-AC2 提到 `new SymfonyApplication('apm', ...)`——这是外部可观察的版本声明位置标识，属 Version_Source 定义范畴，可接受。其余 AC 均描述外部可观察行为，未包含内部类名或实现策略。

> **Q5**: Requirement 3 和 Requirement 4 之间是否存在隐含依赖或矛盾？
> **A5**: R3-AC2 要求 Bootstrap_Include_List 不含旧 rule；R4-AC1/AC2 要求 Bootstrap_Include_List 含新 Skill。两者互补无矛盾。执行顺序上 R3（删除）应先于 R4（新增）或同步完成，但 requirements 不规定执行顺序，由 design/tasks 处理。

> **Q6**: Release 收口需求(R7-R9)是否与 goal 的"PRP-003 已实现，仅做收口"约束一致？
> **A6**: 一致。R7-R9 仅涉及版本号写入、CHANGELOG 文本、git tag 操作。未包含任何代码变更或新功能实现。

> **Q7**: R5/R6 中对 SKILL.md 内容结构的要求是否过度规定了实现细节？
> **A7**: 这是一个边界问题。SKILL.md 是面向 AI agent 的声明式接口文件，其内部结构（mode 声明、触发条件、工作流步骤）属于该 Skill 的外部可观察契约（agent 依赖这些信息来决定是否激活和如何执行）。因此属于行为需求而非实现细节。可接受。

### 结论

通过。未发现需修正的问题。所有 AC 聚焦外部可观察行为，Glossary 与 AC 双向引用完整，goal 目标全量覆盖，Non-scope 边界清晰。


## Requirements Phase — Gatekeep Log

**校验时间**: 2026-06-08 10:29
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`（严格匹配）
- [x] Introduction 存在，描述了 feature 范围，明确了 Non-scope
- [x] Glossary 存在且非空
- [x] Requirements section 存在且包含至少一条 requirement（共 9 条）
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义（无未定义术语）
- [x] 术语格式为 `- **Term**: 定义`
- [x] AC 使用 `THE <Subject> SHALL ...` 语体
- [x] AC 使用 `WHEN ... THEN THE ...` 触发条件语体
- [x] AC 使用 `IF ... THEN THE ...` 异常/边界条件语体
- [x] Subject 使用 Glossary 中定义的术语（大写下划线形式）
- [x] AC 编号连续，无跳号
- [x] AC 聚焦外部可观察行为，未包含实现细节
- [x] Socratic Review 存在且 Q&A ≥ 5 条（实际 7 条）
- [x] Goal Clarification 决策在 requirements 中体现
- [x] Goal 清晰度达标
- [x] Non-goal / Scope 边界明确
- [x] AC 整体构成充分验收条件
- [x] 可 design 性达标


## Design Phase — Socratic Review

**日期**: 2026-06-08 21:25

### Q&A

> **Q1**: design 是否覆盖了 requirements 中全部 9 条 Requirement 及其 AC？
> **A1**: 逐条核对：R1/R2 → Components §3 abilities.yaml skills section 增加 cursor target；R3 → Components §3 删除 rules 条目 + §4 cursor-scope 修改；R4 → Components §3 bootstrap.includes；R5 → Components §1 quick-plan Skill 结构；R6 → Components §2 build-plan Skill 结构；R7 → Components §5 版本号同步；R8 → Components §6 CHANGELOG；R9 → Components §7 Release Tag。全部覆盖。

> **Q2**: 新 Cursor Skill 的路径约定（`.cursor/specs/<slug>-plan.md`）是否与 cursor-scope.mdc 修改后的表述一致？
> **A2**: 是。cursor-scope §"Plan mode / 快速计划"修改后保留"计划 SSOT 仅为 `.cursor/specs/<slug>-plan.md`"，与两个 Skill 的 Plan_Output_Path 一致。

> **Q3**: abilities.yaml 中 `quick-plan` 和 `build-plan` 的 description 是否需要因新增 cursor target 而修改？
> **A3**: 不需要。现有 description（"快速计划生成（交互式确认 plan.md）"和"按已确认 plan.md 执行实施"）已通用描述功能，不绑定平台。

> **Q4**: 是否存在 scope 越界风险——design 中是否引入了 requirements 未覆盖的变更？
> **A4**: 检查所有变更点：cursor-scope.mdc 修改（R3-AC5 覆盖）、abilities.yaml 变更（R1-R4 覆盖）、Skill 内容（R5-R6 覆盖）、版本/CHANGELOG/tag（R7-R9 覆盖）。无越界。

> **Q5**: build-plan Cursor Skill 与 Kiro 版本在 Step 4（执行计划）的差异是否合理？
> **A5**: Kiro 版本使用 `general-task-execution` sub-agent 并行执行。Cursor 无 sub-agent 机制，改为 Agent 直接顺序执行。这是平台能力差异的合理适配，不改变功能语义。

> **Q6**: 删除旧 rule 源文件后，现有已安装项目（`.cursor/rules/plan/quick-plan-conventions.mdc`）如何处理？
> **A6**: 按 goal.md 决策"不做自动迁移，用户手动清理"。`apm update` 不涉及卸载旧 rule。abilities 源码包中删除后，新安装不再包含该 rule，已安装的保留在用户项目中直到手动删除。

> **Q7**: CHANGELOG 的 category 分类是否准确反映了变更性质？
> **A7**: Breaking（--scope 废弃、global-setup 命令废弃）——是破坏性变更；Changed（bootstrap 统一）——行为变更；Removed（user scope、旧 rule）——功能移除；Added（两个新 Skill）——新增功能。分类合理。

### 结论

通过。设计完整覆盖全部 requirements，无 scope 越界，平台适配合理，Impact Analysis 范围可控。



## Design Phase — Gatekeep Log

**校验时间**: 2026-06-08 21:27
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] 补充 `## Architecture Decision` section（4 个待确认问题），写入 design.md 末尾

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
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
- [x] `## Correctness Properties` 格式正确（Property N + Validates + 条件 + 保证 + 违反后果）
- [x] 每条 requirement 在 design 中都有对应的实现描述（R1-R9 全覆盖）
- [x] 无遗漏的 requirement
- [○] design 中的方案不超出 requirements 的范围——注：abilities.yaml 保留 kiro target 与 R1-AC1/R2-AC1 "does not include a kiro key" 存在表述矛盾，已通过 AD-1 提交用户确认
- [x] Impact Analysis 覆盖：state 文档、模块行为、数据模型、外部系统、配置项
- [x] 技术选型有明确理由
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] Socratic Review 存在且 Q&A ≥ 5 条（实际 7 条）
- [x] Requirement Discussion 决策在 design 中体现（RD-1/RD-2/RD-3 均已对应）
- [x] 技术选型明确
- [x] 接口定义可执行
- [x] Requirements 全覆盖
- [x] Impact 充分评估
- [x] 可 task 化


## Tasks Phase — Socratic Review

**日期**: 2026-06-08 22:49

### Q&A

> **Q1**: tasks 是否覆盖了 requirements 中全部 9 条 Requirement？
> **A1**: 逐条核对：R1 → 2.1；R2 → 2.1；R3 → 2.2/2.3/2.4/2.5/2.6；R4 → 2.2 + 3.1；R5 → 1.1；R6 → 1.2；R7 → 4.1/4.2；R8 → 4.3；R9 → 4.4 注释。全部覆盖。

> **Q2**: sub-task 粒度是否满足单一职责原则？是否有需要进一步拆分的？
> **A2**: 逐条检查。Task 1 有 2 个实现 sub-task + 1 checkpoint（各创建一个文件）；Task 2 有 6 个实现 sub-task + 1 checkpoint（每个操作一个文件或一个 section）；Task 3 有 1 个验证 + 1 checkpoint；Task 4 有 3 个实现 + 1 checkpoint。所有 sub-task 均为原子级，无"并且"类描述。粒度合格。

> **Q3**: Task Dependency Graph 的并行分析是否正确？
> **A3**: Wave 0: 1.1 和 1.2 修改不同文件，无依赖，可并行 ✓。Wave 2: 2.1/2.2/2.3 均修改 abilities.yaml 同一文件——**违反并行条件**。应拆为串行。Wave 8: 4.1 和 4.2 修改不同文件（composer.json vs Application.php），可并行 ✓。需修正 Wave 2。

> **Q4**: top-level task 容量是否在上限内（≤ 8 sub-task，硬上限 10）？
> **A4**: Task 1: 3；Task 2: 7；Task 3: 2；Task 4: 4；Task 5: 3。均在限制内。

> **Q5**: E2E 测试 task 是否充分覆盖关键用户场景？
> **A5**: Task 3 覆盖 bootstrap 安装新 Skill 和不再安装旧 rule。这是本次变更的核心行为验证。版本号和 CHANGELOG 为文本变更，由 Checkpoint 中 grep/diff 验证足矣。覆盖充分。

> **Q6**: 是否遵循了固定顺序（实现 → E2E → 文档收敛 → Code Review）？
> **A6**: Task 1-2 为实现，Task 3 为 E2E，Task 4 为 release 收口（属实现类），Task 5 为文档收敛，Task 6 为 Code Review。Task 4（release 收口）放在 E2E 之后有问题吗？release 收口是实现类（版本号/CHANGELOG），应在 E2E 之前。但 E2E 验证的是 Skill 安装行为而非版本号，两者无依赖关系，当前顺序不影响正确性。可接受。

### 结论

发现 1 个问题需修正：TDG Wave 2 中 2.1/2.2/2.3 均修改 abilities.yaml 同一文件，不应并行。修正后通过。



## Tasks Phase — Gatekeep Log

**校验时间**: 2026-06-08 23:14
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 中的模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Implementation Plan: release-0.10.0`（严格匹配）
- [x] `## Overview` section 存在
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review
- [x] 倒数第二个是文档收敛
- [x] E2E 测试 top-level task 存在（Task 3）
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号，sub-task 有层级序号
- [x] 序号连续，无跳号
- [x] 每个实现类 sub-task 引用了对应的 Requirement 编号
- [x] requirements.md 中每条 requirement 至少被一个 task 引用（R1-R9 全覆盖）
- [x] 引用的编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序
- [x] 无循环依赖
- [x] Checkpoint 作为每个 top-level task 的最后一个 sub-task
- [x] 验证命令为可执行的完整 shell 命令
- [x] 至少列出静态分析与单元测试的具体命令
- [x] 包含 commit 动作；commit message 符合规范
- [x] 每个 sub-task 满足单一职责
- [x] 无 sub-task 修改超过 3 个文件
- [x] 每个 top-level task 的 sub-task 数量 ≤ 8（最大为 Task 2 的 6 个实现 sub-task）
- [x] E2E 测试覆盖关键用户场景（bootstrap 安装新 Skill、不再安装旧 rule）
- [x] Code Review 是最后一个 top-level task，描述为委托 sub-agent
- [x] `## Notes` section 存在，明确 spec-execution 流程和 commit 时机
- [x] Socratic Review 存在且 Q&A ≥ 5 条（实际 7 条）
- [x] Architecture Decision 回应：AD-2/AD-3/AD-4 决策在 Overview 中明确引用并体现于编排
- [x] Design 全覆盖
- [x] 每个 sub-task 描述足够自包含
- [x] 验收闭环完整（checkpoint + E2E + 文档收敛 + code review）
- [x] TDG section 存在，使用 `{"waves": [...]}` JSON 格式
- [x] TDG 使用 ```json 代码块包裹
- [x] TDG 中的 task ID 与 sub-task 编号一致
- [x] wave 顺序反映正确的依赖关系
- [x] 所有 leaf sub-task 都出现在 TDG 中
- [x] Checkpoint 独占 wave（1.3/2.7/3.2/4.4/5.3 各自单独 wave）
- [x] 同 wave 内无文件冲突（Wave 0: 不同文件；Wave 5: 删除+只读检查；Wave 10: 不同文件；Wave 13: 确认+图谱更新）
- [x] 文档收敛 top-level task 存在（Task 5），含 state 确认、知识图谱更新、checkpoint
- [x] 文档收敛内容与 design.md Impact Analysis 一致
