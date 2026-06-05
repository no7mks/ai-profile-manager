## Requirements Phase — Socratic Review

**日期**: 2026-06-05 13:00

### Q&A

> **Q1**: requirements 是否完整覆盖了 goal.md 中列出的全部 9 项目标？
> **A1**: 是。Req 1 覆盖目标 1（--scope 废弃）、Req 2 覆盖目标 2（global-setup 废弃）、Req 3 覆盖目标 3（bootstrap 扩展）、Req 4–5 覆盖目标 4（abilities.yaml 变更）、Req 6 覆盖目标 5（show 简化）、Req 7 覆盖目标 6（update 简化）、Req 8 覆盖目标 7（cleanup 文案）、Req 9 覆盖目标 9（文档同步）。目标 8（代码清理）属于实现层面，不属于外部可观察行为，正确地未列为独立需求。

> **Q2**: Non-scope 是否与 goal.md 的 Non-Goals 一致？
> **A2**: 一致。四条 Non-scope 逐条对应 goal.md 的 Non-Goals：无新 scope、不改 init Agent、不做 shim、不自动清理 user 文件。

> **Q3**: Glossary 中的术语是否在 AC 中被实际引用？是否有 AC 中出现的领域概念未在 Glossary 定义？
> **A3**: 全部 Glossary 术语（CLI, Scope_Parameter, Project_Scope, Deprecated_Error, Ability, Bootstrap_Command, Scaffold, Bootstrap_Includes, Ability_Registry, Validation_Error, Global_Setup_Command, Show_Command, Update_Command, Cleanup_Command, Install_Command, Typed_Install_Command, Typed_Uninstall_Command, Force_Flag, Exit_Failure）在 AC 中均被使用。AC 中无未定义的领域概念。

> **Q4**: Req 3（Bootstrap 扩展）是否充分覆盖了 scaffold 失败场景与 bootstrap.includes 为空场景？
> **A4**: 是。AC 7 处理 includes 为空/缺失的情况（静默成功），AC 8 处理 scaffold 失败的情况（exit 1 且不尝试 ability 安装）。

> **Q5**: Req 1 是否覆盖了 `--scope project` 这个曾经的默认值也 hard fail 的边界条件？
> **A5**: 是。AC 7 明确说明 `--scope project` 也触发同样的 Deprecated_Error，无特殊处理。

> **Q6**: Req 4 的 fail-fast 描述是否与 goal.md 的 Clarification #3 一致？
> **A6**: 一致。goal.md 决策为"首个违规即抛异常"，AC 3 明确规定遇到第一个违规立即终止、不继续扫描。

> **Q7**: 是否存在 scope 越界风险（如某 requirement 涉及了实现细节）？
> **A7**: 未发现。所有 AC 聚焦外部可观察行为（CLI 输出、退出码、文件操作结果），未引入类名、方法签名或内部架构描述。

### 结论

通过。requirements.md 完整覆盖 goal.md 全部目标与决策，术语表与 AC 双向对齐，边界条件充分，无 scope 越界。建议可进入 GK 校验。


## Requirements Phase — Gatekeep Log

