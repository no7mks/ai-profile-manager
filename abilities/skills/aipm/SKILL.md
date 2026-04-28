---
name: aipm
description: "当你想在新项目里用 aipm init 装脚手架与 scope 规则，或在本机 .cursor/.kiro 安装或更新 skill、steering、agent，或用 capture 反馈改动时，可使用本 skill 约定的 aipm 命令。"
---

# aipm（AI Profile Manager）

本 Skill 帮助用户通过 `aipm` 工具管理项目的 skills、rules/steerings、custom-agents 等资源。

`aipm` 对应 Composer 包 `no7mks/ai-profile-manager`，安装后命令为 `aipm`，运行需 **PHP ^8.5**。下文由 Agent 协助执行或提示用户执行；`-t` 用于选择 Cursor、Kiro 等安装目标；示例中的名称须替换为实际的 ability / preset。

## 全局安装

用户若尚未安装或无法运行 `aipm`：

```bash
composer global require no7mks/ai-profile-manager
```

把 Composer 全局 `vendor/bin` 加入 `PATH`（以用户本机为准；常见示例）：

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

```bash
echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

```bash
which aipm
aipm --help
```

## 新项目脚手架（`init`）

在**业务仓库根目录**或 `[path]` 下安装捆绑脚手架（模板来自包内 **`scaffold/`**：`docs/`、`issues/`、`AGENTS.md`、`PROJECT.md`），并按 `-t` 写入对应平台的 **scope** 规则（`cursor-scope` / `kiro-scope`）。**不传 `-t` 时**与 `skill:install` 等一致，默认目标为 **cursor** 与 **kiro**。

```bash
aipm init
aipm init path/to/project -t cursor
```

已存在脚手架文件且需覆盖时使用 `-f` / `--force`。

## 装进 `.cursor` / `.kiro`（日常主路径）

在**用户关心的业务仓库根目录**执行（路径按用户实际 workspace）；`-t` 决定写入 **Cursor** 还是 **Kiro** 侧布局。

**单独装某一类**

```bash
aipm skill:install <name> -t cursor
aipm rule:install <name> -t kiro
aipm agent:install <name1> <name2> -t cursor
```

**按 preset 一批装**（顶层 `install` 只处理 preset）

```bash
aipm install gitflow -t cursor
aipm install kiro-spec -t kiro
```

需要对照是否缺件、是否与装包不一致时，可用 `check`（按需）：

```bash
aipm skill:check <name> -t cursor
aipm rule:check <name> -t kiro
aipm agent:check <name> -t cursor
aipm check <preset> -t cursor
```

更新已拉取的能力清单可执行：`aipm update`（按需）。

## 用 capture 反馈本地改动（用户最常需知道的一节）

用户在**本机已经装好的 skill / rule / agent 相关文件**上做了修改后，若要**把「相对安装结果」的差异记下来、用于反馈给提供方**，用 **capture**。若当前仓库根下存在参与管理的 **`abilities/`** 布局，`check` / `capture` 一般在**该仓库根目录**执行。有差异时会在本机 **`~/.aipm/events`** 下写入事件文件（脚本可加 `-y` / `--yes` 跳过交互）。

```bash
aipm skill:capture <name> -t cursor
aipm rule:capture <name> -t kiro
aipm agent:capture <name> -t cursor
aipm capture <preset> -t kiro
aipm capture -t cursor --yes
```

说明：**用户侧只需知道「capture + 事件目录」即可**；谁在下游消费这些事件、是否写回某个 catalog 仓库，属于维护方流程，不必作为 other-repo 用户的必读前提。

## 退出码（简要）

- **0**：正常（含检查通过、capture 无可上报差异等依具体子命令而定）。
- **2**：存在需关注的差异（常见于 capture 发现变更）。
- **1**：命令失败（未装全局 `aipm`、PHP 不满足、cwd 不对等）。
