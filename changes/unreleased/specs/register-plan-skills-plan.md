# 注册 plan 相关 skill 到 abilities 管理

## Goal

将 quick-plan 和 build-plan 两个 Kiro skill 注册到 abilities.yaml 并加入 default preset。

## Scope

- `abilities.yaml`：注册 skill + 加入 default preset

## Assumptions

- 两个 skill 只需要 kiro target（cursor 已自带）。
- 加入 `presets.default.includes` 即为默认安装。
- quick-plan SKILL.md 的 TDD 编排指引已补充完毕（不在本计划范围内）。

## Files

- `abilities.yaml`

## Plan

- [x] RED：运行 `apm check`，确认当前缺少 quick-plan 和 build-plan 的注册（预期报错或缺失提示）
- [x] GREEN：在 `abilities.yaml` 的 `skills` 区块末尾添加 quick-plan 和 build-plan 条目（只含 kiro target），在 `presets.default.includes` 中添加 `skill:quick-plan` 和 `skill:build-plan`
- [x] 运行 `apm check`，确认通过
- [x] docs/state/ 文档同步
- [x] docs/manual/ 文档同步
- [x] code-review
- [x] final checkpoint（整体验证 + commit）

## Validation

- `abilities.yaml` 语法正确（YAML lint 通过）
- `skills` 区块包含 quick-plan 和 build-plan
- `presets.default.includes` 包含对应引用

## Risks

- 无明显风险，变更范围小。
