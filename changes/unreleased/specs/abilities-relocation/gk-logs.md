## Requirements Phase — Socratic Review

**日期**: 2026-05-20 15:34

### Q&A

> **Q1**: 所有 goal 中的目标是否都被 requirements 覆盖？
> **A1**: 是。Goal 的 4 个目标分别对应：CLI 适配（Req 1-4）、功能精简（Req 6-7）、Hook 支持（Req 8-11）、State 文档补写（Req 12）。Gitignore 管理（Req 5）也被覆盖。

> **Q2**: Glossary 中的术语是否都在 AC 中被实际使用？是否有 AC 中使用但未定义的术语？
> **A2**: 所有 Glossary 术语均在 AC 中被引用。GitignoreManager 未在 Glossary 中定义但在 AC 中使用——已补入 Glossary。

> **Q3**: Requirement 6 和 7 是否存在重叠？Ingest 是否已被 Capture 的 Req 6 AC1 覆盖？
> **A3**: Req 6 AC1 列出了 `ingest` 命令，与 Req 7 存在部分重叠。但分开保留是合理的——goal 中明确将 capture 和 ingest 作为两个独立概念。

> **Q4**: Non-scope 是否与 goal 一致？是否有 scope 越界风险？
> **A4**: Non-scope 完全对齐 goal 中的"不做的事情"。无 scope 越界风险。

> **Q5**: Req 4 AC5 提到 "force 选项"——这是否引入了未在其他 requirement 中定义的新功能？
> **A5**: force 选项是 detailer 补充的边界条件。作为 requirement 层面，描述"如果存在 drift 且未指定 force"的行为是合理的——它定义了安全卸载的边界条件。具体交互在 design 阶段细化。

> **Q6**: Req 3 AC5 提到 "ComposerBaselineResolver 返回 null"——这是否属于实现细节？
> **A6**: 严格来说属于实现细节，但它在当前 SSOT 中已作为模块存在，且 AC 描述的是外部可观察行为（报告 unknown 状态）。保持现状。

> **Q7**: Req 1 中已知 section 列表是否应包含 hooks？
> **A7**: 是的，Req 8 新增了 hooks section，Req 1 AC1 的列表需要包含 hooks。已修正。

### 结论

发现两个问题并已修正：
1. Glossary 补入 GitignoreManager 定义
2. Req 1 AC1 的已知 section 列表补入 hooks

---

## Requirements Phase — Gatekeep Log

