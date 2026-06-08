# Changelog (0.10.0)

## Breaking

- `--scope` 参数传入任何值时报 deprecated 错误并退出，不再支持 user scope
- `apm global-setup` 命令废弃，执行即报错退出
- `abilities.yaml` 中含 `scopes` 字段或 `global-setup` key 会导致解析报错
- `apm show` 不再展示 user scope 安装状态
- `apm update` 不再检查/更新 user scope 已安装 ability

## Added

- `quick-plan` Cursor Skill：在 Plan mode 下交互式生成 plan.md
- `build-plan` Cursor Skill：在 Agent mode 下按已确认 plan.md 执行实施

## Removed

- 移除 `UserHomeResolver`、`ScopeGuard`、`DefaultGlobalSetupService` / `GlobalSetupService`
- 移除 `AbilityEntry::$scopes` 属性
- 移除 `DeployScope::User` case，仅保留 `Project`
- 移除 `CheckService::checkTypedForScope()` 方法
- 删除 `quick-plan-conventions` rule（职责由两个 Skill 替代）

## Changed

- `apm bootstrap` 扩展为 scaffold + `bootstrap.includes` ability 安装（原 `global-setup` 职责合并）
- `abilities.yaml` 中 `global-setup` section 改名为 `bootstrap`
- `DeployRootResolver` 简化为仅返回 project root
- `InstallationProbe` / `Installer` 移除 `$scope` 参数，硬编码 project scope
- `AbilityRegistry::globalSetupIncludes()` 改名为 `bootstrapIncludes()`
- 首装流程从三阶段缩减为两阶段：`apm bootstrap` + `/apm init`
- `abilities.yaml` skills section：`quick-plan` 和 `build-plan` 新增 cursor target
- `bootstrap.includes` 移除 `rule:plan:quick-plan-conventions`
