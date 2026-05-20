---
name: spec-planning
description: 当用户提到 spec、planning、goal、requirements、design、tasks 或要求推进/创建 spec 规划时激活。每次只执行一个阶段并产出对应文档。
---

# Spec Planning

统一 spec 规划工作流的单一 skill。能力完整覆盖四部曲，但 `SKILL.md` 仅保留入口与路由，详细规范见 references。

## 触发场景

- 用户提到 `spec`、`planning`、`goal`、`requirements`、`design`、`tasks`、`plan.md`
- 用户要求推进 spec 规划，但希望分阶段确认
- 用户提到需要中途等待 GK（Gatekeeper）或人工介入

## 使用原则

1. **激活即行动**：skill 被激活后，agent 必须立即、自主地执行阶段判定（读取当前分支名 → 推断 spec name → 扫描 `<spec-dir>/<name>/` 目录中已有文件 → 确定当前阶段）。**禁止在判定完成前向用户提问**（如"你想做什么""针对哪个 feature"）。唯一允许提问的情况：当前分支无法推断 spec name（如在 develop/main 上）且 `<spec-dir>` 下无唯一活跃目录时，才可询问用户。
2. **用户显式指定阶段时直接执行**：若用户输入包含阶段关键词（如 `req`/`requirements`/`design`/`tasks`/`goal`），agent 应将其视为目标阶段，完成判定后直接执行该阶段（若与判定结果不一致则先简短告知状态差异并请求确认）。
3. 一次只做一步：当前阶段完成后立即停止，等待用户/GK。
4. 产物路径分两种：
   - **Feature 路径**：`goal.md` → `requirements.md` → `design.md` → `tasks.md`
   - **Bugfix 路径**：`goal.md` → `bugfix.md` → `design.md` → `tasks.md`
5. 若用户提到 `plan.md`，视为 `tasks.md` 的历史命名并在输出中显式说明。
6. Bugfix 路径的触发条件见 `phase-detection.md`；`bugfix.md` 的格式与校验标准见 `phase-requirements.md` 的 Bugfix Analysis 小节。
7. **步骤报告**：执行 phase reference 中的「执行步骤」时，每开始一个步骤须输出 `▶ 步骤 N: <名称>`，完成后输出 `✓ 步骤 N 完成`。跳转步骤时须说明跳转原因。这确保用户能追踪执行进度。

## 流程入口

- 阶段判定：见 [references/phase-detection.md](references/phase-detection.md)
- Goal 阶段：见 [references/phase-goal.md](references/phase-goal.md)
- Requirements 阶段：见 [references/phase-requirements.md](references/phase-requirements.md)
- Design 阶段：见 [references/phase-design.md](references/phase-design.md)
- Tasks 阶段：见 [references/phase-tasks.md](references/phase-tasks.md)

## 完成输出要求

每次阶段完成后统一报告：

- 当前阶段与产物路径
- 关键决策摘要（含 CR/GK 相关输入）
- 下一阶段建议（且明确"未执行下一阶段"）
