---
name: aipm
description: "当你想在新项目里用 aipm init 装脚手架与 scope 规则，或在本机 .cursor/.kiro 安装或更新 skill、steering、agent，或用 capture 反馈改动时，可使用本 skill 约定的 aipm 命令。"
---

# aipm（AI Profile Manager）

本 Skill 是 **Agent 执行手册**，用于在对话中快速、稳妥地调用 `aipm`。  
完整命令语义、参数与行为细节以仓库根 `README.md` 为准；本文件不重复 CLI 手册。

## 何时使用本 Skill

当用户意图属于以下类型时，优先使用 `aipm`：

- 在业务仓库初始化脚手架与 scope（`init`）。
- 把能力安装到 `.cursor` / `.kiro`（`skill:install` / `rule:install` / `agent:install` / `install`）。
- 检查或捕获本地改动（`check` / `capture`）。
- 将 capture 事件回流到能力仓库（`ingest`，仅维护场景）。

## Agent 执行原则

1. 先确认当前目录：默认应在用户目标业务仓库根目录执行。
2. 不臆造名称：ability / preset 名必须来自用户输入或仓库真实存在项。
3. 目标明确：涉及写入时显式带 `-t`（`cursor` 或 `kiro`），避免写错平台目录。
4. 先小后大：不确定时先做 typed 命令（如 `skill:check <name>`），再做 preset 或全量操作。
5. 保守覆盖：只有用户明确要求时才使用 `--force`。
6. 非交互环境才用 `--no-prefill` 或 `--yes`，正常对话保持可确认流程。

## 常用决策树（给 Agent）

- 新项目落地模板：`aipm init [path] -t <target>`
- 安装单个能力：
  - `aipm skill:install <name> -t <target>`
  - `aipm rule:install <name> -t <target>`
  - `aipm agent:install <name...> -t <target>`
- 按预设批量安装：`aipm install <preset> -t <target>`
- 比较当前改动：
  - typed：`aipm {skill|rule|agent}:check <name> -t <target>`
  - preset：`aipm check <preset> -t <target>`
- 生成改动事件：
  - typed：`aipm {skill|rule|agent}:capture <name> -t <target>`
  - preset / 全量：`aipm capture [preset] -t <target> [--yes]`
- 维护方写回能力仓库：`aipm ingest [--changes-dir <dir>]`

## 常见失败与处理

- `aipm: command not found`：提示用户全局安装并确认 `PATH`，再重试。
- 命令执行但写入位置异常：优先检查 cwd 与 `-t`。
- capture 未产出文件：可能是无差异，先看命令摘要与退出码再判断。
- ingest 未生效：核对 `~/.aipm/changes`（或 `--changes-dir`）是否存在可处理变更。

## 与 README 的边界

- `README.md`：产品事实来源（source of truth）。
- `SKILL.md`：对话执行策略（how to operate safely as an agent）。
