---
name: spec-planning
description: 统一执行 spec planning 四阶段（goal/requirements/design/tasks），结合 spec-planner agent 与 spec 规则。使用轻入口 SKILL.md + references；每次只执行一个阶段并产出对应文档。
---

# Spec Planning

统一 spec 规划工作流的单一 skill。能力完整覆盖四部曲，但 `SKILL.md` 仅保留入口与路由，详细规范见 references。

## 触发场景

- 用户提到 `spec`、`planning`、`goal`、`requirements`、`design`、`tasks`、`plan.md`
- 用户要求推进 spec 规划，但希望分阶段确认
- 用户提到需要中途等待 GK（Gatekeeper）或人工介入

## 使用原则

1. 始终先做阶段判定，再执行阶段动作。
2. 一次只做一步：当前阶段完成后立即停止，等待用户/GK。
3. 四阶段产物固定为：`goal.md`、`requirements.md`、`design.md`、`tasks.md`。
4. 若用户提到 `plan.md`，视为 `tasks.md` 的历史命名并在输出中显式说明。

## 流程入口

- 阶段判定：见 [references/phase-detection.md](references/phase-detection.md)
- Goal 阶段：见 [references/goal.md](references/goal.md)
- Requirements 阶段：见 [references/requirements.md](references/requirements.md)
- Design 阶段：见 [references/design.md](references/design.md)
- Tasks 阶段：见 [references/tasks.md](references/tasks.md)

## 完成输出要求

每次阶段完成后统一报告：

- 当前阶段与产物路径
- 关键决策摘要（含 CR/GK 相关输入）
- 下一阶段建议（且明确“未执行下一阶段”）
