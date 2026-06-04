# Usage

apm 的日常使用说明。命令行为 SSOT 见 `docs/state/cli-commands.md`。

---

## 安装 CLI

```bash
composer global require no7mks/ai-profile-manager -W
apm --help
```

Windows（PowerShell + Scoop Composer）：见 `docs/manual/install-windows.md`（`$env:Path`、`COMPOSER_HOME`、`Get-Command apm`、三阶段首装）。

---

## 三阶段首装（新用户）

### 1. 全局能力（任意目录，通常只需一次）

```bash
apm global-setup
```

安装 `abilities.yaml` 中 Global Setup List 的 user-scope 项（如 `/apm` skill、`git-conventions` rule）。可在任意工作目录执行；不需要 `--scope`。

### 2. 项目脚手架（业务仓库根目录）

```bash
apm bootstrap
```

复制 `docs/README.md`、`issues/README.md`、`AGENTS.md` 并创建 `docs/state/`、`docs/manual/` 等空目录骨架；**不**安装 preset 或 conventional ability。

### 3. 项目上下文与能力（Agent 或 CLI）

在 Agent 对话中执行 `/apm init`（**必须先**完成 bootstrap）。Agent 将生成 `PROJECT.md` 与 state/manual 基线，并按仓库特征执行 `apm install <preset>` 或 typed install。

init 流程中 Agent 调用的 `apm add` / `apm install` 默认应带 `-t cursor -t kiro`，除非用户明确要求单平台。

---

## 从旧版迁移

| 旧用法 | 新用法 |
|--------|--------|
| `apm install`（无参） | 失败；按提示执行 `global-setup` → `bootstrap` → typed add 或 `/apm init` |
| `apm install default` | 失败；preset `default` 已删除，改用三阶段首装 + 显式 preset |
| `apm add <name>`（无类型） | 失败；使用 `apm install <preset>` 或 `apm skill:install <name>` 等 |
| `apm uninstall` | 可用别名 `apm remove`（install 族同理：`add`） |

重装 **project** ability：`apm cleanup` 后 `/apm init`；user scope 的 `global-setup` **不必**重跑。

---

## 安装 ability

```bash
# 按 preset 批量安装（先校验整单，失败则零写入）
apm install gitflow -t cursor

# user scope（仅 registry 允许 user 的项）
apm install gitflow --scope user -t cursor

# 安装单个 skill / rule / agent
apm skill:install graphify -t kiro
apm rule:install git:git-conventions -t cursor
apm agent:install code-reviewer -t kiro
```

Hook 通过 preset 或 typed install 安装。`--scope` 省略时默认为 `project`。

---

## 检查漂移

```bash
apm check gitflow -t cursor
apm skill:check graphify -t kiro
```

`check` 不新增 `--scope`；仍仅评估 project scope（与 `docs/state/deploy-scope.md` 一致）。

---

## 查看安装状态

```bash
apm show -t cursor
apm show --scope project -t kiro
apm show --type hook
```

每行一个 conventional ability；双侧安装时可能出现 `[warn] installed in both user and project`。

---

## 更新与清理

```bash
# 须使用 composer global 的 apm
apm update
apm update --force

# 仅卸载 project scope 已装 ability；不动 scaffold / user scope
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
