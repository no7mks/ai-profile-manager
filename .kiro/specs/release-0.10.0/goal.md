# Spec Goal: Release 0.10.0

## 来源

- `changes/unreleased/proposals/PRP-003-deprecate-scope.md`（status: implemented）
- `docs/notes/cursor-quick-plan-to-skills.md`

## 背景摘要

0.10.0 包含两项主要变更：

1. **PRP-003 Deprecate Scope**（已实现、已合并 develop）：废弃 `--scope` 参数、user scope 概念和 `global-setup` 命令；`bootstrap` 统一 scaffold + ability 安装；三阶段首装缩减为两阶段。
2. **Cursor Quick-Plan Rule → 两个 Skill**（待实现）：将 `.cursor/rules/plan/quick-plan-conventions.mdc` 拆分为 `quick-plan`（Plan mode）和 `build-plan`（Agent mode）两个独立 Cursor Skill，删除旧 rule，更新 `abilities.yaml`。

## 目标

- 完成 note `cursor-quick-plan-to-skills` 中描述的 Skill 拆分（仅 Cursor 侧）
- 更新 `abilities.yaml`：删除旧 rule 条目，新增两个 Skill 条目，修改 `bootstrap.includes`
- 执行 release 收口：版本号同步、CHANGELOG 收敛、文档更新、tag

## 不做的事情（Non-Goals）

- 不涉及 Kiro 侧 `quick-plan` / `build-plan` Skill 内容变更（已存在且独立维护）
- 不做旧 rule 的自动迁移逻辑（`apm update` 不自动卸载旧 rule 或安装新 Skill）
- 不引入新 CLI 命令或新 scope 概念

## Goal Clarification

| # | 问题 | 回答 |
|---|------|------|
| 1 | 0.10.0 发布范围：仅 PRP-003 还是也包含 note 中的 Skill 拆分？ | 两者都包含 |
| 2 | Skill 拆分范围：仅 Cursor 侧还是 Cursor + Kiro 双侧？ | 仅 Cursor 侧 |
| 3 | 新 Skill 是否加入 `bootstrap.includes`？ | 是，bootstrap 自动安装 |
| 4 | 旧 rule 删除后已安装项目如何处理？ | 不做自动迁移，用户手动清理 |

## 约束与决策

- `quick-plan` Skill 声明需在 Plan mode 下运行；`build-plan` 声明需在 Agent mode 下运行
- 两个 Skill 共享 plan.md 路径约定：`.cursor/specs/<slug>-plan.md`
- 旧 rule 文件（`plan/quick-plan-conventions.mdc`）从 abilities 源码包中删除
- PRP-003 已实现，release 分支仅做收口工作（版本号、changelog、tag），不含新代码变更
- Fixed issues（ISS-01592、ISS-31532、ISS-32020）均已在更早版本发布，不属于本次 release 范围
