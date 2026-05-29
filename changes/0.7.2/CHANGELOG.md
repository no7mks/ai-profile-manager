# Unreleased Changelog

---

## Added

- 新增 `quick-plan` skill：交互式快速生成 plan.md，等待用户确认后再执行
- 新增 `build-plan` skill：按已确认的 plan.md 逐步执行实施
- 将 quick-plan 和 build-plan 注册到 `abilities.yaml` 并加入 default preset

## Changed

- quick-plan / build-plan 执行协议增加步骤标识输出约束，防止跳步或合并步骤
