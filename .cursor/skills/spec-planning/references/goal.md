# Goal 阶段（产物：goal.md）

## 触发条件

- 当前阶段判定为 Goal（见 `phase-detection.md`）

## 输入读取

1. 需求来源文档
   - Feature：proposal
   - Hotfix：issue 或 note
   - Release：本次纳入的 feature proposal 与已完成 spec
2. `docs/state/`（SSOT）

## Clarification 要求

- 至少提出 3 个问题，逐个提问，逐个等待回答
- 问题聚焦 scope/意图边界，不提前进入技术方案与任务拆分
- 每题至少 3 个选项，最后一个固定为“补充说明（请描述）”

## 产物位置

- `.cursor/specs/<name>/goal.md`

## 文档结构

```markdown
# Spec Goal: <标题>

## 来源
## 背景摘要
## 目标
## 不做的事情（Non-Goals）
## Clarification 记录
## 约束与决策
```

## 完成后输出

- 报告 `goal.md` 路径
- 摘要关键决策
- 提示下一步可进入 requirements（但本次不执行）
