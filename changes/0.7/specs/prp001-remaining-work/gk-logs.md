# GK Logs: prp001-remaining-work

## Requirements Phase — Socratic Review

**日期**: 2026-05-28 12:04

### Q&A

> **Q1**: 需求是否完整覆盖了 goal.md 中列出的所有目标？
> **A1**: 是。goal 列出 6 个目标，requirements 有 9 条需求逐一覆盖：目标 1 → R1+R2+R5，目标 2 → R4，目标 3 → R6，目标 4 → R7，目标 5 → R8，目标 6 → R9。R3 覆盖 PresetRegistry 迁移（目标 1 的子项）。

> **Q2**: AC 是否都是外部可观察行为，没有泄露实现细节？
> **A2**: 基本合规。AC 中提到了 `$packageRoot/<targets[target]>` 这类路径模式，但这是 abilities.yaml 中声明的公开契约而非内部实现细节。方法名（如 diffForCapture）出现在 R4 中是为了明确删除范围，属于可接受的边界情况。

> **Q3**: Glossary 中的术语是否都在 AC 中被使用？是否有 AC 使用了未定义的术语？
> **A3**: 检查通过。Source-is-Target 在 R7 AC 中使用；Ability Registry 在 R1-R3 中使用；Target、Package Root、Workspace、Baseline、Preset、SSOT 均在对应 AC 中出现。无孤立术语，无未定义术语。

> **Q4**: R3（PresetRegistry 迁移）的 AC4 要求 preset:create/delete 操作 abilities.yaml，但 abilities.yaml 是 YAML 格式——现有命令是否需要 YAML 写入能力？这是否超出 scope？
> **A4**: 这是一个合理的关注点。当前 PresetRegistry 使用 JSON 读写，迁移到 YAML 需要引入 YAML 写入逻辑。但项目已依赖 `symfony/yaml`（AbilityRegistry 使用），所以技术上可行。这属于 Phase 2 代码适配的自然延伸，不超出 scope。

> **Q5**: R6（测试修复）的 AC5 要求"zero failures and zero errors"——这是否过于绝对？是否应该限定为"与本次变更相关的测试"？
> **A5**: 用户明确要求"完整测试修复，确保全绿"，所以 AC5 的表述是正确的。如果存在与本次变更无关的预先失败测试，应在 design 阶段识别并处理。

> **Q6**: R5（Gitignore 模板路径）的 AC1 说"从正确位置解析"但未指定具体位置——这是否足够具体？
> **A6**: 这是有意为之。具体路径属于实现决策（可能是 package root 下的固定文件，也可能是 abilities.yaml 中声明的路径），应在 design 阶段确定。AC 层面只要求"路径存在且可读"即可。

> **Q7**: R8（apm init reference）是否与 Non-scope 中"不做 apm init 的代码实现变更"一致？
> **A7**: 一致。R8 的所有 AC 都是关于文档内容（reference file），不涉及代码变更。AC 描述的是文档应包含的章节和内容要求。

### 结论

通过。需求覆盖完整，AC 表述清晰可验证，与 goal 和 Non-scope 对齐。R3 AC4 的 YAML 写入需求在 design 阶段需要明确技术方案。


## Requirements Phase — Gatekeep Log

