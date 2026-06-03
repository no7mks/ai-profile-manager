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

**日期**: 2026-06-03 19:08

### Q&A

> **Q1**: R1–R14 是否均被 task 引用，且 Phase 1 与 D-CR1 单独 commit 是否体现？
> **A1**: 是。Task 1 覆盖 R1 并在 Overview/Notes 强调先于 task 2；R2–R12 映射 task 5–14；R13 为 13.3 人工；R14 为 14.4。

> **Q2**: 每个 top-level task 的 sub-task 数（不含 Checkpoint）是否 ≤10？
> **A2**: 是。最多 task 8 为 4 个实现 sub-task + Checkpoint；无「并且」类合并违规。

> **Q3**: TDG 是否避免同文件并行，且 Checkpoint 在其 task 末 wave？
> **A3**: 是。实现链分 wave（如 1.1→1.2→1.3）；Checkpoint 在各自 task 最后一 wave。

> **Q4**: E2E 是否含 R13 人工段且符合 e2e-testing 标注？
> **A4**: 是。13.1–13.2 标 _脚本_；13.3 标 _人工_ 并写 `docs/notes/deploy-scope-platform-verification.md`。

> **Q5**: 文档收敛是否与 design Impact 一致，Code Review 为末 task？
> **A5**: 是。14.1–14.3 覆盖 state/README/skill；task 15 委托 code-reviewer。

> **Q6**: `--scope` 是否与 D-CR2 A 一致？
> **A6**: 是。Task 12.1 仅 install/uninstall 族；task 7.2 show 过滤；check 不新增 scope（R8.5）。

### 结论

通过。可进入 GK tasks 校验；执行按 TDG wave，Task 1 Checkpoint 后再启动 Phase 2。

---

## Tasks Phase — Gatekeep Log

**校验时间**: 2026-06-03 19:10
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] TDG Checkpoint 独占 wave（51 waves）；移除 `"15"`；commit 改 `+/*` + `by Cursor`
- [内容] Checkpoint 补 phpunit；新增 14.3 迁移指南；9.2/6.2 补 Ref；Notes 补 checkpoint commit

### 合规检查

- [x] 结构/格式/追溯/E2E/CR15/Notes/TDG/文档收敛/Code Review 均符合 gk-tasks

### 非阻塞说明

- R14.1 ISS-31532 于 14.5 与 finish 一并闭环，与 goal 单次发布一致。

### TDG 并行修订（2026-06-03 19:13，GK 后人工修正）

- GK 曾将 51 个 leaf 拆为 51 个单任务 wave（过度串行）；按 gk-tasks「先合并、Checkpoint 独占」合并 6 处并行 wave：`8.1+8.2`、`10.1+10.2`、`12.2+12.3`、`13.1+13.2`、`14.1+14.2`、`14.4+14.5`；`14.3` 仍单独（与 14.2 同改 README/manual）。
