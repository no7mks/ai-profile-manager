# Requirements 阶段（产物：requirements.md）

## 触发条件

- 当前阶段判定为 Requirements（见 `phase-detection.md`）

## 前置读取

1. `.cursor/specs/<name>/goal.md`
2. Goal 中 Clarification 已回答项（必须反映到 requirements）
3. 相关 SSOT（`docs/state/`）

## 关键约束

- 文档以中文为主，英文术语可保留
- 产物较大时采用分段写入，避免一次性大写入
- 文末补全 Socratic Review
- 完成后提示可运行 GK 校验 requirements

## 产物位置

- `.cursor/specs/<name>/requirements.md`

## 内容要求

- 覆盖目标、范围、术语和验收条款
- 面向外部可观察行为，避免实现细节
- 与 goal 决策一致，不重复冲突决策

## 完成后输出

- 报告 `requirements.md` 路径
- 标注已承接的 goal 关键决策
- 提示下一步可进入 design（但本次不执行）
