# Design 阶段（产物：design.md）

> 本文件定义 spec planning 的第三阶段：基于 requirements 设计技术方案，产出 `design.md`。

---

## TOC

- [触发条件](#触发条件)
- [执行步骤](#执行步骤)
- [前置读取](#前置读取)
- [关键约束](#关键约束)
- [产物格式](#产物格式)
- [质量要求](#质量要求)
- [完成后输出](#完成后输出)

---

## 触发条件

- 当前阶段判定为 Design（见 `phase-detection.md`）

---

## 执行步骤

1. **读取前置文件**：按「前置读取」清单获取 requirements、GK Clarification、SSOT
2. **尝试委托系统 sub-agent**：调用 Kiro 内置的 design spec 子代理，将 requirements 内容与本文件的产物格式约束作为输入，由其生成 design.md 初稿
3. **若委托成功** → 跳至步骤 13（Socratic Review）
4. **若委托不可用或失败** → 继续以下手动步骤：
5. **Graphify readiness 检测**（可选）：判断是否可用 graphify 辅助架构分析
6. **设计架构**：确定模块划分、依赖关系、数据流
7. **定义接口与数据模型**：为每个模块设计接口签名（参数类型、返回类型、异常类型）
8. **覆盖校验**：逐条检查 requirements 中的每条 Requirement/AC 是否都有对应设计
9. **Impact Analysis**：逐项检查受影响的 state 文档、行为变化、数据模型变更等
10. **Alternatives Considered**：列出至少 1-2 个备选方案及落选理由
11. **写入产物**：按文档结构写入 `design.md`
12. **诊断检查**：对写入的 `design.md` 执行 `getDiagnostics`，有问题则修正
13. **Socratic Review**：读取 steering `gatekeeping/gk-log-format.md` 获取格式，自检写入 `gk-logs.md`
14. **输出完成报告**：按「完成后输出」格式报告

---

## 前置读取

1. `<spec-dir>/<name>/requirements.md`
2. requirements Gatekeep Log 中已回答的 Clarification
3. 相关 SSOT（`docs/state/`）

---

## 关键约束

- 覆盖 requirements 中全部 Requirement/AC
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 design.md 中
- 可用时先做 Graphify readiness 检测，再决定是否用 graphify 辅助架构分析
- 完成后提示可运行 GK 校验 design

---

## 产物格式

### 产物位置

- `<spec-dir>/<name>/design.md`

### 文档结构（必须遵循）

一级标题不约束格式。必须包含以下 section：

| Section | 必要性 |
|---------|--------|
| `## Overview` | 必须 |
| `## Architecture` | 必须 |
| `## Components and Interfaces` | 必须 |
| `## Data Models` | 必须 |
| `## Correctness Properties` | 推荐 |
| `## Error Handling` | 推荐 |
| `## Testing Strategy` | 推荐 |
| `## Impact Analysis` | 必须 |
| `## Alternatives Considered` | 推荐 |

各 section 之间使用 `---` 分隔。

### Correctness Properties 格式

如果包含 `## Correctness Properties`，每条 property 必须使用三级标题：

```markdown
### Property N: <名称>

- **Validates**: Requirements X.Y, X.Z（X = Requirement 编号，Y/Z = AC 编号；仅引用到 Requirement 级别时可省略 .Y）
- **条件**: <何时成立>
- **保证**: <系统保证什么>
- **违反后果**: <如果违反会怎样>
```

### Impact Analysis 必须覆盖

| 检查项 | 说明 |
|--------|------|
| 受影响的 state 文档 | 具体文件名及 section |
| 现有模块行为变化 | model / service / CLI 行为的具体变化 |
| 数据模型变更 | 如涉及，是否提醒了旧数据兼容 |
| 外部系统交互变化 | 如涉及，说明变化内容 |
| 配置项变更 | 新增、删除、默认值变化 |

---

## 质量要求

- 技术选型有明确理由（不是"因为流行"）
- 接口签名足够清晰，能让 task 独立执行（参数类型、返回类型、异常类型）
- 模块间依赖关系清晰，无循环依赖
- 无过度设计（不引入当前不需要的抽象层）
- 与 state 文档中描述的现有架构一致
- 不引入与 requirements 冲突或越界的实现目标
- 给出 Alternatives Considered（至少 1-2 个备选方案及落选理由）

---

## 完成后输出

- 报告 `design.md` 路径
- 摘要关键设计决策与影响面
- 提示下一步可进入 tasks（但本次不执行）