**校验时间**: 2026-05-20 15:45
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [语体] Req 12 AC6/AC7：Subject "THE 每份 State 文档" 不是 Glossary 术语，改为 "THE APM 项目 SHALL 确保每份 State 文档..."，与 AC1-5 保持一致

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Requirement 编号 1-12 连续，术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`
- [x] Introduction 存在，描述了 feature 范围，明确了 Non-scope
- [x] Glossary 存在且非空（15 个术语）
- [x] Requirements section 存在且包含 12 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 中所有术语在 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 术语格式为 `- **Term**: 定义`
- [x] AC 使用 THE/WHEN/IF ... SHALL 格式
- [x] Subject 使用 Glossary 中定义的术语（修正后）
- [x] AC 编号连续，无跳号（65 条 AC）
- [○] 内容边界：Req 3 AC5 含 ComposerBaselineResolver（实现细节），Socratic Review 已审议决定保留
- [x] Goal CR 回应：goal.md 3 个 CR 决策均在 requirements 中体现
- [x] Goal 清晰度：Introduction 一句话可概括 feature 目标
- [x] Non-goal / Scope 边界：4 项 Non-scope 与 goal.md 完全对齐
- [x] 完成标准：65 条 AC 构成充分验收条件
- [x] 可 design 性：hook 注册格式、安装策略、check 逻辑等信息充分


---

## Design Phase — Socratic Review

**日期**: 2026-05-20 16:16

### Q&A

> **Q1**: 所有 requirements 中的 12 条 Requirement 是否都在 design 中有对应设计覆盖？
> **A1**: 是。Req 1（列表解析）→ AbilityRegistry；Req 2（安装）→ Installer 修改 + HookInstaller；Req 3（状态检查）→ CheckService 修改 + HookChecker；Req 4（卸载）→ Installer.uninstallTyped + HookInstaller.uninstall*；Req 5（Gitignore）→ GitignoreManager 不变；Req 6-7（Capture/Ingest 移除）→ ConsoleRegistration 修改 + 文件删除；Req 8（Hook 注册）→ AbilityRegistry + abilities.yaml hooks 段格式；Req 9（Hook Kiro）→ HookInstaller.installKiro/uninstallKiro + HookChecker.checkKiro；Req 10（Hook Cursor）→ HookInstaller.installCursor/uninstallCursor + HookChecker.checkCursor；Req 11（Show Hook）→ ShowCommand 修改；Req 12（State 文档）→ Impact Analysis 中列出需新建/更新的文档。

> **Q2**: 接口签名是否足够清晰，能让 task 独立执行？
> **A2**: 是。每个新增类（AbilityRegistry、HookInstaller、HookChecker）都有完整的方法签名（参数类型、返回类型、异常类型）。修改类（Installer、CheckService、ShowCommand）列出了具体变更点。

> **Q3**: 模块间是否存在循环依赖？
> **A3**: 不存在。依赖方向为：Command → Installer/CheckService → AbilityRegistry/HookInstaller/HookChecker → 无外部依赖。AbilityRegistry 是纯解析层，不依赖 Installer。

> **Q4**: Cursor Hook 源文件格式（`event` + `entry`）是否与 CR-1 中的 Clarification 回答一致？
> **A4**: CR-1 回答"Cursor 平台为 hooks.json 片段格式（按事件类型键分组，每条目含 command 字段）"。设计中的 `{"event": "preToolUse", "entry": {"command": "..."}}` 格式是对此的具体实现——event 字段指定事件类型键，entry 字段是该键下的条目。一致。

> **Q5**: Impact Analysis 是否覆盖了所有受影响面？是否有遗漏？
> **A5**: 覆盖了 state 文档、模块行为、数据模型、外部系统交互、配置项。额外检查：测试文件也需要更新（Capture 相关测试删除、新增 Hook 测试），这在 Testing Strategy 中已覆盖。PresetRegistry 需要支持 hooks 键——当前 preset 的 includes 格式为 `rule:xxx`、`skill:xxx`，未来可能需要 `hook:xxx`，但 requirements 中 preset 格式未变更，不在本次范围内。

> **Q6**: AbilityRegistry 与现有 Installer.listAvailableItems() 的关系是否清晰？
> **A6**: 是。设计明确选择了 Alt 3（AbilityRegistry 替代 listAvailableItems），Installer 不再自行解析 abilities.yaml，而是接收 AbilityRegistry 注入。listAvailableItems() 方法将委托给 AbilityRegistry.parse() 的结果。

> **Q7**: Directory_Duplicate 检测的具体判定逻辑是否在设计中明确？
> **A7**: 设计中 Installer 修改部分提到"Directory_Duplicate 中止"，但未详细描述判定逻辑。根据 Req 2 AC4，检测条件是"skill 目标目录已存在"。这在现有 Installer 中已有实现（`isInstalledOnTarget` 方法），设计保持现有逻辑不变，仅确保 hook 类型不触发此检测（hook 不是目录级安装）。

### 结论

设计覆盖完整，接口清晰，无循环依赖。两个 CR 问题需用户确认后方可进入 tasks 阶段。


---

## Design Phase — Gatekeep Log

**校验时间**: 2026-05-20 16:47
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Correctness Properties section：原为编号列表格式，改为 `### Property N: <名称>` + 条件/保证/违反后果三项结构，符合 design 结构校验要求

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
- [x] 每条 requirement（1-12）在 design 中都有对应实现描述
- [x] 无遗漏的 requirement
- [x] design 方案不超出 requirements 范围
- [x] 受影响的 state 文档条目明确（architecture.md + 4 份新建）
- [○] graphify 辅助识别受影响范围：graphify_ready = false，跳过
- [x] 现有模块行为变化已列出（Installer、CheckService、ShowCommand）
- [x] 数据模型变更已说明（abilities.yaml 新增 hooks section，向后兼容）
- [x] 外部系统交互变化已列出（.cursor/hooks.json、.kiro/hooks/）
- [x] 配置项变更已列出（KNOWN_ABILITY_TYPES）
- [x] 技术选型有明确理由（Alternatives Considered）
- [x] 接口签名足够清晰（完整 PHP 方法签名含参数类型和返回类型）
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] Requirements CR 回应：requirements CR-1~5 决策在 design 中体现
- [x] 技术选型明确
- [x] 接口定义可执行
- [x] Requirements 全覆盖
- [x] Impact 充分评估
- [x] 可 task 化

---

## Design Phase — Gatekeep Log (Re-validation)

