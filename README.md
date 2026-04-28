# AI Profile Manager (`aipm`)

`aipm` 是一个用于管理 AI profile 资源（`skill`、`rule`、`agent`）的 PHP CLI。

## Quick Start

```bash
composer install
php bin/apm --help
```

或全局安装：

```bash
composer global require no7mks/ai-profile-manager
apm --help
```

## 常用命令

```bash
# 无参数 install：初始化当前仓库（scaffold + apm skill）
apm install

# 预设安装
apm install gitflow -t cursor
```

运行 `apm install`（无参数）后，使用 Agent skill 命令 `/apm init` 初始化 SSOT 基线，生成或补齐：

- `PROJECT.md`
- `docs/state/README.md`
- `docs/manual/README.md`

## 文档边界

- `README.md`：最小入口与快速上手。
- `abilities/skills/apm/SKILL.md`：详细操作策略（面向 Agent 执行）。
- 命令参数、流程细节与实际行为，请以 `--help` 和 `SKILL.md` 为准。
