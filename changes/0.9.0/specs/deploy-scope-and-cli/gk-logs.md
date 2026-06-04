# GK Logs: deploy-scope-and-cli

## Requirements Phase — Socratic Review

**日期**: 2026-06-03 18:36

### Q&A

> **Q1**: 需求是否覆盖 goal 中的 Phase 1 / Phase 2 与全部来源（PRP-002、ISS-31532、ISS-01592）？
> **A1**: 是。R1 对应 Phase 1 的 31532；R2 对应 01592；R3–R14 覆盖 PRP 全量、§8 平台验证与 issue 闭环。Phase 标签在 requirement 标题中显式标注。

> **Q2**: Clarification 三项决策是否体现在 requirements？
> **A2**: 是。Q1 同一 feature 发布 → Introduction 与 R14；Q2 保留并修复 Installation Fallback → R2 AC5；Q3 PRP 全量 + 平台验证阻塞 → R3–R13，R13 标阻塞。

> **Q3**: AC 是否避免实现细节（类名/方法名）？
> **A3**: 是。Subject 使用 Glossary 术语（Baseline Resolver、Show Command 等），未出现 ComposerBaselineResolver、isInstalledOnTarget 等符号。

> **Q4**: R1 与 R2 的边界是否清晰，避免 Phase 2 误以为 01592 可跳过？
> **A4**: 是。R1 聚焦 XDG baseline；R2 明确 baseline 不可用时的 Fallback；goal 已说明有 baseline 时 diff 通常正确，但 R2 仍独立验收。

> **Q5**: R9 Show 三态与现有 check 状态（unchanged/modified/unknown）是否在 requirements 层混淆？
> **A5**: 有意区分。R9 采用 PRP 用户可见三态（not installed / installed / installed with local change），与 Check Service 内部状态分离；design 阶段应定义映射，不在 requirements 写映射表。

> **Q6**: R13 Platform Verification 为人工验收，可测性是否足够？
> **A6**: 可接受。R13 AC 要求 finish 前记录验证日志，属发布门禁而非自动化单测；tasks 阶段应拆为显式人工 task。

> **Q7**: Glossary 与 AC 双向引用是否一致？
> **A7**: 抽检通过。Installation Fallback、Global Setup List、Project-Only Ability 等均在对应 AC 中出现；无孤立术语。

### 结论

通过。可进入 GK requirements 校验；design 阶段需处理 Show 三态映射、scope 解析与 preset 删除的迁移影响。

---

## Requirements Phase — Gatekeep Log

**校验时间**: 2026-06-03 18:39
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [语体] R3 AC3：补全 Subject 为 THE APM CLI
- [语体] R5 AC4–AC6：Subject 统一为 THE Global Setup Command；`ON success` 改为 WHEN 触发式
- [术语] Glossary 增补 **SSOT**、**APM Skill**；R1 AC6、R11、R12 引用对齐
- [内容] R3 新增 AC7：显式列出 PRP-002 **Project-Only Ability** 清单，避免 design 阶段歧义

### 合规检查

- [x] 无 TBD / TODO / 占位符
- [x] 一级标题 `# Requirements Document`
- [x] Introduction + Non-scope + Glossary + Requirements（14 条）
- [x] section 间 `---` 分隔
- [x] Glossary ↔ AC 双向引用（修正后）
- [x] AC EARS 语体（修正后）
- [x] 无类名/方法名泄露
- [x] Socratic Review ≥5 条 Q&A
- [x] Goal CR / Phase / Non-scope 已承接
- [x] 可 design 性充分

### Clarification Round（供 design 前确认）

> **CR1**: **Show Command** 三态与 **Check Service** 内部状态如何映射？
> - **A:** unchanged→installed；modified→installed with local change；missing/unknown→not installed（无 baseline 时走 **Installation Fallback**）

> **CR2**: 删除 preset `default` 后是否提供显式迁移指引？
> - **A:** 是——报错文案 + README 写清「global-setup → 进仓 init → 按需 add preset」

> **CR3**: **Update Command** `--force` 时 user+project 均已安装且内容不同，更新哪一侧？
> - **A:** 两侧都更新（各自与 **Baseline** diff，独立覆盖）

