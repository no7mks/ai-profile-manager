# AI Profile Manager (`apm`)

`apm` 是一个用于管理 AI profile 资源（`skill`、`rule`、`agent`、`hook`）的 PHP CLI。

## Quick Start

### Step 1 安装 apm CLI

- 全局安装（推荐）：

```bash
composer global require no7mks/ai-profile-manager -W
apm --help # 如显示帮助则表示安装成功了
```

- Windows（PowerShell + Scoop）：见 [`docs/manual/install-windows.md`](docs/manual/install-windows.md)。

> `-W` 允许 Composer 连带升级依赖包，避免因 lock file 锁定旧版 symfony 组件而无法安装最新版本。

- 项目内安装（开发/调试）：

```bash
composer install
php bin/apm --help
```

### Step 2 全局能力（一次性）

在**任意目录**执行，将 Global Setup List 写入 user scope（`~/.cursor` / `~/.kiro`）：

```bash
apm global-setup              # 默认 cursor + kiro
apm global-setup -t cursor    # 仅 Cursor
```

完成后在 Agent 对话中即可使用 `/apm` skill（无需在每个仓库重复此步）。

### Step 3 业务仓库脚手架

进入**目标项目根目录**：

```bash
apm bootstrap                 # 复制 docs/issues/AGENTS.md 骨架，不安装 preset
```

然后在 Agent 对话中执行 `/apm init`，由 Agent 生成 `PROJECT.md` 与 `docs/state`、`docs/manual` 基线，并按仓库上下文安装 project ability（见 `.cursor/skills/apm`）。

### Step 4 按需安装 preset / 单项能力

```bash
apm install gitflow -t cursor
apm install spec-core -t kiro
apm skill:install graphify -t cursor
apm show -t cursor
```

可用 preset 与 ability 列表见 `apm show`。`install`/`add` **必须**带 preset 名或 typed 前缀；无参 `apm install` 与 preset `default` 已废弃（见下方迁移说明）。

---

## 从旧版迁移

若你曾使用无参 `apm install` 或 preset `default`，请改为三阶段首装：

1. `apm global-setup` — user scope 全局能力（`/apm` skill、通用 rule 等）
2. 进入业务仓库 `apm bootstrap` — 仅 scaffold
3. `/apm init` 或显式 `apm install <preset>` / `apm skill:install …` — project scope 能力

`apm install default` 将失败并打印上述指引。重装 project 能力时可 `/apm cleanup` 后 `/apm init`，**无需**重复 `global-setup`。

---

## 常用命令

| 场景 | 命令 |
|------|------|
| 查看安装状态 | `apm show [-t cursor\|kiro] [--scope project\|user]` |
| 检查漂移 | `apm check <preset> -t cursor` |
| 全局更新 baseline | `composer global` 安装的 `apm update`（可加 `--force`） |
| 清理 project 安装 | `apm cleanup` |

详细行为见 `docs/manual/usage.md` 与 `docs/state/cli-commands.md`。
