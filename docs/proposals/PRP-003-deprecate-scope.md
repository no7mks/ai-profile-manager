# PRP: Deprecate Scope

**Status**: draft

废弃 `--scope` 参数、`user` scope 概念和 `global-setup` 命令。逻辑上只保留 project scope。

本 PRP supersedes PRP-002 中 user/project 双 scope 模型与 `global-setup` 命令的设计。

---

## 动机

- user scope 与 project scope 的 ability 冲突（双侧安装 warn、project-only 限制）增加了维护复杂度，收益有限。
- `global-setup` 安装到 `$HOME` 导致用户主目录污染，且与多项目场景不兼容。
- 实际使用中 user scope 几乎未被采用；移除后可大幅简化代码路径和用户心智模型。

---

## 1. CLI 行为变更

### `--scope` 参数

所有支持 `--scope` 的命令（`install`、`show`、`skill:install`、`rule:install`、`agent:install`、`skill:uninstall`、`rule:uninstall`、`agent:uninstall`、`preset:uninstall`）：

- 传入 `--scope`（任何值）→ 输出 deprecated 错误消息并 exit FAILURE。
- 不传 `--scope` → 行为不变（硬编码 project）。

错误消息示例：

```
[ERROR] The --scope option has been removed. All operations now target project scope only.
```

### `apm global-setup`

- 执行时直接输出 deprecated 错误消息并 exit FAILURE。
- 不执行任何安装操作。

错误消息示例：

```
[ERROR] The global-setup command has been removed.
        Use "apm bootstrap" in your project directory instead.
```

### `apm show`

- 移除 scope 合并逻辑（user+project 合并展示、`[warn] installed in both` 等）。
- 仅展示 project scope 安装状态。
- `--scope` 参数同上，传入即报错。

### `apm update`

- 仅遍历 project scope 已安装 ability。
- 不再遍历 user scope。

### `apm cleanup`

- 行为不变（本就仅卸载 project scope）。
- 移除提示文案中的 "user-scope global-setup is unchanged" 措辞。

---

## 2. `abilities.yaml` 变更

### `scopes` 字段

- 所有 entry（rules / agents / skills / hooks）的 `scopes` 字段**删除**。
- 解析时若发现 entry 仍含 `scopes` key → 收入 `validationErrors`，报错终止。
- 逻辑上所有 entry 等同 `['project']`。

### `global-setup` section → 改名为 `bootstrap`

- section key 从 `global-setup` **改名为 `bootstrap`**，与 CLI 命令名对齐。
- `includes` 列表保留，含义变为：`apm bootstrap` 执行时安装到 project scope 的 ability 清单。
- 解析时若仍存在 `global-setup` key → 收入 `validationErrors`，报错终止（与 `scopes` 字段同理）。
- `AbilityRegistry::globalSetupIncludes()` 方法改名为 `bootstrapIncludes()`。

---

## 3. `bootstrap` 命令扩展

现有 `bootstrap` 仅执行 scaffold（`ProjectInitializer`：复制 `docs/README.md`、`issues/README.md`、`AGENTS.md`，创建空目录骨架）。

扩展后 `bootstrap` 统一执行：

1. scaffold（同现有行为）。
2. 读取 `abilities.yaml` 的 `bootstrap.includes` 列表。
3. 对列表中的 ability 执行 project scope 安装（等同现有 `Installer::installTyped`）。
4. 幂等：已安装且无 `--force` 时跳过；有 `--force` 时覆盖。
5. 与 `/apm init` 后续安装的 preset 可能存在重叠（如 `skill:apm`），依赖 install 层的幂等性（已存在则覆盖/跳过）。

**原子性**：scaffold 与 ability install 分两阶段执行。scaffold 先写入；若后续 ability install 部分失败，scaffold 保留（不回滚），失败项输出 `[fail]` 并 exit FAILURE。这与现有 install 行为一致（逐项执行、记录失败、不回滚已成功项）。

**签名不变**：`bootstrap [-t|--target TARGET...] [-f|--force]`。

---

## 4. 代码清理