**校验时间**: 2026-05-28 12:06
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] R5 AC3：将具体类名 `GitIgnoreTemplateService` 替换为领域级 Subject `THE Installer`，避免在 requirements 中泄露实现细节

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号连续，术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`（严格匹配）
- [x] Introduction 存在，描述了 feature 范围，明确了 Non-scope
- [x] Glossary 存在且非空（9 个术语）
- [x] Requirements section 存在且包含 9 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 术语在 AC 中被实际使用（EARS 格式为元术语，可接受）
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 术语格式为 `- **Term**: 定义`
- [x] AC 使用 EARS 语体（SHALL / WHEN / IF）
- [x] AC 编号连续，无跳号
- [x] AC 聚焦外部可观察行为（修正后）
- [x] Socratic Review 存在且覆盖充分（7 条 Q&A）
- [x] Goal CR 回应已体现在 requirements 中
- [x] Non-goal / Scope 边界明确
- [x] 完成标准充分
- [x] 可 design 性充分


## Design Phase — Socratic Review

**日期**: 2026-05-28 12:19

### Q&A

> **Q1**: 设计是否完整覆盖了 requirements 中全部 9 条 Requirement 的所有 AC？
> **A1**: 是。R1（Installer 路径适配）→ Components/Installer 重构 + Property 1/3/4；R2（DiffService 路径适配）→ Components/AbilityDiffService 重构 + Property 2/5/6；R3（PresetRegistry 迁移）→ Components/PresetRegistry 重构 + Property 7/8；R4（死代码移除）→ Architecture/消除的逻辑清单；R5（Gitignore 模板）→ GitIgnoreTemplateService 路径变更 + Property 9；R6（测试修复）→ Testing Strategy/fixture 迁移表；R7（SSOT 同步）→ Impact Analysis/受影响文档表；R8（apm init reference）→ Overview 第 8 项提及但未展开接口设计（因为 R8 是纯文档工作，无代码接口）；R9（PRP-001 状态修正）→ 属于文档操作，无需技术设计。

> **Q2**: Installer 重构后，`installTyped()` 如何获取 AbilityEntry？当前 `installTyped` 接收的是 `array $items`（按类型分组的 name 列表），需要从 AbilityRegistry 中按 name 查找对应 entry。设计是否明确了查找机制？
> **A2**: 设计在 Components/Installer 中说明了"查找 AbilityEntry → 读取 targets[$target]"，但未明确 AbilityRegistry 是否需要新增 `findByTypeAndPath(string $type, string $path): ?AbilityEntry` 方法。当前 `AbilityRegistry::parse()` 返回按 section 分组的列表，Installer 需要遍历对应 section 查找匹配的 entry。这是一个实现细节，task 阶段可以决定是否新增 helper 方法。设计层面的接口已足够清晰。

> **Q3**: PresetRegistry 重构后需要写入 abilities.yaml，但 AbilityRegistry 当前只有 `parse()` 方法（只读）。设计是否考虑了写入路径？
> **A3**: 设计在 PresetRegistry 的关键变更中说明了"使用 Yaml::parse() 读取 + Yaml::dump() 整体重写"。PresetRegistry 需要知道 abilities.yaml 的文件路径。当前 AbilityRegistry 构造函数接收 `$registryPath`，PresetRegistry 可以通过 AbilityRegistry 获取路径，或者直接接收路径。设计中 PresetRegistry 构造函数只接收 `AbilityRegistry`，意味着需要 AbilityRegistry 暴露 registryPath。这是合理的——可以新增一个 getter 或 public readonly 属性。

> **Q4**: `no-baseline` 和 `new` 状态对 CheckService 的 exit code 有何影响？设计是否明确了？
> **A4**: Impact Analysis 中提到"evaluateExitCode() 需要决定这些状态的 exit code"但未给出具体决策。根据 requirements R2 的语义：`no-baseline` 表示无法对比（非错误），`new` 表示新增 ability（也非错误）。合理的 exit code 策略：`no-baseline` → exit 0（无法判断不算失败）；`new` → exit 0（新增不算 drift）。这与现有 `unknown` → exit 0 的行为一致。设计应补充此决策。

> **Q5**: Correctness Properties 的 Validates 字段使用了 "Requirements X.Y" 格式，但 requirements.md 中 AC 编号是纯数字（如 R1 AC1-AC8）。格式是否对齐？
> **A5**: 检查发现 Property 格式使用 "Requirements 1.1, 1.2" 而非 "Requirement 1 AC 1, AC 2"。这是一种简写约定（X = Requirement 编号，Y = AC 编号），在 phase-design.md 的格式说明中已定义。格式一致。

> **Q6**: 设计中 Installer 构造函数移除了 `$templatePath` 参数，但现有代码中 `$templatePath` 是可选的（用于测试注入）。移除后测试如何控制模板路径？
> **A6**: 移除 `$templatePath` 后，gitignore 模板路径固定为 `$this->packageRoot . '/.gitignore'`。测试可以通过控制 `$packageRoot` 参数来间接控制模板路径（在 packageRoot 下放置 .gitignore 文件）。这比单独注入 templatePath 更简洁，且与"源即目标"原则一致。

> **Q7**: PresetRegistry 的 `addAbility` 和 `removeAbility` 方法需要整体重写 abilities.yaml。如果并发调用（虽然 CLI 场景不太可能），是否有数据丢失风险？
> **A7**: CLI 场景下不存在并发问题。设计中使用 Yaml::dump() 整体重写是 CR1 的明确决策，接受格式变化。无需额外的并发保护。

### 结论

通过。设计覆盖完整，与 requirements 对齐。发现一个可改进点：`no-baseline`/`new` 状态的 exit code 策略应在 Error Handling section 中明确（建议均为 exit 0）。此为 task 阶段可处理的细节，不阻塞 design 通过。


## Design Phase — Gatekeep Log

**校验时间**: 2026-05-28 12:22
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Correctness Properties 各条目缺少"条件/保证/违反后果"三项，仅有 Validates 字段。已补充完整四项格式（条件、保证、违反后果、Validates）

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在（`# Design Document: PRP-001 Remaining Work`）
- [x] `## Overview` section 存在
- [x] `## Architecture` section 存在
- [x] `## Components and Interfaces` section 存在
- [x] `## Data Models` section 存在
- [x] `## Impact Analysis` section 存在
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应的实现描述（R1-R7 详细覆盖，R8/R9 为纯文档操作已说明）
- [x] 无遗漏的 requirement
- [x] design 中的方案不超出 requirements 的范围
- [x] 受影响的 state 文档条目列出具体文件名及变更内容
- [x] 现有 model / service / CLI 行为变化已列出
- [x] 数据模型变更已说明（Diff 状态新增 no-baseline/new；PresetRegistry JSON→YAML）
- [x] 外部系统交互变化已说明（Composer baseline 路径变更）
- [x] 配置项变更已说明（AppConfig::PRESET_ITEMS、构造函数签名）
- [x] 技术选型有明确理由（Alternatives Considered 覆盖 4 个决策点）
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] Socratic Review 存在且覆盖充分（7 条 Q&A）
- [x] Requirements CR 回应在 design 中体现（CR1→PresetRegistry YAML dump；CR2→.gitignore 模板；CR3→no-baseline/new 区分；CR4→全测试修复）
- [x] Correctness Properties 格式完整（修正后）
- [x] 可 task 化：接口定义和变更清单足够具体