**校验时间**: 2026-06-05 13:02
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（编号、术语）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`
- [x] Introduction 存在，描述了 feature 范围，明确了 Non-scope
- [x] Glossary 存在且非空
- [x] Requirements section 存在且包含至少一条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 中的术语在 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义（无未定义术语）
- [x] 术语格式正确（加粗术语名 + 冒号 + 定义）
- [x] AC 使用 SHALL 语体
- [x] AC Subject 使用 Glossary 术语
- [x] AC 编号连续无跳号
- [x] 无实现细节侵入（无类名、方法签名、框架名）
- [x] Socratic Review 已存在且覆盖充分
- [x] Goal CR 决策已在 requirements 中体现
- [x] Non-scope 与 goal.md Non-Goals 一致
- [x] AC 构成充分验收条件，可进入 design


## Design Phase — Socratic Review

**日期**: 2026-06-05 13:15

### Q&A

> **Q1**: design 是否覆盖了 requirements 中全部 9 条 Requirement 的实现路径？
> **A1**: 是。Req 1 → HandlesDeployScopeOption.rejectIfScopeProvided()；Req 2 → GlobalSetupCommand 退化；Req 3 → BootstrapCommand 扩展（含幂等 hash 比对）；Req 4 → AbilityRegistry.parse() 遗留字段检测；Req 5 → bootstrapIncludes() 方法；Req 6 → ShowStatusPresenter 简化；Req 7 → AbilityUpdateService 简化；Req 8 → CleanupCommand 文案；Req 9 → Impact Analysis 中列出全部受影响文档。

> **Q2**: CR 决策是否全部反映在设计中？
> **A2**: 是。CR-1（统一模板）→ InvalidScopeException::deprecated() 工厂方法；CR-2（IO 层立即验证）→ parse() 在任何命令路径都被调用；CR-3（hash 比对）→ BootstrapCommand 使用 AbilityDiffService 做内容比对；CR-4（baseline diff）→ ShowStatusPresenter 沿用 AbilityDiffService。

> **Q3**: 接口签名是否足够清晰，能让 task 独立执行？
> **A3**: 是。每个组件给出了完整的 PHP 方法签名（参数类型、返回类型），并说明了删除/新增逻辑。BootstrapCommand 有详细的阶段流程。

> **Q4**: 是否存在循环依赖或过度设计？
> **A4**: 无循环依赖。模块关系为 Command → Service → Config 单向依赖。未引入新抽象层（如 ScopeStrategy），仅简化现有结构。

> **Q5**: 删除 ScopeGuard 后，是否有调用点遗漏处理？
> **A5**: 不会。ScopeGuard 的调用入口仅在 HandlesDeployScopeOption trait 的 `guardInstallBatch()` 和 `guardPresetInstall()` 中，这两个方法一并删除。InstallCommand 中对应的调用改为直接执行 install（因为 scope 永远是 project，无需 guard）。

> **Q6**: bootstrap 的幂等判定（hash 比对）与现有 install 机制是否有冲突？
> **A6**: 不冲突。现有 Installer.installTyped() 无幂等检查（总是覆盖写入）。BootstrapCommand 在调用 installTyped 之前自行做 hash 比对决定是否 skip，是额外的前置逻辑，不修改 Installer 核心行为。

> **Q7**: DeployScope 仅保留 Project 单值后，现有 match 表达式是否需要处理？
> **A7**: 是。DeployRootResolver::resolve() 中的 match 只需保留 `DeployScope::Project => $this->projectRoot()` 一个分支（或直接 return）。Installer 等内部方法不再传 scope 参数。编译器会自动检查 match 穷举性，单 case 下不会有遗漏。

### 结论

通过。design.md 完整覆盖全部 requirements 和 CR 决策，接口签名清晰，模块依赖单向无循环，与现有代码架构一致。可进入 GK 校验。


## Design Phase — Gatekeep Log

**校验时间**: 2026-06-05 13:16
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Design: deprecate-scope`
- [x] Overview 概述了设计方向
- [x] Components and Interfaces 为每个组件提供了 PHP 方法签名
- [x] Data Models 描述了 abilities.yaml 新旧格式差异
- [x] Correctness Properties 存在且覆盖核心不变量
- [x] Error Handling 列出所有错误场景与处理方式
- [x] Testing Strategy 覆盖了单元和 E2E 层面
- [x] Impact Analysis 列出受影响文档与模块
- [x] Alternatives Considered 记录落选方案与理由
- [x] CR 决策在设计中被正确引用和体现
- [x] 与 requirements 的每条 Requirement 有明确对应
- [x] Socratic Review 已存在且覆盖充分


## Tasks Phase — Socratic Review

