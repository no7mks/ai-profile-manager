# Spec Goal: Deprecate Scope

## 来源

- `docs/proposals/PRP-003-deprecate-scope.md`（status: in-progress）

## 背景摘要

apm 当前支持 `project` 和 `user` 两种 Deploy Scope。user scope 安装到用户主目录，与多项目场景不兼容，实际几乎未被采用。`global-setup` 命令安装到 `$HOME` 导致主目录污染。

PRP-003 提出废弃 `--scope` 参数、user scope 概念和 `global-setup` 命令，逻辑上只保留 project scope，简化代码路径和用户心智模型。

## 目标

1. **废弃 `--scope` 参数**：以下命令传入 `--scope`（任何值）即输出 deprecated 错误消息并 exit FAILURE：`install`、`show`、`skill:install`、`rule:install`、`agent:install`、`skill:uninstall`、`rule:uninstall`、`agent:uninstall`、`preset:uninstall`。
2. **废弃 `global-setup` 命令**：执行时输出 deprecated 错误消息并 exit FAILURE。
3. **扩展 `bootstrap` 命令**：统一执行 scaffold + `bootstrap.includes` 列表中的 ability 安装到 project scope。幂等：已安装且无 `--force` 时跳过，有 `--force` 时覆盖。失败策略：逐项执行，失败项记 `[fail]`，最终 exit FAILURE（scaffold 不回滚）。
4. **`abilities.yaml` 变更**：删除所有 entry 的 `scopes` 字段；`global-setup` section 改名为 `bootstrap`；遗留旧字段/key 时 fail-fast 抛异常。
5. **`show` 命令简化**：移除 scope 合并逻辑（user+project 合并展示、`[warn] installed in both`），仅展示 project scope 安装状态。
6. **`update` 命令简化**：仅遍历 project scope 已安装 ability，不再遍历 user scope。
7. **`cleanup` 文案清理**：移除提示文案中 "user-scope global-setup is unchanged" 措辞。
8. **代码清理**：按 PRP-003 §4 清理 `ScopeGuard`、`UserHomeResolver`、`DefaultGlobalSetupService`、`ShowStatusPresenter` 合并逻辑等。`DeployScope` 枚举保留仅含 `Project` 单值。
9. **文档同步**：更新所有受影响的 SSOT 文档（`deploy-scope.md`、`cli-commands.md`、`abilities-model.md`、`install-behavior.md`、`usage.md`、init-workflow 参考文件）。

## 不做的事情（Non-Goals）

- 不引入新 scope 概念（如 workspace scope）。
- 不修改 `apm init` Agent 端逻辑（仅更新参考文档）。
- 不做向后兼容 shim（`--scope project` 不静默通过，一律 hard fail）。
- 不自动清理用户主目录下已安装的 user scope 文件。

## Clarification 记录

| # | 问题 | 回答 |
|---|------|------|
| 1 | `DeployScope` 枚举"可删可留单值"，倾向哪个方向？ | 保留 `DeployScope` 仅含 `Project` 单值 |
| 2 | `bootstrap` ability 安装失败时，继续尝试剩余还是中止？ | 逐项执行，失败项记 `[fail]`，最终 exit FAILURE（与现有 install 行为一致） |
| 3 | `abilities.yaml` 遗留 `scopes`/`global-setup` key 的验证粒度？ | 首个违规即抛异常（fail-fast） |
| 4 | uninstall 类命令的 `--scope` 参数是否在本 spec 范围内？ | 是，在本 spec 内作为最后清理步骤处理 |
| 5 | 文档同步是否在本 spec 范围内？ | 是，一并完成 |

## 约束与决策

| 项 | 决策 |
|----|------|
| `DeployScope` 枚举 | 保留仅含 `Project` 单值（类型安全） |
| bootstrap 失败策略 | scaffold 先完成；ability install 逐项执行，失败项记 `[fail]`，最终 exit FAILURE，scaffold 不回滚 |
| abilities.yaml 验证 | fail-fast：首个违规字段（`scopes` 或 `global-setup` key）即抛异常 |
| `--scope` 报错策略 | 所有命令（install + uninstall）统一 hard fail，无静默通过 |
| `global-setup` section 改名 | 改名为 `bootstrap`，与 CLI 命令对齐；旧 key 报 validationError |
| user scope 已安装文件清理 | 不做自动清理，用户自行删除 |
| 文档同步 | 属于本 spec 范围，与代码变更同步完成 |
