---
name: spec-execution
description: 当用户说 execute task/next wave/next task/执行任务/继续执行 等场景，表明要进入 spec 实现阶段时激活。含执行模型、质量标准、特殊任务与异常处理。
---

# Spec Execution

本 Skill 统一了 spec 中 task 的执行规范。激活后严格按如下步骤执行，并在每个 step / sub-step 执行前后向用户反馈执行结果与下一步计划。

---

## Step 1：判断执行角色

激活后立即判断当前处于哪种角色：

| 角色 | 判断条件 | 进入流程 |
|------|---------|---------|
| **Main-agent** | 用户要求执行 top-level task 或一组 wave | → Step 2: Main-agent 流程 |
| **Sub-agent** | 被 main-agent 派发执行具体 sub-task | → Step 3: Sub-agent 流程 |

判断依据：如果当前上下文中有完整的 tasks.md 访问权限且用户直接对话，则为 main-agent；如果收到的是单个 task 描述和限定的文件范围，则为 sub-agent。

---

## Step 2：Main-agent 流程

Main-agent 负责调度和验收，不直接写代码。每完成一个 sub-step（2.1 → 2.2 → ...）必须向用户汇报「2.1 done」「2.2 done」，再进入下一步。

### 2.1 Pre-execution Review（Drift 检测）

每次开始或恢复执行前，将当前要执行的 task 涉及的关键文件与 design.md 中的预期做快速比对——如果代码结构、接口签名、依赖关系已发生变化（例如被其他分支 merge 改动），标记为 drift。

决策：
- 无 drift → 正常执行
- 发现 drift 但不影响当前 task 的实现路径 → 记录 drift，正常执行
- 发现 drift 且影响当前 task 的实现路径 → **停下来**，向用户报告 drift 内容，等待确认后再继续

### 2.2 确定执行范围与编排

1. 读取 tasks.md和 TDG
2. 若没有 TDG → 按 [references/orchestration.md](references/orchestration.md) 生成 TDG
3. **确定范围**：范围可以是一个 top-level task、一个 wave、或用户指定的若干 sub-task；若用户没指定，视为执行下一个 wave
4. **分析并行性**：若 wave 内包含不止一个任务，按 [references/orchestration.md](references/orchestration.md) 的并行策略分析所有任务是否可以并行执行
5. **汇报**：确定后必须向用户报出执行范围，例如：“下一个 wave 是 3.1, 3.2，执行范围确认，绝不额外执行。”

**严格避免**：执行非范围内的任务。

### 2.3 执行任务

0. 先输出一句话“不管任务多少、难度几何，都不允许在 main-agent 执行任务！”
1. 按照 2.2 的分析，为每个 sub-task 派发一个 sub-agent 执行，串行的任务等上一个 sub-task 完毕后再派发下一个。对每个 sub-task 派发 sub-agent 时，必须包含以下上下文：
  - **激活指令**：明确告知 sub-agent 以 `sub-agent` 身份激活 `spec-execution` skill（这是硬性要求，不可省略）
  - **task 描述**：tasks.md 中该 sub-task 的完整内容（含 Ref）
  - **相关文件路径**：task 涉及的源文件、测试文件、配置文件
  - **前序产出**：前序 task 的关键产出（新增的类名、接口签名、文件路径等）

> **注意**：不向 sub-agent 传递整个 tasks.md、与当前 task 无关的 references、已完成 task 的过程。

### 2.4 Pre-existing Failure Review

所有 sub-agent 完成后、标记进度前，main-agent 必须执行以下检查：

1. 审查每个 sub-agent 的完成汇报，确认是否存在 pre-existing failure
2. 如果存在，确认是否按 execution-model 的异常处理流程进行了处理（修复成功 / 用户明确说可忽略）
3. 如果发现有被忽略、跳过、或未按流程处理的 pre-existing failure → **回到 2.3**，派发新的 sub-agent 专门处理这些遗留 failure
4. 新 sub-agent 完成后，再次执行本步骤检查，直到无不合规情况

### 2.5 汇总与推进

- 并行组内所有 sub-agent 完成后，main-agent review 结果
- 标记 tasks.md 进度
- **硬停止**：完成当前 top-level task 或 wave 后，**禁止**自动执行下一个 task/wave。不得以"让我继续下一个"、"接下来执行"等措辞自行推进。必须停下来，等待用户显式发出下一步指令（如 next wave、next task、继续执行）。标记完成后的唯一允许动作是向用户汇报当前进度。

---

## Step 3：Sub-agent 流程

Sub-agent 负责执行具体 sub-task，按步骤推进并逐步汇报。每完成一个 sub-step（3.1 → 3.2 → ...）必须向 main-agent（或用户）汇报「3.1 done」「3.2 done」，再进入下一步。

### 3.1 加载执行模型

读取 [执行模型](references/execution-model.md) ，然后根据收到的 task 描述判断类型，并加载对应规则。最终汇报类似下文的内容：
“已从<file-path>加载执行模型，task 类型判断为<task-type>，将按照约定……”

### 3.2 拆分 Sub-steps

将 sub-task 拆分为可逐步执行的 sub-steps。典型的功能实现 sub-task 拆分为：

1. 编写测试（覆盖 AC）
2. 运行测试，确认失败（RED）
3. 编写实现代码
4. 运行测试，确认通过（GREEN）
5. 覆盖自检（对照 AC 检查是否有遗漏场景）

### 3.3 逐步执行与汇报

每完成一个 sub-step，向 main-agent（或用户）汇报：

- 当前完成了什么
- 执行结果（测试输出、编译结果等）
- 下一步计划
- 若执行中遇到错误，按 execution-model 的异常处理规则处理

### 3.4 完成汇报

所有 sub-steps 执行完毕后，向 main-agent（或用户）提交最终汇报：

- 任务状态：成功 / 失败 / 被阻断
- 产出物清单：新增或修改的文件列表
- 测试结果：运行了哪些测试、是否全部通过
- **合规自检**：逐项确认遗留问题是否按 execution-model 处理（包括 pre-existing failures——已修复，或已获得用户确认可跳过）。如果存在未处理的失败且未获用户确认，任务状态不得报"成功"


