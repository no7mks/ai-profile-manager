---
name: quick-plan
description: 当用户说“快速计划”、“Quick Plan”、“Plan”、“跳过 Spec”等明确表达时激活。通过交互式确认生成 plan.md，等待用户批准后再进入实现阶段。
---

# Quick Plan

你的职责是帮助用户快速制定实施计划（plan.md）。

目标是提供一个快速的 先规划，再执行 的体验

除非用户明确要求，否则不要使用 Kiro Spec 工作流，不要创建 requirements.md、design.md、tasks.md。

## 工作流程

### 1. 收集需求

如果用户尚未明确说明要做什么：

主动询问。

不要猜测。

### 2. Clarification Round

通过阅读项目代码、文档和目录结构理解上下文。

如果存在不确定性，可以向用户提问。每轮优先提出最重要的问题，避免一次提出大量问题。

当目标、范围、验收标准均已明确时结束澄清，不要为了提问而提问。

### 3. 制定计划

创建：

```text
.kiro/specs/<slug>-plan.md
```

slug 使用英文 kebab-case，由 agent 根据任务主题自动生成。

计划应聚焦于实施，不应编写详细设计文档。

使用以下格式：

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

要求：

* 计划应尽可能简洁
* Step 应明确且可执行
* Step 数量以完成任务所需为准
* 优先关注实施路径
* 避免过度设计
* Plan 的最后几步应始终包含：
  - state 文档同步（`docs/state/`）
  - manual 文档同步（`docs/manual/`）
  - code-review
  - final checkpoint（整体验证 + commit）

### 4. 展示计划

生成 plan.md 后：

* 保存文件
* 向用户展示 Goal 和 Plan 步骤列表，不必全文复述
* 说明计划文件路径

### 5. 等待确认

计划生成后立即停止。

等待用户确认。

不要：

* 编写代码
* 修改代码
* 执行计划
* 自动进入下一阶段
* 自动调用 build-plan

## 规则

优先产出一个简洁、步骤可执行、无歧义的 plan.md，而不是长篇分析。

如果任务规模明显超出 Quick Plan 范围，可以建议用户改用完整 Spec 工作流，但不要自动切换。

## 禁止事项

* 创建 requirements.md
* 创建 design.md
* 创建 tasks.md
* 创建 Spec
* 编写业务代码
* 修改业务代码
* 执行计划
* 擅自扩大需求范围
* 跳过用户确认
