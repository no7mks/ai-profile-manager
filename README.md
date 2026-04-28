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
- `docs/state/` 下的项目状态文档（文件名由 AI 按项目上下文决定）
- `docs/manual/` 下的项目手册文档（文件名由 AI 按项目上下文决定）

`/apm init` 不预设 `manual/state` 的固定文件名，只要求在两个目录里形成可用的初始化内容。

## 文档边界

- `README.md`：最小入口与快速上手。
- `abilities/skills/apm/SKILL.md`：详细操作策略（面向 Agent 执行）。
- 命令参数、流程细节与实际行为，请以 `--help` 和 `SKILL.md` 为准。