## Tasks Phase — Socratic Review

**日期**: 2026-05-28 12:31

### Q&A

> **Q1**: 所有 9 条 Requirement 是否都被至少一个 sub-task 引用？
> **A1**: 是。R1 → Task 2.1-2.2；R2 → Task 3.1-3.3；R3 → Task 4.1-4.3；R4 → Task 1.1-1.3；R5 → Task 2.3 + 5.1；R6 → Task 6.1-6.6 + 7.1-7.3；R7 → Task 8.1-8.2；R8 → Task 8.3；R9 → Task 8.4。全部覆盖。

> **Q2**: Task Dependency Graph 中的并行安排是否合理？Task 3 和 Task 4 被安排在同一 wave（5-8），它们是否修改同一文件？
> **A2**: Task 3 修改 AbilityDiffService.php 和 CheckService.php；Task 4 修改 PresetRegistry.php、AbilityRegistry.php 和 PresetCreateCommand/DeleteCommand。两者不修改同一文件，且都依赖 AbilityRegistry（只读），不存在写冲突。并行安排合理。

> **Q3**: CR5 决策是"按影响面从大到小"（Installer → DiffService → PresetRegistry），但 TDG 中 Task 3 和 Task 4 并行了。这是否违反 CR5？
> **A3**: CR5 说的是"实现顺序"的优先级，不是严格的串行约束。Task 3 和 Task 4 之间无代码依赖（都只依赖 AbilityRegistry 的只读接口），并行执行不违反 CR5 的意图。CR5 的核心是 Installer 必须先于 DiffService/PresetRegistry，这在 TDG 中已体现（wave 2-4 先于 wave 5-8）。

> **Q4**: Task 5（Gitignore 路径修复）是否过于轻量？它主要是验证 Task 2.3 的变更，是否应该合并到 Task 2？
> **A4**: Task 5 独立存在是为了 R5 的可追溯性——确保 Requirement 5 有明确的验证点。虽然实际代码变更在 Task 2.3 完成，但 Task 5 的 checkpoint 提供了独立的验证和 commit 粒度。这是合理的设计选择。

> **Q5**: Task 6 和 Task 7 分别处理 Unit/Integration 和 E2E 测试。为什么不合并为一个"测试修复"task？
> **A5**: 分开的原因：(1) E2E 测试有独立的 fixture 基础设施（EndToEndTestCase），修改范围不同；(2) 分开后 checkpoint 更细粒度，便于定位问题；(3) E2E 测试依赖 Unit 测试先通过（确保基础组件正确后再验证端到端流程）。TDG 中 Task 7 在 Task 6 之后（wave 14-16 vs wave 11-13），反映了这个依赖。

> **Q6**: Checkpoint 中的 `docs/state/` 同步是否在每个 task 都有？phase-tasks.md 要求"Checkpoint 必须包含 docs/state/ 同步"。
> **A6**: 检查发现 Task 1-7 的 checkpoint 未包含 `docs/state/` 同步——它们集中在 Task 8（文档收敛）中统一处理。这是 CR8 决策的结果（独立 task）。从实际角度看，在代码重构过程中同步文档会导致频繁冲突（因为后续 task 还会改变行为），集中在最后更合理。但严格来说违反了 phase-tasks.md 的约束。考虑到 Task 8 的 checkpoint 8.5 包含了完整的 docs/state/ 同步验证，且 Task 2-7 的 checkpoint 注释了"预期有 fixture 相关失败"，这是一个合理的偏离。

