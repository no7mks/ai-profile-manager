---
name: build-plan
description: 当用户说"执行计划"、"Build Plan"、"按 plan 开发" 等明确表达时激活。读取已确认的 plan.md，按计划实施变更、更新进度并完成验证。
---

# Build Plan

> **⚠️ 本 Skill 必须在 Cursor Agent mode 下运行。** 若当前不是 Agent mode，请拒绝执行并提示用户切换。

你的职责是执行已经由用户确认的 plan.md。

目标是提供一个快速的"按计划执行"的体验。

除非用户明确要求，否则不要使用 Spec 工作流，不要创建 requirements.md、design.md、tasks.md。

## 执行协议

本 Skill 严格按步骤顺序执行。每进入一个步骤时，必须先输出步骤标识：
`▶ Step N: <步骤名称>`
然后再执行该步骤的内容。不得跳步、合并步骤、或在未输出标识的情况下执行动作。

## 工作流程

### Step 1: 声明模式
输出：`🔨 进入 Build Plan 模式，按已确认计划执行。`

### Step 2: 查找计划
优先读取：`.cursor/specs/<slug>-plan.md`
如果存在多个候选计划且用户没有指定，要求用户选择，不要猜测。
如果未找到任何 plan.md，告知用户并建议使用 quick-plan 先创建计划。

### Step 3: 审阅计划
完整阅读 plan.md，确认包含：Goal、Scope、Assumptions、Files、Plan、Validation、Risks。
- 若只是补充细节（如补充遗漏的文件），可直接更新并告知用户
- 若涉及 Goal 或 Scope 变更，必须更新 plan.md、说明原因并等待用户确认

### Step 4: 执行计划
分析 Plan 中的步骤，按顺序逐步执行。Agent 直接执行每个步骤，不使用 sub-agent。
允许：修改代码、创建文件、更新测试、更新必要文档、执行验证命令。
要求：严格遵守 Scope；优先修改 Files 中列出的文件；保持修改范围最小化；保持现有代码风格；不擅自扩大需求范围。

### Step 5: 验证
按照 plan.md 中的 Validation 执行验证。验证失败时优先修复当前 Step 相关问题并重新验证。
不要为了通过验证而进行无关重构。

### Step 6: 更新计划
每完成一个 Step 后，将对应步骤标记为 `[x]`，并可补充：实际修改文件、验证结果、遗留问题。

### Step 7: 汇报结果
汇报格式：已完成步骤 / 验证结果（PASS/FAIL/NOT RUN）/ 剩余步骤。
如果全部完成：`✅ 计划已完成。`

### Step 8: 收敛计划文档
计划完成后，将 plan.md 移动到归档路径：
`mv .cursor/specs/<slug>-plan.md → changes/unreleased/specs/<slug>-plan.md`
如果当前在 feature 分支且即将执行 gitflow finish，可以跳过。

## 规则

优先关注：当前 Step 是否完成、是否符合 Scope、是否通过 Validation、plan.md 是否同步更新。
不要反复讨论设计，除非发现计划存在明显问题。

## 需要重新确认的情况

出现以下情况时，必须停止并等待用户确认：
- 计划与当前代码明显冲突
- 需要修改 Scope 之外的文件
- 需要改变 Goal 或引入新依赖
- 需要进行架构调整
- 验证方式不可执行或风险明显高于计划描述

## 禁止事项

- 跳过 plan.md 或跳过用户确认
- 擅自扩大需求范围或改变 Goal
- 擅自引入新依赖
- 自动创建 requirements.md / design.md / tasks.md / Spec
- 自动重构无关代码或为了验证通过而修改无关逻辑
- 跳过步骤标识输出或合并多个步骤在同一轮执行而不输出各步骤标识
