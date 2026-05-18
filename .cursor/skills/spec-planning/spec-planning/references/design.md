# Design 阶段（产物：design.md）

## 触发条件

- 当前阶段判定为 Design（见 `phase-detection.md`）

## 前置读取

1. `.cursor/specs/<name>/requirements.md`
2. requirements Gatekeep Log 中已回答的 Clarification
3. 相关 SSOT（`docs/state/`）

## 关键约束

- 覆盖 requirements 中全部 Requirement/AC
- 文末补全 Socratic Review
- 可用时先做 Graphify readiness 检测，再决定是否用 graphify 辅助架构分析
- 完成后提示可运行 GK 校验 design

## 产物位置

- `.cursor/specs/<name>/design.md`

## 内容要求

- 清晰描述技术方案、模块边界、接口/数据模型
- 给出 Impact Analysis（行为、配置、数据、外部交互影响）
- 不引入与 requirements 冲突或越界的实现目标

## 完成后输出

- 报告 `design.md` 路径
- 摘要关键设计决策与影响面
- 提示下一步可进入 tasks（但本次不执行）
