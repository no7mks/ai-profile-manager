---
name: apm
description: "当用户说 /apm 或要求执行 apm 命令（init、install、check、uninstall、show）时激活。"
---

# apm（AI Profile Manager）

本 Skill 是 **Agent 执行手册**。用户界面统一使用 `/apm <command>`，必要时再由 Agent 在后台调用 `apm` binary。

## 命令总览

| 用户命令 | 意图 | 调用 binary | 后台动作 |
|---|---|---|---|
| `/apm init` | 生成项目基础上下文（SSOT ready） | 否 | 生成/更新 `PROJECT.md`，并按项目上下文在 `docs/state`、`docs/manual` 创建或补齐内容文件 |
| `/apm install <preset>` | 按 preset 批量安装能力 | 是 | `apm install <preset> -t <target>` |
| `/apm install skill <name>` | 安装单个 skill | 是 | `apm skill:install <name> -t <target>` |
| `/apm install rule <name>` | 安装单个 rule/steering | 是 | `apm rule:install <name> -t <target>` |
| `/apm install agent <name...>` | 安装单个或多个 agent | 是 | `apm agent:install <name...> -t <target>` |
| `/apm check <preset>` | 检查 preset 漂移 | 是 | `apm check <preset> -t <target>` |
| `/apm check <type> <name>` | 检查 typed 能力漂移 | 是 | `apm {skill\|rule\|agent}:check <name> -t <target>` |

## 何时使用本 Skill

- 初始化项目脚手架与 scope
- 在 `.cursor` / `.kiro` 安装或更新能力
- 检查本地能力漂移状态

## Agent 执行原则

1. **先确认当前目录**：默认应在用户目标业务仓库根目录执行。
2. **不臆造名称**：ability / preset 名必须来自用户输入或仓库真实存在项。
3. **目标明确**：涉及写入时显式带 `-t`（`cursor` 或 `kiro`），避免写错平台目录。
4. **先小后大**：不确定时先做 typed 命令（如 `skill:check <name>`），再做 preset 或全量操作。
5. **保守覆盖**：只有用户明确要求时才使用 `--force`。
6. **Prompt ability**：安装 preset 时若包含 `prompt` 类型 ability，apm binary 会输出其 `message` 内容，agent 按指引执行一次性操作（如修改 `PROJECT.md`）。

## 常见失败与处理

- `apm: command not found`：提示用户全局安装并确认 `PATH`，再重试。
- 命令执行但写入位置异常：优先检查 cwd 与 `-t`。

## `/apm init` 交付物

当用户要求"初始化项目上下文"时，`/apm init` 需要一次性完成以下三件事（SSOT ready）：

| 交付物 | 要求 |
|---|---|
| `PROJECT.md` | 按 [模板](references/project.md) 生成或补齐，未知内容写 `TODO`，不臆测 |
| `docs/state/` 下至少一个文件 | 建立当前状态基线（当前版本、活跃分支、最近变更摘要、待确认风险） |
| `docs/manual/` 下至少一个文件 | 建立人工操作手册基线（常用命令、发布/回滚、排障入口） |

约束：

- `docs/state/` 与 `docs/manual/` 的文件名、拆分粒度由 AI 根据项目上下文决定，不预设固定名称。
- 两个目录都必须有可用内容（各至少创建或更新 1 个业务文档）。

详细执行流程见 [init-workflow.md](references/init-workflow.md)。

## 与 README 的边界

- `README.md`：产品事实来源（source of truth）。格式见 [readme.md](references/readme.md)。
- `SKILL.md`：对话执行策略（how to operate safely as an agent）。
