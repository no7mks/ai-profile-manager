---
name: apm
description: "当用户说 /apm 或要求执行 apm 命令（init、global-setup、bootstrap、install、check、cleanup、show、update）时激活。"
---

# apm（AI Profile Manager）

本 Skill 是 **Agent 执行手册**。用户界面统一使用 `/apm <command>`，必要时再由 Agent 在后台调用 `apm` binary。

## 命令总览

| 用户命令 | 意图 | 调用 binary | 后台动作 |
|---|---|---|---|
| `/apm init` | 生成项目上下文并安装 project 能力 | 是（多步） | 见 [init-workflow.md](references/init-workflow.md) |
| `/apm global-setup` | 提示用户一次性 global 安装 | 是 | `apm global-setup -t <target>`（任意 cwd） |
| `/apm bootstrap` | 项目 scaffold | 是 | `apm bootstrap -t <target>` |
| `/apm cleanup` | 清理 project 已装 ability | 是 | `apm cleanup` |
| `/apm install <preset>` | 按 preset 批量安装 | 是 | `apm install <preset> -t cursor -t kiro`（默认双平台） |
| `/apm install skill <name>` | 安装单个 skill | 是 | `apm skill:install <name> -t …` |
| `/apm install rule <name>` | 安装 rule | 是 | `apm rule:install <name> -t …` |
| `/apm install agent <name…>` | 安装 agent | 是 | `apm agent:install <name…> -t …` |
| `/apm check <preset>` | 检查 preset 漂移 | 是 | `apm check <preset> -t …` |
| `/apm check <type> <name>` | 检查单项漂移 | 是 | `apm {skill\|rule\|agent}:check <name> -t …` |
| `/apm show` | 查看安装状态 | 是 | `apm show -t …` |
| `/apm update` | 从 global baseline 更新 | 是 | `apm update` / `apm update --force`（须 global 二进制） |

**禁止**：在 init 流程中将 Global Setup List 项（`apm` skill、`git-conventions` 等）以 project scope 再装一遍；这些仅由 `apm global-setup` 写入 user scope。

## 何时使用本 Skill

- 全局环境：`global-setup`（Composer global 安装后一次）
- 业务仓库：bootstrap → init → preset/typed install
- 检查漂移、查看状态、清理 project 安装、global 更新

## Agent 执行原则

1. **先确认 cwd**：业务命令在目标仓库根目录执行；`global-setup` 可在任意目录。
2. **init 必先 bootstrap**：未 scaffold 时先 `apm bootstrap`，再 Detection / 能力安装。
3. **不臆造名称**：preset / ability 名须来自 registry 或用户输入；**不写死**「某语言必装某 preset」映射表——根据仓库上下文自行决定，并向用户说明理由。
4. **双平台默认**：init 中每条 `apm install` / `apm add` 默认 `-t cursor -t kiro`，除非用户明确要求单平台。
5. **命令清单**：init 结束前应列出拟执行/已执行的 **每条** apm 命令及结果（成功 / 跳过 / 失败）。
6. **保守覆盖**：仅用户明确要求时使用 `--force`。
7. **Prompt ability**：安装含 `prompt` 的 preset 时遵循 binary 输出的 `message` 指引。

## 常见失败与处理

- `apm: command not found`：提示 `composer global require` 并检查 `PATH`。
- 无参 `apm install` / `default` preset：引导三阶段首装（`global-setup` → `bootstrap` → init/显式 preset）。
- `update` 失败：确认使用 global `vendor/bin/apm`。

## `/apm init` 交付物

| 交付物 | 要求 |
|---|---|
| `PROJECT.md` | 按 [模板](references/project.md)；未知填 `TODO` |
| `docs/state/` | 至少一个基线文件（版本、分支、变更摘要、风险） |
| `docs/manual/` | 至少一个操作文件（常用命令、发布/排障入口） |
| project abilities | 按路径安装所需 preset/typed 项；**不**重装 global-setup 项 |

流程细节见 [init-workflow.md](references/init-workflow.md)。

## 与 README 的边界

- `README.md`：产品事实与用户 Quick Start。
- `SKILL.md`：Agent 如何安全、有序地操作 apm。
