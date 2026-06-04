# Deploy Scope

Deploy scope 路径根解析与 CLI 默认行为（SSOT）；registry `scopes`、Global Setup、Project-Only 见 `abilities-model.md`。

---

## Scope 取值

枚举 `DeployScope`：`project`、`user`。CLI 通过 `DeployRootResolver` 统一解析。

| Scope | 根目录 | 解析方式 |
|-------|--------|----------|
| `project`（**Project Scope**） | **Workspace Root** | `getcwd()`；`realpath` 可用时规范化 |
| `user`（**User Scope**） | **User Root** | 环境变量 `HOME`；未设置时运行时错误 |

---

## 默认与选项解析

- 省略 `--scope` 或 `parseScopeOption(null)` → `project`。
- 合法字符串：`project`、`user`。
- 其它值（含空字符串）→ `InvalidScopeException`；空字符串消息含 `(empty)`。

---

## 相对路径拼接

`absoluteTargetPath(scope, relativeTarget)`：在对应根下拼接 registry `targets` 相对路径；trim 首尾 `/` `\` 后拼接；空相对路径返回根目录；分隔符为 `DIRECTORY_SEPARATOR`。

---

## InvalidScopeException

`InvalidScopeException` 继承 `InvalidArgumentException`，工厂方法统一在消息末尾附带 `Valid scopes: project, user.`。

| 工厂方法 | 触发场景 | 消息要点 |
|----------|----------|----------|
| `unknownScope(?string $value)` | 非法 `--scope` 字符串 | `Invalid deploy scope: <value>`；`null` / 空字符串显示 `(empty)` |
| `projectOnlyInUserScope(string $abilityRef)` | **User Scope** 下请求 **Project-Only Ability** | `Project-only ability "<ref>" cannot be deployed to user scope.` |

`DeployRootResolver::parseScopeOption()` 对非法 scope 使用 `unknownScope`。安装路径上的 project-only 校验见下文 `ScopeGuard`。

---

## ScopeGuard（批量 scope 校验）

`ScopeGuard` 依赖 `AbilityRegistry`，在**任何安装写盘之前**对 preset / batch 的 `type:path` 列表做 scope 约束校验。

### `assertBatchAllowed(DeployScope $scope, array $items)`

- **`project` scope**：直接通过（不逐项校验 project-only）。
- **`user` scope**：遍历 `items` 中每个 `type:path` 字符串：
  - 无 `:`、空 type/path、未知 ability type → **忽略**（不在此层校验格式或存在性）。
  - `gitignore`、`prompt` → 一律视为 project-only，拒绝 user scope。
  - `skill` / `rule` / `agent` / `hook`：若 registry 有对应 entry 且 `scopes === ['project']`，或 path 落在 `projectOnlyPaths()` 中 → 拒绝。
  - 首个违规项抛出 `InvalidScopeException::projectOnlyInUserScope($path)`（消息中的 ability 名为 path 部分）。

### 批量原子性

整批在 Guard 阶段**先全部校验、后写盘**。任一项在 user scope 下非法 → **整批失败**，调用方不得写入任何目标文件（零部分写入）。混合合法与非法项时，在**第一个**非法项处抛错，此前亦无磁盘副作用。

CLI 安装族在写盘前调用 `assertBatchAllowed`：`install`（别名 `add`）、`skill:install` / `rule:install` / `agent:install`（及 `skill:add` 等）、`preset:uninstall`（别名 `preset:remove`）通过 `HandlesDeployScopeOption` 解析 `--scope`（默认 `project`）。`install` 另在 Guard 前执行 `PresetRegistry::validatePresetInstall`（缺失 ability bundle → 失败，零写入）。

---

## CheckService

- **`checkTypedForScope($items, $targets, $scope)`**：`DeployRootResolver::resolve($scope)` 为已安装侧根（`project` = workspace，`user` = `$HOME`），与 baseline `install_path` 对比 skill/rule/agent（`AbilityDiffService`）与 hook（`HookChecker`）。
- **`checkTyped()`**：等同 `checkTypedForScope(..., project)`；`check` / `skill:check` 等 CLI 仍只查 project scope，不新增 `--scope`。
- **hook**：Kiro 目标 `{root}/.kiro/hooks/<name>.kiro.hook`；Cursor 为 `{root}/.cursor/hooks/<name>/` 与 `hooks.json`。user scope 仅以 `$HOME` 为根，与 project 安装位置分离。
