# Requirements 阶段（产物：requirements.md 或 bugfix.md）

> 本文件定义 spec planning 的第二阶段：将 goal 转化为结构化需求文档。根据路径分支产出不同文件。

---

## TOC

- [路径分支](#路径分支)
- [Feature 路径](#feature-路径requirements)
  - [执行步骤](#feature-执行步骤)
  - [前置读取](#feature-前置读取)
  - [关键约束](#feature-关键约束)
  - [产物格式](#feature-产物格式)
  - [完成后输出](#feature-完成后输出)
- [Bugfix 路径](#bugfix-路径bugfix-analysis)
  - [执行步骤](#bugfix-执行步骤)
  - [前置读取](#bugfix-前置读取)
  - [关键约束](#bugfix-关键约束)
  - [产物格式](#bugfix-产物格式)
  - [完成后输出](#bugfix-完成后输出)

---

## 路径分支

| 路径 | 产物 | 触发条件 |
|------|------|----------|
| Feature | `requirements.md` | 默认 |
| Bugfix | `bugfix.md` | hotfix 分支（默认）/ 用户显式声明 |

> **规则**：`hotfix/` 分支默认走 Bugfix 路径，无需用户额外确认。

---

## Feature 路径：Requirements

### Feature 执行步骤

1. **读取前置文件**：按「前置读取」清单获取 goal、Clarification 回答、SSOT
2. **提取需求要素**：从 goal 的目标、决策、Clarification 中识别外部可观察行为
3. **建立术语表**：识别领域概念，撰写 Glossary
4. **撰写 Introduction**：说明 feature 范围，明确 Non-scope
5. **逐条撰写 Requirement**：每条包含 User Story + AC（EARS 格式）
6. **交叉校验**：确认 Glossary ↔ AC 双向引用无孤立/未定义术语
7. **写入产物**：按文档结构写入 `requirements.md`
8. **Socratic Review**：读取 rule `gatekeeping/gk-log-format.mdc` 获取格式，自检写入 `gk-logs.md`
9. **输出完成报告**：按「完成后输出」格式报告

### Feature 前置读取

1. `<spec-dir>/<name>/goal.md`
2. Goal 中 Clarification 已回答项（必须反映到 requirements）
3. 相关 SSOT（`docs/state/`）

### Feature 关键约束

- 文档以中文为主，英文术语可保留
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 requirements.md 中
- 完成后提示可运行 GK 校验 requirements

### Feature 产物格式

#### 产物位置

- `<spec-dir>/<name>/requirements.md`

#### 文档结构（必须遵循）

一级标题必须为 `# Requirements Document`（严格匹配）。必须包含以下 section：

| Section | 必要性 | 说明 |
|---------|--------|------|
| `## Introduction` | 必须 | 说明 feature 范围，必须明确列出 Non-scope |
| `## Glossary` | 必须 | 术语表，格式 `- **Term**: 定义` |
| `## Requirements` | 必须 | 每条为 `### Requirement N: 名称` |

各 section 之间使用 `---` 分隔。

#### Requirement 条款格式

```markdown
### Requirement N: 名称

**User Story:** As a <role>, I want <feature>, so that <benefit>

#### Acceptance Criteria

1. THE <Subject> SHALL ...
2. WHEN <条件>, THE <Subject> SHALL ...
3. IF <条件>, THEN THE <Subject> SHALL ...
```

#### AC 语体规则（EARS 格式）

| 模式 | 格式 | 用途 |
|------|------|------|
| 无条件 | `THE <Subject> SHALL <行为>` | 必须具备的行为 |
| 触发条件 | `WHEN <条件>, THE <Subject> SHALL <行为>` | 特定条件下的行为 |
| 异常/边界 | `IF <条件>, THEN THE <Subject> SHALL <行为>` | 异常或边界条件下的行为 |

规则：
- Subject 使用 Glossary 中定义的术语
- AC 编号连续，无跳号
- 每条 AC 描述一个可独立验证的行为

#### 内容边界

Requirements 聚焦外部可观察行为，不应包含实现细节。

**不应出现**：具体库名/框架名、内部结构描述、实现策略、具体类名/方法签名。
**可以出现**：质量属性、外部可观察的行为约束、Glossary 中定义的领域术语。

#### 术语表规则

- Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- AC 中使用的领域概念在 Glossary 中有定义（无未定义术语）
- 格式：`- **Term**: 定义`

### Feature 完成后输出

- 报告 `requirements.md` 路径
- 标注已承接的 goal 关键决策
- 提示下一步可进入 design（但本次不执行）

---

## Bugfix 路径：Bugfix Analysis

### Bugfix 执行步骤

1. **读取前置文件**：按「前置读取」清单获取 goal、Clarification 回答、SSOT、相关源代码
2. **复现分析**：阅读源代码，理解 bug 触发路径，撰写 Reproduction Steps
3. **描述当前行为**：用 EARS 格式（不含 SHALL）描述 defect 行为
4. **描述期望行为**：用 EARS 格式（含 SHALL）描述修复后正确行为
5. **识别回归风险**：列出不应被修改影响的现有行为（SHALL CONTINUE TO）
6. **补充环境/约束**：记录影响版本、兼容性要求等
7. **写入产物**：按文档结构写入 `bugfix.md`
8. **Socratic Review**：读取 rule `gatekeeping/gk-log-format.mdc` 获取格式，自检写入 `gk-logs.md`
9. **输出完成报告**：按「完成后输出」格式报告

### Bugfix 前置读取

1. `<spec-dir>/<name>/goal.md`
2. Goal 中 Clarification 已回答项
3. 相关 SSOT（`docs/state/`）
4. 相关源代码（复现路径涉及的模块）

### Bugfix 关键约束

- 文档以中文为主，英文术语可保留
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 bugfix.md 中
- 完成后提示可运行 GK 校验 bugfix（使用 `gk-bugfix` 而非 `gk-requirements`）

### Bugfix 产物格式

#### 产物位置

- `<spec-dir>/<name>/bugfix.md`

#### 文档结构（必须遵循）

| Section | 必要性 | 说明 |
|---------|--------|------|
| 一级标题 | 必须 | 格式不约束 |
| `## Introduction` | 必须 | bug 背景与影响范围 |
| `## Reproduction Steps` | 推荐 | 复现步骤 |
| `## Environment / Constraints` | 推荐 | 环境与约束信息 |
| `## Glossary` | 可选 | 术语表；术语少时可省略 |
| `## Current Behavior (Defect)` | 必须 | 当前错误行为，EARS 格式 |
| `## Expected Behavior (Correct)` | 必须 | 修复后正确行为，EARS 格式 |
| `## Unchanged Behavior (Regression Prevention)` | 必须 | 不应受影响的行为 |

各 section 之间使用 `---` 分隔。

#### AC 语体规则

- `WHEN <条件> THEN THE <Subject> SHALL ...`（正确行为）
- `WHEN <条件> THEN THE <Subject> SHALL CONTINUE TO ...`（回归防护）
- Current Behavior 不使用 SHALL（描述现状，非期望行为）

### Bugfix 完成后输出

- 报告 `bugfix.md` 路径
- 标注已承接的 goal 关键决策
- 提示下一步可进入 design（但本次不执行）
- 提示 GK 校验使用 `gk-bugfix`
