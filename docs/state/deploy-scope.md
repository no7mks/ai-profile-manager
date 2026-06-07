# Deploy Scope

Deploy scope 路径根解析与 CLI 默认行为（SSOT）。

---

## Scope 取值

枚举 `DeployScope` 仅含一个值：`Project`。

| Scope | 根目录 | 解析方式 |
|-------|--------|----------|
| `Project` | **Workspace Root** | `getcwd()`；可通过构造注入 `rootPath` 覆盖 |

---

## DeployRootResolver

`DeployRootResolver` 提供路径解析能力：

### `resolve(): string`

返回 project root——即 `getcwd()`，或通过构造函数注入的 `rootPath`。

### `absoluteTargetPath(string $relativeTarget): string`

在 project root 下拼接 registry `targets` 相对路径：

- trim 首尾 `/` `\` 后，以 `DIRECTORY_SEPARATOR` 拼接
- 空相对路径返回根目录

---

## InvalidScopeException

`InvalidScopeException` 继承 `InvalidArgumentException`，提供以下工厂方法：

| 工厂方法 | 触发场景 | 消息要点 |
|----------|----------|----------|
| `scopeOptionDeprecated()` | CLI 传入 `--scope` 选项 | 提示 `--scope` 已弃用，仅支持 project scope |
| `globalSetupDeprecated()` | 调用 `global-setup` 命令 | 提示 global-setup 已弃用 |
| `legacyScopesField()` | `abilities.yaml` 中存在 `scopes` 字段 | 提示 `scopes` 字段已弃用 |
| `legacyGlobalSetupKey()` | `abilities.yaml` 中存在 `global_setup` key | 提示 `global_setup` 已弃用 |

---

## CheckService

`checkTyped()` 直接使用 project root（`DeployRootResolver::resolve()`）作为已安装侧根目录，与 baseline `install_path` 对比 skill/rule/agent（`AbilityDiffService`）与 hook（`HookChecker`）。

- `check` / `skill:check` 等 CLI 始终查 project scope
- hook 目标路径：`{projectRoot}/.kiro/hooks/<name>.kiro.hook`（Kiro）；`{projectRoot}/.cursor/hooks/<name>/` 与 `hooks.json`（Cursor）
