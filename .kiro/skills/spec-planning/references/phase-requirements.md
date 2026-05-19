# Requirements 阶段（产物：requirements.md 或 bugfix.md）

## 路径分支

本阶段根据 `phase-detection.md` 的路径选择规则，产出不同文件：

| 路径 | 产物 | 触发条件 |
|------|------|----------|
| Feature | `requirements.md` | 默认 |
| Bugfix | `bugfix.md` | hotfix 分支（默认）/ 用户显式声明 |

> **规则**：`hotfix/` 分支默认走 Bugfix 路径，无需用户额外确认。

## Kiro Feature Spec 工作流

进入 Requirements 阶段时，主 agent 应启动 Kiro 原生的 Feature Spec workflow — 以 **requirements-first** 模式创建 `requirements.md`。本文件的格式规范用于 GK 校验时的标准。

---

## Feature 路径：Requirements

### 触发条件

- 当前阶段判定为 Requirements 且路径为 Feature（见 `phase-detection.md`）

## 前置读取

1. `<spec-dir>/<name>/goal.md`
2. Goal 中 Clarification 已回答项（必须反映到 requirements）
3. 相关 SSOT（`docs/state/`）

## 关键约束

- 文档以中文为主，英文术语可保留
- 产物较大时采用分段写入，避免一次性大写入
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 requirements.md 中
- 完成后提示可运行 GK 校验 requirements

## 产物位置

- `<spec-dir>/<name>/requirements.md`

## 文档结构（必须遵循）

一级标题必须为 `# Requirements Document`（严格匹配）。必须包含以下 section（英文名）：

| Section | 必要性 |
|---------|--------|
| `## Introduction` | 必须 — 说明 feature 范围，明确 Non-scope |
| `## Glossary` | 必须 — 术语表，格式 `- **Term**: 定义` |
| `## Requirements` | 必须 — 每条为 `### Requirement N: 名称` |

各 section 之间使用 `---` 分隔。

## Requirement 条款格式

每条 requirement 包含：

```markdown
### Requirement N: 名称

**User Story:** As a <role>, I want <feature>, so that <benefit>

#### Acceptance Criteria

1. THE <Subject> SHALL ...
2. WHEN <条件>, THE <Subject> SHALL ...
3. IF <条件>, THEN THE <Subject> SHALL ...
```

### AC 语体规则（EARS 格式）

- 使用 `THE <Subject> SHALL ...` 描述必须具备的行为
- 使用 `WHEN <条件>, THE <Subject> SHALL ...` 描述触发条件下的行为
- 使用 `IF <条件>, THEN THE <Subject> SHALL ...` 描述异常/边界条件下的行为
- Subject 使用 Glossary 中定义的术语
- AC 编号连续，无跳号

### 内容边界

Requirements 聚焦外部可观察行为，不应包含实现细节。

**不应出现**：具体库名/框架名、内部结构描述、实现策略、具体类名/方法签名。
**可以出现**：质量属性、外部可观察的行为约束、Glossary 中定义的领域术语。

## 术语表规则

- Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- AC 中使用的领域概念在 Glossary 中有定义（无未定义术语）
- 格式：`- **Term**: 定义`

## 内容要求

- 覆盖目标、范围、术语和验收条款
- 面向外部可观察行为，避免实现细节
- 与 goal 决策一致，不重复冲突决策
- Introduction 明确 Non-scope（不涉及的内容）

## 完成后输出

- 报告 `requirements.md` 路径
- 标注已承接的 goal 关键决策
- 提示下一步可进入 design（但本次不执行）

---

## Bugfix 路径：Bugfix Analysis（产物：bugfix.md）

### 触发条件

- 当前阶段判定为 Bugfix Analysis（见 `phase-detection.md`）
- 分支前缀为 `hotfix/` 时自动进入本路径

### 前置读取

1. `<spec-dir>/<name>/goal.md`
2. Goal 中 Clarification 已回答项
3. 相关 SSOT（`docs/state/`）
4. 相关源代码（复现路径涉及的模块）

### 关键约束

- 文档以中文为主，英文术语可保留
- 产物较大时采用分段写入
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 bugfix.md 中
- 完成后提示可运行 GK 校验 bugfix（使用 `gk-bugfix` 而非 `gk-requirements`）

### 产物位置

- `<spec-dir>/<name>/bugfix.md`

### 文档结构（必须遵循）

```markdown
# <标题（格式不约束）>

## Introduction

一段话说明 bug 的背景、影响范围和修复目标。

---

## Reproduction Steps

1. 步骤 1
2. 步骤 2
3. ...

---

## Environment / Constraints

- 影响版本 / 环境
- 修复约束（如不可改动的接口、向后兼容要求等）

---

## Glossary

- **Term**: 定义

---

## Current Behavior (Defect)

描述当前错误行为，使用 EARS 格式：

1. WHEN <条件> THEN THE <Subject> <错误行为描述>

---

## Expected Behavior (Correct)

描述修复后的正确行为：

1. WHEN <条件> THEN THE <Subject> SHALL <正确行为描述>

---

## Unchanged Behavior (Regression Prevention)

明确列出不应被修改影响的现有行为：

1. WHEN <条件> THEN THE <Subject> SHALL CONTINUE TO <现有行为描述>
```

### Section 说明

| Section | 必要性 | 说明 |
|---------|--------|------|
| 一级标题 | 必须 | 格式不约束 |
| `## Introduction` | 必须 | bug 背景与影响范围 |
| `## Reproduction Steps` | 推荐 | 复现步骤，位于行为描述之前 |
| `## Environment / Constraints` | 推荐 | 环境与约束信息，位于行为描述之前 |
| `## Glossary` | 可选 | 术语表；术语少时可省略 |
| `## Current Behavior (Defect)` | 必须 | 当前错误行为，EARS 格式 |
| `## Expected Behavior (Correct)` | 必须 | 修复后正确行为，EARS 格式 |
| `## Unchanged Behavior (Regression Prevention)` | 必须 | 不应受影响的行为 |

### AC 语体规则

与 Feature 路径一致，使用 EARS 格式：

- `WHEN <条件> THEN THE <Subject> SHALL ...`（正确行为）
- `WHEN <条件> THEN THE <Subject> SHALL CONTINUE TO ...`（回归防护）
- Current Behavior 不使用 SHALL（描述现状，非期望行为）

### 完成后输出

- 报告 `bugfix.md` 路径
- 标注已承接的 goal 关键决策
- 提示下一步可进入 design（但本次不执行）
- 提示 GK 校验使用 `gk-bugfix`