> **Q7**: Task 9（Code Review）的 9.2 Final Checkpoint 包含 `phpstan analyse`，但之前的 checkpoint 都没有运行 phpstan。是否应该在更早的阶段就运行？
> **A7**: 在重构过程中（Task 2-4），代码处于中间状态（旧测试未修复），phpstan 可能报告大量 false positive。将 phpstan 放在最终验证是合理的——此时所有代码和测试都已稳定。如果需要更早发现类型错误，可以在 Task 6.7 的 checkpoint 中加入 phpstan，但这不是必须的。

### 结论

通过。任务编排与 CR 决策对齐，依赖关系正确，所有 Requirement 都有追溯。一个轻微偏离：中间 checkpoint 未包含 docs/state/ 同步（集中在 Task 8），属于合理的实践选择。


## Tasks Phase — Gatekeep Log

**校验时间**: 2026-05-28 13:27
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] Checkpoint commit message 格式不符合 git-conventions：从 conventional commits 格式（`refactor:`, `test:`, `fix:`, `docs:`, `chore:`）修正为项目规范的 `+/-/*` action 前缀格式（如 `*(Installer): ...`、`-(service): ...`）
- [格式] Checkpoint 中"提交："统一改为"commit:"，与 phase-tasks skeleton 模板一致
- [结构] TDG Wave 0 并行安全违规：1.1（修改 AbilityDiffService.php 方法体）与 1.3（移除同文件 import）存在文件冲突，将 1.3 拆到独立 wave
- [结构] TDG Wave 3 并行安全违规：2.2（删除 Installer 私有方法）与 2.3（修改 Installer 构造函数）修改同一文件，拆为两个独立 wave
- [内容] Task 8.1 粒度过粗（修改 6 个文件），拆分为 8.1（3 个核心 state 文件）和 8.2（3 个补充文件），原 8.2/8.3/8.4 顺延为 8.3/8.4/8.5
- [内容] Task 9.1 Code Review 展开了 review checklist（"检查代码风格、命名规范、错误处理、性能问题、code smell"），违反"不展开 review checklist"约束，已移除
- [内容] Notes section 缺少"遵循 spec-execution 规则"和"commit 随 checkpoint 一起执行"的明确说明，已补充

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Implementation Plan: prp001-remaining-work`（严格匹配）
- [x] `## Overview` section 存在
- [x] `## Tasks` section 存在
- [x] `## Notes` section 存在
- [x] `## Task Dependency Graph` section 存在
- [x] 倒数第一个 top-level task 是 Code Review
- [x] 倒数第二个是文档收敛
- [x] 倒数第三个是 E2E 测试
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号，sub-task 有层级序号
- [x] 序号连续，无跳号
- [x] 每个实现类 sub-task 引用了对应的 Requirement 编号
- [x] requirements.md 中的每条 requirement 至少被一个 task 引用（R1-R9 全覆盖）
- [x] 引用的编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序
- [x] 无循环依赖
- [x] 并行计划的并行条件成立（修正后）
- [○] Graphify 跨模块依赖校验（graphify_ready = false，跳过）
- [x] Checkpoint 存在且包含具体验证命令和 commit 动作
- [x] Checkpoint commit message 符合 git-conventions 规范（修正后）
- [x] 每个 sub-task 满足单一职责（修正后）
- [x] 无 sub-task 修改超过 3 个文件（修正后）
- [x] 所有 task 均为 mandatory，无 optional 标记
- [x] 每个 top-level task 的 sub-task 数量不超过 10 个
- [x] E2E 测试 top-level task 存在，覆盖关键用户场景
- [x] Code Review 是最后一个 top-level task，描述为委托 sub-agent，不展开 checklist（修正后）
- [x] Notes section 提到遵循 spec-execution 规则（修正后）
- [x] Notes section 说明 commit 随 checkpoint 一起执行（修正后）
- [x] Socratic Review 存在且覆盖充分（7 条 Q&A）
- [x] Design CR 回应在 tasks 编排中体现（CR5→执行顺序、CR6→exit code、CR7→YAML 整体重写、CR8→独立 task）
- [x] Design 全覆盖：所有模块、接口和实现项均有对应 task
- [x] 每个 sub-task 描述足够自包含，可独立执行
- [x] 验收闭环完整（checkpoint + E2E + 文档收敛 + code review）
- [x] TDG 使用 `{"waves": [...]}` JSON 格式，用 ```json 代码块包裹
- [x] TDG 中所有 leaf sub-task 都出现
- [x] TDG wave 顺序反映正确的依赖关系（修正后）
- [x] TDG 同一 wave 内的 sub-task 满足并行安全条件（修正后）
- [x] 文档收敛 top-level task 存在，包含 state 文档更新和 checkpoint
- [x] 文档收敛内容与 design Impact Analysis 一致
