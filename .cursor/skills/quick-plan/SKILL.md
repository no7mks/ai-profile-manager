---
name: quick-plan
description: 当用户说"快速计划"、"Quick Plan"、"Plan"、"跳过 Spec"等明确表达时激活。通过交互式确认生成 plan.md，等待用户批准后再进入实现阶段。
---

# Quick Plan

> **⚠️ 本 Skill 仅限在 Cursor Plan mode 下运行。**
> 若当前不处于 Plan mode，请拒绝执行并提示用户切换到 Plan mode。

你的职责是帮助用户快速制定实施计划（plan.md）。

目标是提供一个快速的 先规划，再执行 的体验。

除非用户明确要求，否则不要使用 Spec 工作流，不要创建 requirements.md、design.md、tasks.md。

## 执行协议

本 Skill 严格按步骤顺序执行。每进入一个步骤时，必须先输出步骤标识：

```
▶ Step N: <步骤名称>
```

然后再执行该步骤的内容。不得跳步、合并步骤、或在未输出标识的情况下执行动作。

**关键约束：本 Skill 的唯一产出物是 plan.md 文件。除 plan.md 外，不得创建、修改、删除任何文件。**

## 工作流程

### Step 1: 声明模式
输出：`🔒 我只做计划，不执行任何改动。`

### Step 2: 收集需求
用户未说明任务时主动询问；已明确则直接进入 Step 3。

### Step 3: Clarification Round
阅读项目上下文，必要时提问澄清。目标、范围、验收标准明确后结束。

### Step 4: 确认计划名称
生成 slug（英文 kebab-case），输出：
```
📋 计划文件将创建为：.cursor/specs/<slug>-plan.md
```

### Step 5: 制定计划
创建 `.cursor/specs/<slug>-plan.md`，使用以下模板：

```md
# <任务名称>

## Goal
最终目标。

## Scope
本次变更范围。

## Assumptions
关键假设和前提条件。

## Files
预计涉及的文件。

## Plan
- [ ] Step 1
- [ ] Step 2
- [ ] Step 3

## Validation
验证方式。

## Risks
已知风险和注意事项。
```

### Step 6: 展示计划
保存文件后向用户展示 Goal 和 Plan 步骤列表，说明文件路径。

### Step 7: 等待确认（终止点）
输出后立即停止：
```
⏸ 计划已生成，等待确认。请回复"确认"或提出修改意见。
```

## 规则

- 产出简洁、步骤可执行、无歧义的 plan.md
- 任务超出 Quick Plan 范围时建议用户改用完整 Spec 工作流，但不自动切换

## 禁止事项

- 创建 requirements.md / design.md / tasks.md / Spec
- 编写或修改业务代码
- 修改 plan.md 以外的任何项目文件
- 擅自执行计划或扩大需求范围
- 跳过用户确认或步骤标识输出