**日期**: 2026-06-05 13:35

### Q&A

> **Q1**: tasks 是否完整覆盖了 design 中的所有实现项（14 个 Components/Interfaces）？
> **A1**: 是。Task 1 → InvalidScopeException + DeployScope；Task 2 → DeployRootResolver；Task 3 → HandlesDeployScopeOption；Task 4 → AbilityEntry + AbilityRegistry；Task 5 → Installer + InstallationProbe；Task 6 → CheckService + AbilityUpdateService；Task 7 → ShowStatusPresenter；Task 8 → GlobalSetupCommand + CleanupCommand；Task 9 → BootstrapCommand；Task 10 → 删除模块（ScopeGuard、UserHomeResolver、GlobalSetupService、GlobalInstallDetector）；Task 11 → abilities.yaml 迁移。

> **Q2**: task 之间的依赖顺序是否正确？
> **A2**: 是。底层先行：Task 1（枚举+异常）→ Task 2（DeployRootResolver 依赖枚举简化）→ Task 3（trait 依赖新异常方法）→ Task 4（registry 依赖 AbilityEntry 变更）→ Task 5/6（service 依赖 resolver 和 registry）→ Task 7/8（命令层依赖 service）→ Task 9（bootstrap 依赖 installer + registry）→ Task 10（删除在所有解耦后）→ Task 11（yaml 迁移在 registry 支持新格式后）。

> **Q3**: 每个 task 的粒度是否合适？
> **A3**: 是。每个 sub-task 满足单一职责原则：修改一个类/接口或一组紧密耦合改动。最大的 top-level task（Task 4 有 4 个功能 sub-task + 1 checkpoint = 5 个）未超过 8 个上限。Task 9（4 个功能 sub-task + 1 checkpoint = 5 个）、Task 10（4 个删除 sub-task + 1 checkpoint = 5 个）均在合理范围。

> **Q4**: checkpoint 是否覆盖了关键阶段？
> **A4**: 是。每个 top-level task 均以 checkpoint 结尾，包含静态分析 + 单元测试验证命令和 commit 动作。

> **Q5**: 并行标注是否满足并行条件？
> **A5**: 是。同一 wave 内的 sub-task 不修改同一文件：wave 0（1.1 改 DeployScope.php、1.2 改 InvalidScopeException.php）；wave 8（4.1 改 AbilityEntry.php、4.4 改 AbilityRegistry.php 中的 projectOnlyPaths）；wave 11（5.1 改 InstallationProbe.php、5.2 改 Installer.php）等。Checkpoint 均独占 wave。

> **Q6**: E2E 测试是否覆盖了关键用户场景？
> **A6**: 是。4 个 E2E sub-task 覆盖：--scope 拒绝（多命令、多值）、global-setup 拒绝（含 flags）、bootstrap 完整流程（首次+幂等+force）、abilities.yaml 遗留字段检测。与 design Testing Strategy 的 E2E 表一致。

> **Q7**: Requirement 追溯是否完整？
> **A7**: 是。逐条检查：Req 1 → Task 1.2, 2.1, 3.1, 3.2, 5.1, 5.2, 6.1, 10.1-10.4, 12.1；Req 2 → Task 1.2, 8.1, 10.3, 12.2；Req 3 → Task 9.1-9.4, 12.3；Req 4 → Task 4.1, 4.2, 4.4, 12.4；Req 5 → Task 4.3, 9.2, 9.4；Req 6 → Task 7.1, 7.2；Req 7 → Task 6.2；Req 8 → Task 8.2；Req 9 → Task 13.1-13.7。每条 requirement 至少被一个 task 引用。

### 结论

通过。tasks.md 完整覆盖 design 全部实现项，依赖顺序正确，粒度合适（每 top-level task ≤ 8 sub-task），并行条件成立，E2E 覆盖充分，Requirement 追溯完整。可进入 GK 校验。
