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

**日期**: 2026-06-05 15:14

### Q&A

> **Q1**: tasks 是否完整覆盖了 design 中的所有组件和接口变更？
> **A1**: 是。Design 列出 14 个组件变更：InvalidScopeException（1.1）、HandlesDeployScopeOption（3.1）、DeployScope（1.2）、DeployRootResolver（6.1）、AbilityRegistry（2.1-2.3）、AbilityEntry（1.3）、InstallationProbe（6.2）、Installer（6.3）、BootstrapCommand（7.1-7.3）、CheckService（6.4）、AbilityUpdateService（6.5）、ShowStatusPresenter（6.6）、GlobalSetupCommand（5.1）、CleanupCommand（6.7）。全部覆盖。另外 4 个需删除的模块在 Task 8 中处理。

> **Q2**: task 之间的依赖顺序是否正确？
> **A2**: 是。Task 1 改基础类型（Exception/Enum/Entry），Task 2 改 Registry（依赖 Entry 变更），Task 3 改 trait（依赖 Exception），Task 4-5 改 Command（依赖 trait），Task 6 改 Service（依赖 DeployScope/Resolver 简化），Task 7 扩展 Bootstrap（依赖 Registry + Installer），Task 8 删除废弃模块（需确认无引用），Task 9 改配置文件（需代码已就位），Task 10 E2E（需全功能就位），Task 11 文档，Task 12 Code Review。顺序正确。

> **Q3**: 每个 task 的粒度是否合适，是否存在过粗需拆分的 sub-task？
> **A3**: Task 6 有 7 个实现 sub-task + 1 checkpoint = 8，在原则上限内。Task 4 有 4 个实现 sub-task + 1 checkpoint = 5，合理。Task 11（文档收敛）有 7 个实现 sub-task + 1 checkpoint = 8，在上限内。各 sub-task 均满足单一职责。

> **Q4**: checkpoint 是否覆盖了关键阶段？
> **A4**: 是。每个 top-level task 末尾都有 checkpoint，包含 phpstan + phpunit 验证和 commit。E2E task 的 checkpoint 使用 `--testsuite e2e`。文档收敛 checkpoint 包含对 state 文件的同步更新。

> **Q5**: TDG 并行标注是否满足并行条件？
> **A5**: 是。Wave 0 中 1.1/1.2/1.3 修改不同文件（InvalidScopeException / DeployScope / AbilityEntry）且无数据依赖。Wave 6 中 4.1-4.4 修改不同 Command 文件且互不依赖。Wave 11 中 6.2-6.5 修改不同 Service 文件。Wave 17 中 8.1-8.4 删除不同文件。Wave 23 中 11.1-11.6 修改不同文档文件。均满足并行安全条件。

> **Q6**: E2E 测试是否覆盖了关键用户场景？
> **A6**: 是。覆盖了：deprecated global-setup（10.1）、install --scope（10.2）、show --scope（10.3）、typed install --scope（10.4）、bootstrap 正常执行（10.5）、bootstrap 幂等（10.6）、show 无 scope 标签（10.7）。覆盖了 design Testing Strategy 中列出的全部 4 个 E2E 场景并增加了 3 个补充场景。

> **Q7**: requirements 中的每条 requirement 是否至少被一个 task 引用？
> **A7**: Req 1 → Task 1.1, 1.2, 3.1, 4.1-4.4；Req 2 → Task 1.1, 5.1；Req 3 → Task 6.3, 7.1-7.3；Req 4 → Task 1.3, 2.1-2.3；Req 5 → Task 2.2；Req 6 → Task 6.6；Req 7 → Task 6.5；Req 8 → Task 6.7；Req 9 → Task 11.1-11.7。全部覆盖。

### 结论

通过。tasks.md 完整覆盖 design 全部组件和接口，依赖顺序正确，粒度适中，TDG 并行标注安全，E2E 场景充分，Requirement 追溯完整。可进入 GK 校验。



## Tasks Phase — Gatekeep Log

**校验时间**: 2026-06-05 15:36
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Task 4.4 原修改 4 个文件（SkillUninstallCommand、RuleUninstallCommand、AgentUninstallCommand、PresetUninstallCommand），超过 3 文件粒度上限。拆分为 4.4（前三者）和 4.5（PresetUninstallCommand），原 4.5 Checkpoint 重编号为 4.6。
- [结构] 文档收敛 Task 11 缺少 migration guide sub-task。新增 11.7 Migration Guide，原 11.7/11.8 重编号为 11.8/11.9。
- [格式] TDG Wave 2 原将 2.1、2.2、2.3 并行，三者均修改 AbilityRegistry.php，违反文件冲突规则。改为逐 wave 串行（Wave 2→3→4）。
- [格式] TDG Wave 15 原将 7.2、7.3 并行，两者均修改 BootstrapCommand.php，违反文件冲突规则。改为串行（Wave 17→18）。
- [格式] TDG E2E 测试 wave 原将 10.1-10.7 全部并行。10.5 和 10.6 均测试 bootstrap 命令，可能写入同一测试文件。拆分为 Wave 24（10.1-10.4）、Wave 25（10.5）、Wave 26（10.6+10.7）。

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Implementation Plan: deprecate-scope`
- [x] Overview section 存在
- [x] Tasks section 存在
- [x] 倒数第一个 top-level task 是 Code Review
- [x] 倒数第二个是文档收敛
- [x] 倒数第三个是 E2E 测试
- [x] top-level task 有序号，sub-task 有层级序号，连续无跳号
- [x] 每个实现类 sub-task 引用了对应 Requirement 编号
- [x] requirements.md 中每条 requirement 至少被一个 task 引用
- [x] 引用的编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序，无循环依赖
- [x] checkpoint 作为每个 top-level task 的最后一个 sub-task
- [x] 验证命令包含 phpstan + phpunit 完整 shell 命令
- [x] checkpoint 包含 commit 动作，commit message 符合 git-conventions
- [x] 每个 sub-task 满足单一职责
- [x] 无 sub-task 修改超过 3 个文件（修正后）
- [x] 所有 task 均为 mandatory
- [x] 每个 top-level task 的 sub-task 数量不超过 10 个（不含 Checkpoint）
- [x] E2E 测试 top-level task 存在，覆盖关键用户场景
- [x] Code Review 是最后一个 top-level task，描述为委托 sub-agent
- [x] Notes section 存在，明确提到 spec-execution 流程和 commit 随 checkpoint 执行
- [x] Socratic Review 已存在于 gk-logs.md
- [x] Design CR 决策在 tasks 编排中体现
- [x] Design 全覆盖
- [x] TDG section 存在，使用 JSON 格式和 json 代码块
- [x] TDG task ID 与 sub-task 编号一致
- [x] wave 顺序反映正确的依赖关系
- [x] 所有 leaf sub-task 都出现在 TDG 中
- [x] 同一 wave 内 sub-task 满足并行安全条件（修正后）
- [x] 文档收敛 top-level task 包含 state/manual/migration guide/checkpoint sub-task
- [x] 文档收敛内容与 design.md Impact Analysis 一致
