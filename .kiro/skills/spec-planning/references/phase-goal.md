# Goal 阶段（产物：goal.md）

> 本文件定义 spec planning 的第一阶段：通过 Goal Clarification（GC）对话确定需求边界，产出 `goal.md`。

---

## TOC

- [触发条件](#触发条件)
- [执行步骤](#执行步骤)
- [输入读取](#输入读取)
- [Goal Clarification 规则](#goal-clarification-规则)
- [产物格式](#产物格式)
- [完成后输出](#完成后输出)

---

## 触发条件

- 当前阶段判定为 Goal（见 `phase-detection.md`）

---

## 执行步骤

1. **读取输入**：按「输入读取」清单获取来源文档与 SSOT
2. **生成 Clarification 问题**：基于来源文档，拟定至少 3 个 scope/意图边界问题
3. **逐个提问**：每次只提出 1 个问题，等待用户回答后再提下一个
4. **记录回答**：将每轮 Q&A 写入 Goal Clarification 记录
5. **判断收敛**：当 scope 边界已清晰、无重大歧义时，结束 Clarification
6. **撰写 goal.md**：按「产物格式」结构，将背景、目标、Non-Goals、决策等写入产物
7. **输出完成报告**：按「完成后输出」格式报告

---

## 输入读取

1. 需求来源文档
   - Feature：proposal 或 note
   - Hotfix：issue 或 note
   - Release：`changes/unreleased/` 下的 notes、proposals + `issues/` 中 status=closed 的 issue（已修复待发布）。Spec 仅为执行记录，可以参考，但不作为需求来源
2. `docs/state/`（SSOT）

---

## Goal Clarification 规则

- 至少提出 3 个问题，逐个提问，逐个等待回答
- 问题聚焦 scope/意图边界，不提前进入技术方案与任务拆分
- 每题至少 3 个选项，最后一个固定为"补充说明（请描述）"

---

## 产物格式

### 产物位置

- `<spec-dir>/<name>/goal.md`

### 文档结构

```markdown
# Spec Goal: <标题>

## 来源
## 背景摘要
## 目标
## 不做的事情（Non-Goals）
## Goal Clarification
## 约束与决策
```

---

## 完成后输出

- 报告 `goal.md` 路径
- 摘要关键决策
- 提示下一步可进入 requirements（但本次不执行）
