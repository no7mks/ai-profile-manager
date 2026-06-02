# Changelog (Unreleased → 0.8.0)

## Added

- `quick-release` skill 注册为双平台能力，并加入 `default` preset。
- Cursor `plan:quick-plan-conventions` rule：约定快速计划路径 `.cursor/specs/<slug>-plan.md`；实施由 IDE Build 发起，不写 plan 查找步骤。

## Changed

- gitflow skill（Cursor / Kiro）：各平台 SKILL 与 finish-flow 仅引用本机规则路径与 `<spec-dir>`。
- cursor-scope 补充 `.cursor/specs/` 下 Spec 子目录与 `*-plan.md` 的说明。
- `default` preset 新增 `rule:git:git-conventions`、`rule:plan:quick-plan-conventions`、`skill:quick-release`。
- doc-convergence：新增 Agent 实施期 CHANGELOG 路径禁令；Cursor 版 Spec 归档路径改为 `.cursor/specs/`。
