# AI Profile Manager (`aipm`)

`aipm` 是一个用于管理 AI profile 资源（`skill`、`rule`、`agent`）的 PHP CLI。

## Quick Start

```bash
composer install
php bin/aipm --help
```

或全局安装：

```bash
composer global require no7mks/ai-profile-manager
aipm --help
```

## 文档边界

- `README.md`：最小入口与快速上手。
- `abilities/skills/aipm/SKILL.md`：详细操作策略（面向 Agent 执行）。
- 命令参数、流程细节与实际行为，请以 `--help` 和 `SKILL.md` 为准。