**校验时间**: 2026-05-20 16:55
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Correctness Properties：7 个 Property 均缺少 `**Validates: Requirements X.Y**` 引用行。已为每个 Property 添加对应的 Requirements 映射：
  - Property 1 (Registry Completeness) → Requirements 1.1, 1.3
  - Property 2 (Type Determinism) → Requirements 1.1, 1.4
  - Property 3 (Install Idempotence) → Requirements 2.1, 10.4
  - Property 4 (Uninstall Precision) → Requirements 4.1, 10.6
  - Property 5 (Check Consistency) → Requirements 3.1, 3.4
  - Property 6 (Capture Removal Completeness) → Requirements 6.4, 7.4
  - Property 7 (Validation Exhaustiveness) → Requirements 1.3, 8.6

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
- [x] Correctness Properties 使用 `### Property N: <名称>` 格式
- [x] 每个 Property 包含 Validates、条件、保证、违反后果四项
- [x] 每条 requirement（1-12）在 design 中都有对应实现描述
- [x] 无遗漏的 requirement
- [x] design 方案不超出 requirements 范围
- [x] 受影响的 state 文档条目明确（architecture.md + 4 份新建）
- [○] graphify 辅助识别受影响范围：graphify_ready = false，跳过
- [x] 现有模块行为变化已列出（Installer、CheckService、ShowCommand）
- [x] 数据模型变更已说明（abilities.yaml 新增 hooks section，向后兼容）
- [x] 外部系统交互变化已列出（.cursor/hooks.json、.kiro/hooks/）
- [x] 配置项变更已列出（KNOWN_ABILITY_TYPES）
- [x] 技术选型有明确理由（Alternatives Considered）
- [x] 接口签名足够清晰（完整 PHP 方法签名含参数类型和返回类型）
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] Requirements CR 回应：requirements CR-1~5 决策在 design 中体现
- [x] 技术选型明确
- [x] 接口定义可执行
- [x] Requirements 全覆盖
- [x] Impact 充分评估
- [x] 可 task 化

---

## Tasks Phase — Socratic Review

**日期**: 2026-05-20 17:20

### Q&A

> **Q1**: tasks 是否完整覆盖了 design 中的所有实现项？
> **A1**: 是。AbilityRegistry（Task 2）、HookInstaller（Task 3）、HookChecker（Task 4）、Installer 修改（Task 2.2 + Task 5）、CheckService 修改（Task 4.2）、ShowCommand（Task 6）、ConsoleRegistration/Capture 移除（Task 1）、State 文档（Task 8）。所有 design 中列出的新增/修改/删除模块均有对应 task。

> **Q2**: task 之间的依赖顺序是否正确？
> **A2**: 是。Task 1（移除）无前置依赖；Task 2（AbilityRegistry）依赖 Task 1 完成后代码库干净；Task 3（HookInstaller）依赖 Task 2 提供 AbilityEntry 类型；Task 4（HookChecker + CheckService）依赖 Task 3 的 HookInstaller 存在；Task 5（Installer 集成）依赖 Task 2-4 的新模块；Task 6（ShowCommand）依赖 Task 2 的 AbilityRegistry。顺序正确。

> **Q3**: 每个 task 的粒度是否合适？
> **A3**: 合适。每个 sub-task 对应一个可独立完成的实现单元（一个类或一组紧密相关的修改），且包含 test-first 步骤。Checkpoint 粒度为每个 top-level task 一次，与 commit 对齐。

> **Q4**: checkpoint 是否覆盖了关键阶段？
> **A4**: 是。每个 top-level task（1-6, 8）都有 checkpoint sub-task，包含 `./vendor/bin/phpunit` 验证 + `docs/state/architecture.md` 更新 + commit。

> **Q5**: Design CR 决策是否在 tasks 编排中体现？
> **A5**: 是。CR-3（先移除后新增）→ Task 1 在最前；CR-4（全量测试 + grep）→ Task 1.2；CR-5（一步到位）→ Task 2.1 + 2.2 在同一 top-level task；CR-6（单个 task 统一补写）→ Task 8。

> **Q6**: E2E 测试是否覆盖了关键用户场景？
> **A6**: 是。覆盖了：Capture/Ingest 命令不可达（7.1）、hook 完整生命周期 Kiro（7.2）、hook 完整生命周期 Cursor（7.3）、show 命令（7.4）、错误处理（7.5）。这些对应 Req 6/7/9/10/11/1/8 的核心场景。

> **Q7**: Requirement 追溯是否完整？每条 Requirement 是否至少被一个 task 引用？
> **A7**: 逐条检查：Req 1（Task 2.1, 2.2）✓、Req 2（Task 2.2, 5.1）✓、Req 3（Task 4.2）✓、Req 4（Task 5.2）✓、Req 5 未被引用——Gitignore 管理在 design 中标注为"不变"，但 requirements 中仍有 Req 5。需要确认：design 明确 GitignoreManager 不变，tasks 中无需新增实现。但 Req 5 的 AC 描述的是现有行为，不需要新代码。因此 Req 5 属于"已实现，无需新 task"的情况。可在 Notes 中说明。Req 6（Task 1.1, 1.2）✓、Req 7（Task 1.1, 1.2）✓、Req 8（Task 2.1）✓、Req 9（Task 3.1, 4.1）✓、Req 10（Task 3.2, 4.1, 5.1, 5.2）✓、Req 11（Task 6.1）✓、Req 12（Task 8.1）✓。

