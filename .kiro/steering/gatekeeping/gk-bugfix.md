---
inclusion: manual
description: Spec gatekeeper 校验 bugfix 阶段的详细指引。由 spec-gatekeeper agent 在校验 bugfix.md 时读取。
---

# Bugfix Gatekeep 指引

本文件定义 bugfix.md 的校验标准。Gatekeeper 按以下清单逐项检查，发现问题直接修正。

---

## 执行顺序

1. 机械扫描
2. 结构校验
3. 术语表校验
4. 行为条款校验
5. 回归防护校验
6. Socratic Review 校验
7. 目的性审查
8. 将修正项写入 Gatekeep Log
9. 生成 Clarification Round（为 design 阶段准备）
10. Completion：向 main-agent 返回结果

---

## 1. 机械扫描

- [ ] 无 TBD / TODO / 待定 / 占位符
- [ ] 无空 section 或不完整的列表
- [ ] 内部引用一致（术语表中的术语在正文中使用）
- [ ] 无 markdown 格式错误（未闭合的代码块、错误的标题层级）

发现问题直接修正，不需要在 Gatekeep Log 中逐一列出机械扫描的修正。

---

## 2. 结构校验

bugfix.md 必须包含以下 section，且顺序正确：

| 序号 | Section | 必要性 | 说明 |
|------|---------|--------|------|
| 1 | 一级标题 | 必须 | 格式不约束 |
| 2 | `## Introduction` | 必须 | bug 背景、影响范围、修复目标 |
| 3 | `## Reproduction Steps` | 推荐 | 复现步骤 |
| 4 | `## Environment / Constraints` | 推荐 | 环境与约束 |
| 5 | `## Glossary` | 可选 | 术语表，`- **Term**: 定义` 格式；术语少时可省略 |
| 6 | `## Current Behavior (Defect)` | 必须 | 当前错误行为 |
| 7 | `## Expected Behavior (Correct)` | 必须 | 修复后正确行为 |
| 8 | `## Unchanged Behavior (Regression Prevention)` | 必须 | 不应受影响的行为 |

### 检查项

- [ ] 一级标题存在
- [ ] Introduction 存在，描述了 bug 影响范围
- [ ] Current Behavior 存在且至少有一条行为描述
- [ ] Expected Behavior 存在且至少有一条行为描述
- [ ] Unchanged Behavior 存在且至少有一条行为描述
- [ ] Reproduction Steps 和 Environment / Constraints（如存在）位于 Current Behavior 之前
- [ ] 各 section 之间使用 `---` 分隔

---

## 3. 术语表校验

> 仅当 `## Glossary` section 存在时执行本节校验。Glossary 为可选项。

- [ ] Glossary 中的术语在正文行为描述中被实际使用（无孤立术语）
- [ ] 行为描述中使用的领域概念在 Glossary 中有定义（无未定义术语）
- [ ] 术语格式为 `- **Term**: 定义`

---

## 4. 行为条款校验

### Current Behavior

- [ ] 描述的是**实际发生的错误行为**，不使用 SHALL（这不是期望行为）
- [ ] 使用 `WHEN <条件> THEN THE <Subject> <错误行为>` 格式
- [ ] 条件描述具体、可复现

### Expected Behavior

- [ ] 使用 `WHEN <条件> THEN THE <Subject> SHALL <正确行为>` 格式
- [ ] 与 Current Behavior 条目一一对应（每个 defect 都有对应的 fix）
- [ ] Subject 使用 Glossary 中定义的术语

### 内容边界

行为描述应聚焦外部可观察行为，不应包含实现细节。

**不应出现**：具体库名/框架名、内部结构描述、实现策略、具体类名/方法签名。
**可以出现**：质量属性、外部可观察的行为约束、Glossary 中定义的领域术语。

---

## 5. 回归防护校验

- [ ] Unchanged Behavior 使用 `WHEN <条件> THEN THE <Subject> SHALL CONTINUE TO <行为>` 格式
- [ ] 覆盖了 bug 修复可能波及的相邻功能
- [ ] 至少列出 1 条回归防护行为
- [ ] 回归防护条目与 Expected Behavior 不重复（防护的是"不应改变的"，不是"要修复的"）

---

## 6. Socratic Review 校验

gatekeeper 应补充 Socratic Review（写入 `<spec-dir>/<name>/gk-logs.md` 的 `## Bugfix Socratic Review` section），至少覆盖：

- bug 的根因是否已在 Current Behavior 中充分体现？
- Expected Behavior 是否完整覆盖了所有 defect 场景？
- Unchanged Behavior 是否遗漏了可能被波及的功能？
- 修复是否可能引入新的边界条件？
- 复现步骤是否足够让 design 阶段定位代码？

---

## 7. 目的性审查

Bugfix 文档的核心目的是：**让读者清楚地知道什么坏了、应该怎样、什么不能动**。

### 审查清单

- [ ] **Goal CR 回应**：goal.md 中 Clarification Round 的用户决策是否在 bugfix.md 中体现。
- [ ] **Defect 清晰度**：读完 Current Behavior 后能否准确理解 bug 表现？
- [ ] **Fix 明确性**：Expected Behavior 是否构成充分的修复验收条件？
- [ ] **防护充分性**：Unchanged Behavior 是否覆盖了关键的相邻功能？
- [ ] **可 design 性**：design 阶段的 agent 是否有足够信息进行根因分析和方案设计？

如果不达标，直接修正。修正后在 Gatekeep Log 中记录（修正类型为 `目的`）。

---

## 8. Gatekeep Log

将校验过程中的修正项写入 `<spec-dir>/<name>/gk-logs.md` 的 `## Bugfix Gatekeep Log` section（不写在 bugfix.md 中）。

---

## 9. Clarification Round (CR)

校验完成后，生成面向 design 阶段的 CR 问题（**3 个以上**）。

CR 写入 bugfix.md 末尾的 `## Clarification Round` section。

CR 聚焦 **bugfix 到 design 的衔接**——修复方案可能存在多种路径，需要用户在进入 design 前做出决策。

聚焦方向：
- 修复范围是否需要限定（最小修复 vs 顺带改善）？
- 是否存在多种修复路径（如修改调用方 vs 修改被调用方）？
- 回归防护的测试策略偏好（单元测试 vs 集成测试 vs 手动验证）？
- 是否需要向后兼容已有的错误数据？
- 修复是否需要同步到其他分支？

每个问题提供 **至少 3 个选项**，写入 Gatekeep Log 的 `### Clarification Round` 小节。

---

## 10. Completion

向 main-agent 返回：

1. **校验结果摘要**：通过 / 已修正后通过，列出修正项
2. **CR 待确认**：告知有待用户回答的 CR 问题

Main-agent 收到后逐题与用户交互，将回答写入对应的 `**A:**` 行。