> **CR4**: 如何判定 **Global APM Installation**？
> - **A:** 可执行路径在 Composer global `vendor/bin` 下即视为 global，否则 **Update Command** 报错

---

## Design Phase — Socratic Review

**日期**: 2026-06-03 18:43

### Q&A

> **Q1**: 14 条 Requirement 是否均有可实现的组件落点？
> **A1**: 是。Traceability 表与 Components 覆盖 R1–R14；R11–R12 标为 tasks 文档同步；R13–R14 为验证/issue 收尾。

> **Q2**: Phase 1 设计是否独立于 Phase 2，避免阻塞？
> **A2**: 是。P1 仅改 `ComposerBaselineResolver`；P2 才引入 DeployRootResolver 与新命令，与 goal phase 一致。

> **Q3**: CR1 Show 映射是否单一职责、避免命令层重复？
> **A3**: 是。映射表在 `ShowStatusPresenter`；CheckService 仍输出内态，Show 不泄露 unchanged/modified 字面量。

> **Q4**: R2 与现有 `Installer::isInstalledOnTarget` 关系是否明确？
> **A4**: 是。新 `InstallationProbe` 取代 rule 的 basename 递归；Installer 委托 probe，保留 skill/agent 路径逻辑。

> **Q5**: Update 弃用 KnowledgeBaseUpdater 是否造成 scope 越界？
> **A5**: 否。R10 要求 baseline diff 覆盖；design 注明类保留、tasks 决定去留，不扩需求。

> **Q6**: Impact Analysis 是否覆盖 state、兼容、配置与 R13 人工项？
> **A6**: 是。列三份 state、无自动迁移、yaml 变更、验证日志路径建议。

> **Q7**: Alternatives 是否说明落选理由且不少于 1 项？
> **A7**: 是。四条备选均对应 requirements 或 GK 约束。

### 结论

通过。可进入 GK design 校验；tasks 阶段用 **wave** 编排执行（对齐 Phase 1/2），并含 R13 人工 task 与文档同步清单。

---

## Design Phase — Gatekeep Log

**校验时间**: 2026-06-03 18:44
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] Correctness Properties：补全 Validates / 条件 / 保证 / 违反后果；引用改为 Requirements N.M
- [内容] 命令表补 R5.3–6、R6.2、R7.1–3、R8.3–5；Show 补 R9.2–3/6、gitignore 与 user hook
- [内容] Error Handling 明确 `InvalidScopeException`
- [内容] Impact Analysis 扩展代码清单、外部 IDE/hook、配置与 KnowledgeBaseUpdater 待定
- [内容] Traceability 表格式；Clarification Round 改为 tasks 向 D-CR1–4（各 3 选项）
- [目的] 承接 requirements GK CR，避免 design 末尾重复未答 CR

### 合规检查

- [x] 无 TBD / TODO / 占位符
- [x] 必须 section：Overview、Architecture、Components、Data Models、Impact、Alternatives
- [x] section 间 `---` 分隔
- [x] Requirements R1–R14 可追溯
- [x] Impact 含 state / 代码 / 兼容 / 外部 / 配置
- [x] Correctness Properties 格式合规（修正后）
- [x] Design Socratic Review ≥5 条 Q&A
- [x] 接口签名可 task 化
- [x] Alternatives ≥1 项

### Clarification Round（供 tasks 前确认，见 design.md）

> **D-CR1** **A:** Phase 1 单独 commit，先于 Phase 2  
> **D-CR2** **A:** `--scope` 仅 install/uninstall 族；show 保留过滤 scope  
> **D-CR3** **A:** 删除 `KnowledgeBaseUpdater` 及测试/DI/文档  
> **D-CR4** **A:** cleanup 遍历 registry conventional，探测 project 存在则卸

---

## Tasks Phase — Socratic Review

**日期**: 2026-06-04 14:51  
**类型**: Re-review（首次：2026-06-03 19:08）

### Q&A

> **Q1**: 更新后的 §6 Checkpoint 是否要求每个 top-level task 列出可执行的 phpstan + phpunit，且 E2E 仅留在 task 13？
> **A1**: 是。Re-review 后 task 1–12、14 的 checkpoint 均为 `./vendor/bin/phpstan analyse` + `./vendor/bin/phpunit`；task 13.4 另加 e2e suite，其它 task checkpoint 不含 e2e。

