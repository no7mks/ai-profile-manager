# Design 阶段（产物：design.md）

## 触发条件

- 当前阶段判定为 Design（见 `phase-detection.md`）

## 前置读取

1. `<spec-dir>/<name>/requirements.md`
2. requirements Gatekeep Log 中已回答的 Clarification
3. 相关 SSOT（`docs/state/`）

## 关键约束

- 覆盖 requirements 中全部 Requirement/AC
- 文末补全 Socratic Review
- 可用时先做 Graphify readiness 检测，再决定是否用 graphify 辅助架构分析
- 完成后提示可运行 GK 校验 design

## 产物位置

- `<spec-dir>/<name>/design.md`

## 文档结构（必须遵循）

一级标题不约束格式。必须包含以下 section（英文名）：

| Section | 必要性 |
|---------|--------|
| 技术方案主体（section 名自由） | 必须 — 承接 requirements，给出具体技术方案 |
| 接口 / 数据模型（section 名自由） | 必须 — 接口签名、数据模型、模块划分 |
| `## Impact Analysis` | 必须 — 影响分析 |
| `## Alternatives Considered` | 推荐 — 备选方案及落选理由 |
| `## Socratic Review` | 推荐 |

各 section 之间使用 `---` 分隔。

## Impact Analysis 必须覆盖

- 受影响的 state 文档条目（具体文件名及 section）
- 现有 model / service / CLI 行为的变化
- 是否涉及数据模型变更——如涉及，是否提醒了旧数据兼容
- 是否涉及外部系统交互变化
- 是否涉及配置项变更（新增、删除、默认值变化）

## 内容要求

- 清晰描述技术方案、模块边界、接口/数据模型
- 给出 Impact Analysis（覆盖上述检查项）
- 给出 Alternatives Considered（至少列出 1-2 个备选方案及落选理由）
- 不引入与 requirements 冲突或越界的实现目标
- 接口签名足够清晰，能让 task 独立执行
- 模块间依赖关系清晰，无循环依赖

## 完成后输出

- 报告 `design.md` 路径
- 摘要关键设计决策与影响面
- 提示下一步可进入 tasks（但本次不执行）
