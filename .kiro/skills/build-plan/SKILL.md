---
name: build-plan
description: 当用户说"执行计划"、"Build Plan"、"按 plan 开发" 等明确表达时激活。读取已确认的 plan.md，按计划实施变更、更新进度并完成验证。
---

# Build Plan

你的职责是执行已经由用户确认的 plan.md。

目标是提供一个快速的 按计划执行 的体验。

除非用户明确要求，否则不要使用 Kiro Spec 工作流，不要创建 requirements.md、design.md、tasks.md。

## 执行协议

本 Skill 严格按步骤顺序执行。每进入一个步骤时，必须先输出步骤标识：

```
▶ Step N: <步骤名称>
```

然后再执行该步骤的内容。不得跳步、合并步骤、或在未输出标识的情况下执行动作。

## 工作流程

### Step 1: 声明模式

输出以下固定文本，不做任何其他动作：

```
🔨 进入 Build Plan 模式，按已确认计划执行。
```

### Step 2: 查找计划

优先读取：

```text
.kiro/specs/<slug>-plan.md
```

如果存在多个候选计划，且用户没有指定：

要求用户选择。

不要猜测。

如果未找到任何 plan.md，告知用户并建议使用 quick-plan 先创建计划。

### Step 3: 审阅计划

完整阅读 plan.md。

确认以下内容：

* Goal
* Scope
* Assumptions
* Files
* Plan
* Validation
* Risks

如果计划缺少关键信息，或与当前代码明显不一致：

* 如果只是补充细节（如补充遗漏的文件），可以直接更新并告知用户
* 如果涉及 Goal 或 Scope 变更，必须更新 plan.md、说明原因并等待用户确认

不要直接实施新的方案。

### Step 4: 执行计划

先分析 Plan 中的步骤是否有同步执行的可能性，规划出执行批次，然后按照批次顺序执行；同一批次可以并行，但要一起执行完才能继续下一个批次。

每次优先完成一个未完成步骤：

```md
- [ ] Step
```

完成后更新为：

```md
- [x] Step
```

允许：

* 修改代码
* 创建文件
* 更新测试
* 更新必要文档
* 执行验证命令

要求：

* 严格遵守 Scope
* 优先修改 Files 中列出的文件
* 保持修改范围最小化
* 保持现有代码风格
* 不要擅自扩大需求范围

### Step 5: 验证

按照 plan.md 中的 Validation 执行验证。

如果验证通过：

记录结果。

如果验证失败：

* 优先修复与当前 Step 直接相关的问题
* 重新验证
* 如果无法修复，记录失败原因和阻塞点

不要为了通过验证而进行无关重构。

### Step 6: 更新计划

每完成一个 Step 后，更新 plan.md。

必要时可以补充：

* 实际修改文件
* 验证结果
* 遗留问题
* Blocker

不要重写整个 plan.md，除非用户明确要求。

### Step 7: 汇报结果

每个批次执行完成后汇报；如果单个 step 遇到阻塞，立即汇报。

汇报格式：

```text
已完成：

- [x] Step ...

验证结果：

PASS / FAIL / NOT RUN

剩余步骤：

- [ ] Step ...
```

如果全部完成：

```text
✅ 计划已完成。
```

### Step 8: 收敛计划文档

计划完成后，将 plan.md 移动到 `changes/unreleased/specs/`：

```text
mv .kiro/specs/<slug>-plan.md → changes/unreleased/specs/<slug>-plan.md
```

如果当前在 feature 分支且即将执行 gitflow finish，可以跳过（finish 流程会统一处理）。

## 规则

Build Plan 的目标是按已确认计划交付实现，而不是重新设计方案。

优先关注：

* 当前 Step 是否完成
* 是否符合 Scope
* 是否通过 Validation
* plan.md 是否同步更新

不要反复讨论设计，除非发现计划存在明显问题。

## 需要重新确认的情况

出现以下情况时，必须停止并等待用户确认：

* 计划与当前代码明显冲突
* 需要修改 Scope 之外的文件
* 需要改变 Goal
* 需要引入新依赖
* 需要进行架构调整
* 验证方式不可执行
* 风险明显高于计划描述
* 需要升级为 Kiro Spec

## 禁止事项

* 跳过 plan.md
* 跳过用户确认
* 擅自扩大需求范围
* 擅自改变 Goal
* 擅自引入新依赖
* 自动创建 requirements.md
* 自动创建 design.md
* 自动创建 tasks.md
* 自动创建 Spec
* 自动重构无关代码
* 为了验证通过而修改无关逻辑
* 跳过步骤标识输出
* 合并多个步骤在同一轮执行而不输出各步骤标识