> **Q2**: `docs/state/` 同步是否仍被误写进每个 checkpoint？
> **A2**: 否。state 在 1.4、14.1 等变更事实的 sub-task 内更新；Overview/Notes 明确 checkpoint 不重复 state；14.6 仅跑静态分析与单测后 commit 文档。

> **Q3**: R1–R14 追溯与 D-CR1–4 编排是否仍完整？
> **A3**: 是。结构顺序、Ref 覆盖、TDG 45 waves、D-CR2 scope 边界、D-CR3 删 KnowledgeBaseUpdater、D-CR4 cleanup 枚举均未因 checkpoint 修订而破坏。

> **Q4**: TDG 并行合并与 Checkpoint 独占 wave 是否仍成立？
> **A4**: 是。`8.1+8.2`、`10.1+10.2` 等合并 wave 保留；各 task checkpoint 仍在末 wave（id 4、7、11…44），无与同 wave 实现 sub-task 混排。

> **Q5**: 文档收敛 task 14 是否仍对齐 design Impact（state/manual/迁移/skill/issue）？
> **A5**: 是。14.1–14.5 覆盖三份 state、README/manual、迁移专节、APM skill、ISS 闭环；checkpoint 14.6 含验证命令与 commit。

> **Q6**: 执行中 task 1–6 已勾选完成，未完成任务 checkpoint 是否同样可执行？
> **A6**: 是。已完成与待办 task 使用同一 checkpoint 命令模板，执行 agent 无需区分格式。

### 结论

通过。Re-review 仅强化 checkpoint 与 PROJECT.md 对齐；可按 TDG 继续 task 7 起执行。

---

## Tasks Phase — Gatekeep Log

**校验时间**: 2026-06-04 14:51  
**类型**: Re-review（首次：2026-06-03 19:10，⚠️ 已修正后通过）  
**校验结果**: ⚠️ 已修正后通过

### 修正项（本次 Re-review）

- [内容] 全部实现类 Checkpoint（1.5–12.4、14.6）补 `./vendor/bin/phpstan analyse`，保留 `./vendor/bin/phpunit`
- [内容] E2E Checkpoint 13.4：phpstan + unit + `--testsuite e2e`（不在其它 task checkpoint 写 e2e）
- [内容] 文档收敛 14.6：补 phpstan + phpunit（原仅 commit）
- [内容] Overview/Notes：checkpoint 与 PROJECT.md 对齐；state 仅在变更事实 sub-task 更新

### 修正项（首次校验，2026-06-03）

- [格式] TDG Checkpoint 独占 wave；移除 `"15"`；commit 改 `+/*` + `by Cursor`
- [内容] 新增 14.3 迁移指南；9.2/6.2 补 Ref；Notes 补 checkpoint commit
- [格式] TDG 合并 6 处并行 wave（`8.1+8.2` 等），见 19:13 人工修订

### 合规检查

- [x] 机械扫描：无 TBD/TODO/占位符；checkbox 与标题格式正确
- [x] 结构：15 task 顺序；末 Code Review；倒数二文档收敛；倒数三 E2E
- [x] Task 格式：序号连续；sub-task ≤10（不含 Checkpoint）
- [x] Requirement 追溯：R1–R14 均有引用；实现 sub-task 含 Ref
- [x] Checkpoint：可执行 shell；phpstan + phpunit；commit 含 `by Cursor`；state 按需于 sub-task
- [x] E2E：13.1–13.3 覆盖关键场景；_脚本_/_人工_ 标注
- [x] Code Review：委托 sub-agent，无展开 checklist
- [x] Notes：spec-execution、checkpoint commit、TDG 并行说明
- [x] TDG：json 代码块；45 waves；Checkpoint 独占；并行安全
- [x] 文档收敛：14.1–14.3 对齐 Impact；含 migration
- [x] 目的性：Design CR、模块覆盖、验收闭环

### 非阻塞说明

- R14 / ISS-31532 于 14.5 与 finish 一并闭环，与 goal 单次发布一致。
- 无新增 Clarification Round；design D-CR 已在 tasks 编排中落地。