### 结论

发现一个问题：Req 5（Gitignore 管理）未被任何 task 引用。但 design 明确 GitignoreManager 不变，Req 5 描述的是现有已实现行为。在 Notes 中补充说明即可。




---

## Tasks Phase — Gatekeep Log

**校验时间**: 2026-05-25 15:28
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] Checkpoint commit message（Task 1.3, 2.3, 3.3, 4.3, 5.3, 6.2, 8.4）：使用了 conventional commit 格式（`feat:`、`refactor:`、`docs:`），不符合 `git-conventions.md` 规定的 `<action>(scope): description` 格式。已全部修正为 `+`/`-`/`*` 前缀格式
- [结构] Task Dependency Graph Wave 11：原将 5.1 和 5.2 放入同一 wave，但两者都修改 Installer 类（5.1 修改 installTyped，5.2 修改 uninstallTyped + 新增 --force），违反"无文件冲突"并行安全条件。已将 5.2 拆到独立 wave（id 12），后续 wave id 顺延，TDG 总计 21 个 wave

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Requirement 编号 1-12 均存在于 requirements.md）
- [x] checkbox 语法正确（`- [ ]` / `- [x]`）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Implementation Plan: abilities-relocation`
- [x] `## Overview` section 存在
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（Task 9）
- [x] 倒数第二个是文档收敛（Task 8）
- [x] 倒数第三个是 E2E 测试（Task 7）
- [x] 所有 task 使用 `- [ ]` / `- [x]` checkbox 语法
- [x] top-level task 有序号（1-9），sub-task 有层级序号
- [x] 序号连续，无跳号
- [x] 每个实现类 sub-task 引用了对应的 Requirement 编号
- [x] requirements.md 中的每条 requirement 至少被一个 task 引用（Req 5 在 Notes 中说明为已实现无需新 task）
- [x] 引用的编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序
- [x] 无循环依赖
- [○] Graphify 跨模块依赖校验：graphify_ready = false，跳过
- [x] checkpoint 存在（每个 top-level task 1-6, 8 均有）
- [x] checkpoint 包含具体验证命令（`./vendor/bin/phpunit`）和 commit 动作
- [x] commit message 符合 git-conventions.md 格式（修正后）
- [x] 推荐 test-first 顺序已遵循（RED→GREEN 模式）
- [x] 每个 sub-task 足够具体，可独立 session 执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] E2E 测试 top-level task 存在（Task 7）
- [x] E2E 测试覆盖关键用户场景（5 个场景覆盖 Req 6/7/9/10/11/1/8）
- [x] E2E 测试场景描述具体，可执行
- [x] Code Review 是最后一个 top-level task（Task 9）
- [x] Code Review 描述为"委托给 code-reviewer sub-agent 执行"
- [x] Code Review 不展开 review checklist
- [x] `## Notes` section 存在
- [x] 明确提到遵循 `spec-execution` 规则
- [x] 明确说明 commit 随 checkpoint 一起执行
- [x] 包含当前 spec 特有的执行要点
- [x] Tasks Phase — Socratic Review 存在于 gk-logs.md
- [x] Design CR 回应在 tasks 编排中体现（CR-3~6 均有对应）
- [x] Design 全覆盖（所有新增/修改/删除模块均有对应 task）
- [x] 每个 sub-task 描述足够自包含
- [x] checkpoint + E2E 测试 + 文档收敛 + code review 构成完整验收闭环
- [x] 排序和依赖关系清晰
- [x] TDG section 存在，使用 `{"waves": [...]}` JSON 格式
- [x] TDG JSON 使用 ```json``` 代码块包裹
- [x] TDG 中的 task ID 与 sub-task 编号一致
- [x] wave 顺序反映正确的依赖关系（修正后）
- [x] 同一 wave 内的 task 满足并行安全条件（修正后）
- [x] 所有 leaf sub-task 都出现在 TDG 中
- [x] 文档收敛 top-level task 存在（Task 8）
- [x] 包含 state 文档更新 sub-task（8.1）
- [x] 包含 manual 文档更新 sub-task（8.2）
- [x] 包含 migration guide sub-task（8.3）
- [x] 包含 checkpoint sub-task（8.4）
- [x] 文档收敛内容与 design.md 的 Impact Analysis 一致