| 组件 | 操作 |
|------|------|
| `DeployScope` 枚举 | 删除（或仅保留 `Project` 单值用于类型安全，视实现便利决定） |
| `ScopeGuard` | 删除（无 user scope 即无需 project-only 校验） |
| `InvalidScopeException` | 精简：仅保留 deprecated 消息工厂；删除 `projectOnlyInUserScope` |
| `HandlesDeployScopeOption` | 改为仅检测 `--scope` 并报 deprecated 错误 |
| `DeployRootResolver` | 简化为仅返回 project root（`getcwd()`）；删除 user root、`parseScopeOption` |
| `UserHomeResolver` | 删除 |
| `InstallationProbe` | 移除 `$scope` 参数（硬编码 project） |
| `Installer` | 移除 `$scope` 参数（硬编码 project） |
| `CheckService` | 删除 `checkTypedForScope`；`checkTyped` 直接使用 project root |
| `AbilityUpdateService` | 移除 user scope 遍历 |
| `DefaultGlobalSetupService` / `GlobalSetupService` | 删除 |
| `ShowStatusPresenter` | 移除 scope 合并逻辑 |
| `AbilityEntry::$scopes` | 删除属性 |
| `AbilityRegistry` | 解析时对 `scopes` key 和 `global-setup` key 报 validationError；`globalSetupIncludes()` 改名为 `bootstrapIncludes()`，读取 `bootstrap` section |

---

## 5. `AbilityEntry` 变更

删除 `$scopes` 属性。`projectOnlyPaths()` 方法及 ScopeGuard 中依赖 `scopes` 的判断一并移除。

---

## 6. 三阶段首装变更

| 旧流程 | 新流程 |
|--------|--------|
| 1. `apm global-setup`（user scope） | 删除 |
| 2. `apm bootstrap`（scaffold only） | `apm bootstrap`（scaffold + global-setup includes 安装到 project） |
| 3. `/apm init`（Agent 调用 `apm add`） | `/apm init`（不变） |

新流程为两阶段：

1. `apm bootstrap`：scaffold + `bootstrap.includes` 列表中的 ability 安装到 project scope。
2. `/apm init`：Agent 按项目上下文决定 `apm add` 什么。

---

## Non-Goals

- 不引入新的 scope 概念（如 workspace scope）。
- 不修改 `apm init` Agent 端逻辑（仅更新其参考文档中的 `global-setup` 引用）。
- 不做向后兼容 shim（如 `--scope project` 静默通过）——一律 hard fail。

---

## Breaking Changes

- `--scope` 参数传入任何值 → 报错（原先默认 `project`、可传 `user`）。
- `apm global-setup` → 报错（原先安装到 user scope）。
- `abilities.yaml` 中含 `scopes` 字段 → 解析报错。
- `abilities.yaml` 中含 `global-setup` key → 解析报错（须改为 `bootstrap`）。
- `apm show` 不再展示 user scope 安装状态。
- `apm update` 不再检查/更新 user scope 已安装 ability。

---

## 文档同步

| 文件 | 待改内容 |
|------|----------|
| `docs/state/deploy-scope.md` | 重写为仅 project scope；移除 user root、ScopeGuard 等章节 |
| `docs/state/cli-commands.md` | 移除所有 `--scope` 签名；`global-setup` 标记 deprecated；更新 `bootstrap` 签名 |
| `docs/state/abilities-model.md` | 移除 `scopes` 字段文档；移除 Project-Only 概念 |
| `docs/state/install-behavior.md` | 移除 Deploy Scope 通用行为章节中 user scope 相关内容 |
| `docs/manual/usage.md` | 两阶段首装；移除 `global-setup`、`--scope` 引用 |
| `.cursor/skills/apm/references/init-workflow.md` | 移除 `global-setup` 引用；更新首装流程描述 |
| `.kiro/skills/apm/references/init-workflow.md` | 同上 |

---

## 验收

1. `bin/apm global-setup` → deprecated 报错，exit FAILURE。
2. `bin/apm install gitflow --scope user` → deprecated 报错，exit FAILURE。
3. `bin/apm show --scope project` → deprecated 报错，exit FAILURE。
4. `bin/apm bootstrap -t cursor -t kiro` → scaffold + global-setup includes 安装成功。
5. `abilities.yaml` 中无 `scopes` 字段。
6. `./vendor/bin/phpunit` 全部通过。
7. `./vendor/bin/phpstan analyse` 零错误。

---

## 已关闭决策

| 项 | 决策 |
|----|------|
| `--scope project` 是否静默通过 | 否，一律 hard fail |
| `DeployScope` 枚举是否保留 | 视实现便利，可删可留单值 |
| `global-setup` section 是否改名 | 是，改名为 `bootstrap`，与 CLI 命令对齐；旧 key 报 validationError |
| user scope 已安装文件是否清理 | 不做自动清理；用户自行删除 `$HOME/.cursor/` 等 |
