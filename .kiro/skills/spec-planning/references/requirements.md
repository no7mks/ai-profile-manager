# Requirements 阶段（产物：requirements.md）

## 触发条件

- 当前阶段判定为 Requirements（见 `phase-detection.md`）

## 前置读取

1. `<spec-dir>/<name>/goal.md`
2. Goal 中 Clarification 已回答项（必须反映到 requirements）
3. 相关 SSOT（`docs/state/`）

## 关键约束

- 文档以中文为主，英文术语可保留
- 产物较大时采用分段写入，避免一次性大写入
- 文末补全 Socratic Review
- 完成后提示可运行 GK 校验 requirements

## 产物位置

- `<spec-dir>/<name>/requirements.md`

## 文档结构（必须遵循）

一级标题不约束格式。必须包含以下 section（英文名）：

| Section | 必要性 |
|---------|--------|
| `## Introduction` | 必须 — 说明 feature 范围，明确 Non-scope |
| `## Glossary` | 必须 — 术语表，格式 `- **Term**: 定义` |
| `## Requirements` | 必须 — 每条为 `### Requirement N: 名称` |
| `## Socratic Review` | 推荐 |

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
