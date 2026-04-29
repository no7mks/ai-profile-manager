---
name: apm
description: "通过 `/apm <command>` 统一入口执行初始化、安装、检查、捕获与回流；按需调用 apm binary。"
---

# apm（AI Profile Manager）

本 Skill 是 **Agent 执行手册**。用户界面统一使用 `/apm <command>`，必要时再由 Agent 在后台调用 `apm` binary。

## 命令总览（用户先看到）

| 用户命令 | 意图 | 后台是否调用 `apm` binary | 后台动作 |
|---|---|---|---|
| `/apm init` | 生成项目基础上下文（SSOT ready） | 否 | 生成/更新 `PROJECT.md`，并按项目上下文在 `docs/state`、`docs/manual` 创建或补齐内容文件 |
| `/apm install <preset>` | 按 preset 批量安装能力 | 是 | `apm install <preset> -t <target>` |
| `/apm skill add <name>` | 安装单个 skill | 是 | `apm skill:install <name> -t <target>` |
| `/apm rule add <name>` | 安装单个 rule/steering | 是 | `apm rule:install <name> -t <target>` |
| `/apm agent add <name...>` | 安装单个或多个 agent | 是 | `apm agent:install <name...> -t <target>` |
| `/apm check <preset>` | 检查 preset 漂移 | 是 | `apm check <preset> -t <target>` |
| `/apm check <type> <name>` | 检查 typed 能力漂移 | 是 | `apm {skill\|rule\|agent}:check <name> -t <target>` |
| `/apm capture <preset>` | 生成 preset 变更 | 是 | `apm capture <preset> -t <target> [--yes]` |
| `/apm capture <type> <name>` | 生成 typed 变更 | 是 | `apm {skill\|rule\|agent}:capture <name> -t <target>` |

## 何时使用本 Skill

当用户需求涉及以下内容时优先使用：

- 初始化项目脚手架与 scope；
- 在 `.cursor` / `.kiro` 安装或更新能力；
- 检查或捕获本地能力改动；
- 将 capture changes 回流到能力仓库。

## Agent 执行原则

1. 先确认当前目录：默认应在用户目标业务仓库根目录执行。
2. 不臆造名称：ability / preset 名必须来自用户输入或仓库真实存在项。
3. 目标明确：涉及写入时显式带 `-t`（`cursor` 或 `kiro`），避免写错平台目录。
4. 先小后大：不确定时先做 typed 命令（如 `skill:check <name>`），再做 preset 或全量操作。
5. 保守覆盖：只有用户明确要求时才使用 `--force`。
6. 需要非交互执行 capture 时可使用 `--yes`；正常对话保持可确认流程。

## 常见失败与处理

- `apm: command not found`：提示用户全局安装并确认 `PATH`，再重试。
- 命令执行但写入位置异常：优先检查 cwd 与 `-t`。
- capture 未产出文件：可能是无差异，先看命令摘要与退出码再判断。
- ingest 未生效：核对 `~/.apm/changes`（或 `--changes-dir`）是否存在可处理变更。

## `/apm init`（skill 命令）交付物

当用户要求“初始化项目上下文”时，`/apm init` 需要一次性完成以下三件事（SSOT ready）：

| 交付物 | 要求 |
|---|---|
| `PROJECT.md` | 按模板生成或补齐，未知内容写 `TODO`，不臆测 |
| `docs/state/` 下至少一个文件 | 建立当前状态基线（当前版本、活跃分支、最近变更摘要、待确认风险） |
| `docs/manual/` 下至少一个文件 | 建立人工操作手册基线（常用命令、发布/回滚、排障入口） |

约束：

- `docs/state/` 与 `docs/manual/` 的文件名、拆分粒度由 AI 根据项目上下文决定，不预设固定名称。
- 两个目录都必须有可用内容（各至少创建或更新 1 个业务文档）。

### `PROJECT.md` 模板

````markdown
# <Project Name>

一句话描述项目目标与边界。

---

## 架构概览

- 核心模块或子系统
- 关键外部依赖或平台

---

## 技术栈

- 语言：
- 构建/包管理：
- 测试框架：

---

## 构建与测试命令

```bash
# 安装依赖
<command>

# 构建
<command>

# 全量测试
<command>
```

---

## 版本号位置

- `<file-path>`：`<field-or-symbol>`

---

## 敏感文件

- `<file-path-or-pattern>`（若无则写“无”）
````

## 与 README 的边界

- `README.md`：产品事实来源（source of truth）。
- `SKILL.md`：对话执行策略（how to operate safely as an agent）。
