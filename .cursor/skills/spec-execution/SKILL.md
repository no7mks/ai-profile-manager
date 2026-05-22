---
name: spec-execution
description: 当用户要求执行 spec task、执行 spec wave、推进 tasks.md、说 next wave/next task/继续执行、或进入 spec 实现阶段时激活。含执行模型、质量标准、特殊任务与异常处理。
---

# Spec Execution

本 Skill 统一了 spec 中 task 的执行规范。激活后严格按如下步骤执行，并在每个 step / sub-step 执行前后向用户反馈执行结果与下一步计划。

---

## 通用约束

- **Pre-existing failures 不可跳过**：任何时候运行测试或验证命令，如果存在失败，无论是否由本次变更引起，都必须修复或向用户确认可以忽略后才能继续。不得以"pre-existing issue"、"非本次引入"等理由自行决定跳过。

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

### 2.2 确定执行范围

读取 tasks.md（和 TDG），确定当前要执行的 task 范围。范围可以是一个 top-level task、一个 wave、或用户指定的若干 sub-task。

**默认行为**：如果用户空白激活 skill（无其他上下文），等同于用户说了 "next wave"，执行下一个未完成的 wave。

**汇报格式**：确定后必须向用户报出执行范围，例如：

> 下一个 wave 是 3.1, 3.2，执行范围确认，绝不额外执行。

**严格避免**：执行非范围内的任务。

### 2.3 分析并行性

- 如果 tasks.md 包含 TDG → 按 wave 分组
- 如果没有 TDG → 按 [references/orchestration.md](references/orchestration.md) 的并行策略自行分析

### 2.4 派发 Sub-agent

**对每个 sub-task 派发 sub-agent 时，必须包含以下上下文：**

1. **激活指令**：明确告知 sub-agent 以 `sub-agent` 身份激活 `spec-execution` skill（这是硬性要求，不可省略）
2. **执行法则**：[references/sub-agent-rules.md](references/sub-agent-rules.md) 的完整内容（所有 sub-agent 必传，不可省略）
3. **task 描述**：tasks.md 中该 sub-task 的完整内容（含 Ref）
4. **相关文件路径**：task 涉及的源文件、测试文件、配置文件
5. **前序产出**：前序 task 的关键产出（新增的类名、接口签名、文件路径等）

**不传递**：整个 tasks.md、与当前 task 无关的 references、已完成 task 的过程。

### 2.5 Commit 粒度（Cursor 特有）

以 top-level task 为 commit 粒度。谁负责 commit 取决于派发方式：

| 派发方式 | sub-agent 职责 | 主 agent 职责 |
|---------|---------------|-------------|
| 串行 | 写代码 + 标记 `tasks.md` `[x]` + commit | 验证后推进下一个 task |
| 并行 | 写代码 + 标记 `tasks.md` `[x]`（不 commit） | 全部完成后 review → 逐 task stage + commit |

### 2.6 汇总与推进

- 并行组内所有 sub-agent 完成后，main-agent review 结果
- 标记 tasks.md 进度
- **硬停止**：完成当前 top-level task 或 wave 后，**禁止**自动执行下一个 task/wave。不得以"让我继续下一个"、"接下来执行"等措辞自行推进。必须停下来，等待用户显式发出下一步指令（如 next wave、next task、继续执行）。标记完成后的唯一允许动作是向用户汇报当前进度。

---

## Step 3：Sub-agent 流程

Sub-agent 负责执行具体 sub-task，按步骤推进并逐步汇报。每完成一个 sub-step（3.1 → 3.2 → ...）必须向 main-agent（或用户）汇报「3.1 done」「3.2 done」，再进入下一步。

### 3.1 确认 task 类型与加载规则

根据收到的 task 描述判断类型，并加载对应规则：

- 功能实现（编码）→ 按 sub-agent-rules 的测试规范执行
- Bug fix → 按 sub-agent-rules 的 Bug Fix 测试规则执行
- E2E 测试 → 加载 e2e-testing steering
- Code Review → 委托 code-reviewer sub-agent
- 文档收敛 → 无额外加载
- Checkpoint → 按 sub-agent-rules 的 Checkpoint 规则执行

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

### 3.4 异常处理

执行中遇到问题时，按 sub-agent-rules 的异常处理规则处理：

- 常规错误（编译失败、测试失败）→ 自行修复，继续推进
- 触发 Blocker Escalation 条件 → 立即停止，向 main-agent/用户报告

---

## 术语约定

- `Task 3-5` = Task 3 到 Task 5（范围），不是 Task 3 的 sub-step 5
- 引用 sub-step 时使用 `Task 3.2`（用句点分隔）

---

## References

- 编排规则（无 TDG 时的并行分析）：[references/orchestration.md](references/orchestration.md)
- Sub-agent 执行法则（异常处理、测试规范、checkpoint、特殊任务）：[references/sub-agent-rules.md](references/sub-agent-rules.md)
