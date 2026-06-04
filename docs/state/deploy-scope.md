# Deploy Scope

Deploy scope 路径根解析与 CLI 默认行为（SSOT）；registry `scopes`、Global Setup、Project-Only 见 `abilities-model.md` 后续章节。

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
