# Usage

apm 的日常使用说明。命令行为 SSOT 见 `docs/state/cli-commands.md`。

---

## 安装 CLI

```bash
composer global require no7mks/ai-profile-manager -W
apm --help
```

Windows（PowerShell + Scoop Composer）：见 `docs/manual/install-windows.md`（`$env:Path`、`COMPOSER_HOME`、`Get-Command apm`、两阶段首装）。

---

## 两阶段首装（新用户）

### 1. 项目脚手架 + 基础能力安装（业务仓库根目录）

```bash
apm bootstrap
```

执行项目骨架搭建（复制 `docs/README.md`、`issues/README.md`、`AGENTS.md` 并创建 `docs/state/`、`docs/manual/` 等空目录），然后读取 `abilities.yaml` 中 `bootstrap.includes` 列表，逐项安装对应 ability 到 project scope（如 `/apm` skill、`git-conventions` rule）。已安装的 ability 自动跳过，加 `--force` 可覆盖。

### 2. 项目上下文与能力（Agent 或 CLI）

在 Agent 对话中执行 `/apm init`（**必须先**完成 bootstrap）。Agent 将生成 `PROJECT.md` 与 state/manual 基线，并按仓库特征执行 `apm install <preset>` 或 typed install。

init 流程中 Agent 调用的 `apm add` / `apm install` 默认应带 `-t cursor -t kiro`，除非用户明确要求单平台。

---

## 从旧版迁移

| 旧用法 | 新用法 |
|--------|--------|
| `apm install`（无参） | 失败；按提示执行 `bootstrap` → `/apm init` |
| `apm install default` | 失败；preset `default` 已删除，改用两阶段首装 + 显式 preset |
| `apm add <name>`（无类型） | 失败；使用 `apm install <preset>` 或 `apm skill:install <name>` 等 |
| `apm uninstall` | 可用别名 `apm remove`（install 族同理：`add`） |
| `apm global-setup` | 已废弃；改用 `apm bootstrap`（scaffold + ability 安装合并） |
| `--scope user` / `--scope project` | 已移除；所有操作现在仅针对 project scope |

重装 **project** ability：`apm cleanup` 后 `/apm init`。

---

## 安装 ability

```bash
# 按 preset 批量安装（先校验整单，失败则零写入）
apm install gitflow -t cursor

# 安装单个 skill / rule / agent
apm skill:install graphify -t kiro
apm rule:install git:git-conventions -t cursor
apm agent:install code-reviewer -t kiro
```

Hook 通过 preset 或 typed install 安装。所有安装操作均针对 project scope。

---

## 检查漂移

```bash
apm check gitflow -t cursor
apm skill:check graphify -t kiro
```

`check` 仅评估 project scope（与 `docs/state/deploy-scope.md` 一致）。

---

## 查看安装状态

```bash
apm show -t cursor
apm show -t kiro
apm show --type hook
```

每行一个 conventional ability，格式为 `{type}:{name}  {status}   {targets}`。

---

## 更新与清理

```bash
# 须使用 composer global 的 apm
apm update
apm update --force

# 仅卸载 project scope 已装 ability；不动 scaffold
apm cleanup
```

---

## 卸载

```bash
apm skill:uninstall graphify -t cursor
apm skill:remove graphify -t cursor    # remove 为 uninstall 别名

apm preset:uninstall gitflow -t cursor --force
```

有本地修改时默认中止；加 `--force` 强制卸载。
