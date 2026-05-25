---
name: spec-execution
description: 当用户说 execute task/next wave/next task/执行任务/继续执行 等场景，表明要进入 spec 实现阶段时激活。含执行模型、质量标准、特殊任务与异常处理。
---

# Spec Execution

本 Skill 统一了 spec 中 task 的执行规范。激活后严格按如下步骤执行。

---

## Step 1：判断执行角色

1. 输出："§1.1 判断执行角色……"
2. 根据以下条件判断角色：

| 角色 | 判断条件 | 进入流程 |
|------|---------|---------|
| **Main-agent** | 用户要求执行 top-level task 或一组 wave | → Step 2 |
| **Sub-agent** | 被 main-agent 派发执行具体 sub-task | → Step 3 |

判断依据：如果当前上下文中有完整的 tasks.md 访问权限且用户直接对话，则为 main-agent；如果收到的是单个 task 描述和限定的文件范围，则为 sub-agent。

3. 输出："§1.3 角色确认：<Main-agent / Sub-agent>，进入 Step <2/3>。"

---

## Step 2：Main-agent 流程

Main-agent 负责调度和验收，不直接写代码。

### 2.1 Pre-execution Review（Drift 检测）

1. 输出："§2.1.1 开始 Drift 检测，比对当前代码与 design.md 预期……"
2. 将当前要执行的 task 涉及的关键文件与 design.md 中的预期做快速比对——如果代码结构、接口签名、依赖关系已发生变化（例如被其他分支 merge 改动），标记为 drift。
3. 决策：
   - 无 drift → 正常执行
   - 发现 drift 但不影响当前 task 的实现路径 → 记录 drift，正常执行
   - 发现 drift 且影响当前 task 的实现路径 → **停下来**，向用户报告 drift 内容，等待确认后再继续
4. 输出："§2.1.4 Drift 检测完成。结果：<无 drift / 有 drift 但不影响 / 有 drift 需确认>"

### 2.2 确定执行范围与编排

1. 输出："§2.2.1 开始确定执行范围……"
2. 读取 tasks.md 和 TDG
3. 若没有 TDG → 按 [references/orchestration.md](references/orchestration.md) 生成 TDG
4. **确定范围**：范围可以是一个 top-level task、一个 wave、或用户指定的若干 sub-task；若用户没指定，视为执行下一个 wave
5. **识别 wave 序列**：从 TDG 中提取当前范围内的所有 wave，wave 间串行，wave 内并行
6. **严格避免**：执行非范围内的任务。
7. 输出："§2.2.7 执行范围确定。共 <N> 个 wave：<wave 列表>。绝不超范围执行。"

### 2.3 ~ 2.5 逐 wave 循环

对范围内的每个 wave，依次执行 §2.3 → §2.4 → §2.5：

### 2.3 派发当前 wave

1. 输出："§2.3.1 不管任务多少、难度几何，都不允许在 main-agent 执行任务！派发 wave <id> 的 sub-agent……"
2. 并行派发当前 wave 内的所有 sub-task，每个 sub-task 一个 sub-agent。派发时必须包含以下上下文：
   - **激活指令**：明确告知 sub-agent 以 `sub-agent` 身份激活 `spec-execution` skill（这是硬性要求，不可省略）
   - **task 描述**：tasks.md 中该 sub-task 的完整内容（含 Ref）
   - **相关文件路径**：task 涉及的源文件、测试文件、配置文件
   - **前序产出**：前序 wave 的关键产出（新增的类名、接口签名、文件路径等）
3. **注意**：不向 sub-agent 传递整个 tasks.md、与当前 task 无关的 references、已完成 task 的过程。
4. 输出："§2.3.4 wave <id> 所有 sub-agent 已返回结果。"

### 2.4 Pre-existing Failure Review（当前 wave）

1. 检查当前 wave 内 sub-agent 汇报的测试结果，输出："§2.4.1 审查 wave <id> 是否有 Pre-Existing Failure：<YES/NO>"
2. 如果有失败，检查用户是否有说过“可以忽略这些失败”，输出："§2.4.2 用户 <明确说过/没有说过> 可以忽略这些 Pre-Existing Failure"
3. **硬规则**：存在失败 → 且用户没说可以忽略 → 不合规，回到 §2.3，派发新 sub-agent 修复
4. 若无失败 → 合规，继续
5. 输出："§2.4.5 wave <id> 审查完成。结果：<全部通过 / 已修复 / 用户确认可忽略 / 不合规已派发修复>"

### 2.5 Wave 汇总

1. 输出："§2.5.1 汇总 wave <id> 执行结果……"
2. Review 当前 wave 所有 sub-agent 结果
3. 标记 tasks.md 中对应 sub-task 进度
4. 若范围内还有下一个 wave → 回到 §2.3 执行下一个 wave
5. 若所有 wave 已完成 → 进入 §2.6

### 2.6 完成与硬停止

1. 输出："§2.6.1 所有 wave 执行完毕，汇总最终结果……"
2. 确认 tasks.md 进度标记完整
3. **硬停止**：完成当前 top-level task 或执行范围后，**禁止**自动执行下一个 task/wave。不得以"让我继续下一个"、"接下来执行"等措辞自行推进。必须停下来，等待用户显式发出下一步指令（如 next wave、next task、继续执行）。标记完成后的唯一允许动作是向用户汇报当前进度。
4. 输出："§2.6.4 当前进度：<已完成的 task/wave>。等待用户指令。"

---

## Step 3：Sub-agent 流程

Sub-agent 负责执行具体 sub-task，按步骤推进并逐步汇报。

### 3.1 加载执行模型

1. 输出："§3.1.1 加载执行模型……"
2. 读取 [执行模型](references/execution-model.md)，然后根据收到的 task 描述判断类型，并加载对应规则。
3. 输出："§3.1.3 已加载执行模型，task 类型判断为 <task-type>。"

### 3.2 拆分 Sub-steps

1. 输出："§3.2.1 拆分 sub-steps……"
2. 将 sub-task 拆分为可逐步执行的 sub-steps。典型的功能实现 sub-task 拆分为：
   - 编写测试（覆盖 AC）
   - 运行测试，确认失败（RED）
   - 编写实现代码
   - 运行测试，确认通过（GREEN）
   - 覆盖自检（对照 AC 检查是否有遗漏场景）
3. 输出："§3.2.3 拆分完成。共 <N> 个 sub-steps：<列表>。"

### 3.3 逐步执行与汇报

1. 输出："§3.3.1 开始逐步执行……"
2. 每完成一个 sub-step，向 main-agent（或用户）汇报：
   - 当前完成了什么
   - 执行结果（测试输出、编译结果等）
   - 下一步计划
3. 若执行中遇到错误，按 execution-model 的异常处理规则处理
4. 输出："§3.3.4 所有 sub-steps 已执行。"

### 3.4 完成汇报

1. 输出："§3.4.1 生成完成汇报……"
2. 向 main-agent（或用户）提交最终汇报：
   - 任务状态：成功 / 失败 / 被阻断
   - 产出物清单：新增或修改的文件列表
   - 测试结果：运行了哪些测试、是否全部通过
   - 要求 main-agent：“继续执行 §2.3 的剩余 sub-steps”
3. 输出："§3.4.3 任务状态：<成功/失败/被阻断>。"
