# PRP: Deploy Scope and CLI Onboarding

**Status**: draft

定义 user/project 部署 scope、`global-setup` 清单、onboarding，以及 `show`/`update`/`cleanup`。

本 PRP supersedes PRP-001 中「不做跨项目 ability 分发」的 Non-Goal。

---

## 动机

- 通用能力改 user 安装，降低每仓 `.cursor/` 噪音。
- 用户 **`composer global` 安装 apm CLI 后**，还须执行 **`apm global-setup`**，Cursor/Kiro 才能加载 `/apm` skill。
- 项目向能力由 **`/apm init` 按项目特征** `apm add …`，不再用 preset `default`。
- `show` 与 `update` 职责分离；CLI 统一 install/add、显式类型。

---

## 1. Scope 模型

- `project` → `getcwd()` + `targets[target]`；`user` → `$HOME`（Windows `%USERPROFILE%`）+ 同路径。
- `scopes` 省略 = `[project]`，首项为默认；`--scope` 非法 → 报错。
- **仅 project**：`gitignore`、`prompt`、`tga-query`、`lark-sheets`、`cursor-scope`、`kiro-scope`、`superpowers-integration`。

---

## 2. `abilities.yaml`

- **删除** preset `default`；**不设** `project-default`。
- 新增顶层 **`global-setup:`**（typed includes），**仅**供 `apm global-setup` 读取；**不是** preset。
- **不** 在 `abilities.yaml` 增加 `bootstrap:`；scaffold 留在代码（现 `ProjectInitializer`，由 `apm bootstrap` 调用）。ability 语义见 `docs/state/abilities-model.md`。

**global-setup 清单**（均 user scope，首项 `user`）：

`skill:apm`；`skill:quick-plan`、`skill:build-plan`、`skill:quick-release`；`agent:code-reviewer`；`rule:doc:writing-conventions`、`rule:doc:doc-convergence`；`rule:safety:command-safety`、`rule:safety:config-safety`；`rule:git:git-conventions`（通用流程，路径示例不保证适配所有仓）；`rule:plan:quick-plan-conventions`。（**无通配符**，与 `includes` 逐项写法一致。）

其余 preset（`php`、`gitflow`…）独立维护 `scopes`。

---

## 3. 命令：global-setup / bootstrap / cleanup

### `apm global-setup`

- 只装 `global-setup:` 列表；**禁止** preset、列表外 ability、**禁止** project scope（无 `--scope`）。
- 固定 user 根；任意 cwd；**幂等**（`--force` 覆盖已装 ability 文件）。
- 成功后提示：进业务仓执行 `/apm init`。

### `apm bootstrap`

- 仅 **scaffold**（`ProjectInitializer` 硬编码：模板 + 空目录，**不进 abilities.yaml**）；不装 ability、不装 preset。
- **`/apm init` 必须首先调用**（见 §4）。

### `apm cleanup`

- **仅** 卸载 **project scope** 的 ability（见 state `abilities-model.md`）；**不** 动 bootstrap/scaffold。
- **不** 删 `PROJECT.md`、init 写入的 `docs/state|manual` 内容、用户改过的 `AGENTS.md` 等；**不** 动 user 目录。
- 标准重装：**`/apm cleanup` → `/apm init`**（**不**要求再跑 `global-setup`，user 侧保留）。

---

## 4. `/apm init`（两条路径）

**硬性**：流程 **必须先** `apm bootstrap`（幂等规则同现 `ProjectInitializer`）。

### 4.1 检测式（老项目）

条件：有 manifest + git ≥1 commit。

1. `apm bootstrap`
2. Agent Detection
3. Agent 根据仓库上下文 **自行决定** 调用哪些 `apm add preset|rule|skill …`（知晓 registry 中全部 preset）；**不问死板的固定表**；问用户什么、问多少由 Agent 安排；**输出须列出**拟执行/已执行的每条 apm 命令及结果。
4. init 流程中所有 **`apm add` 命令** 默认带 **`-t cursor -t kiro`**（双平台都装，不依赖「当前 IDE 会话」）。
5. Agent：`PROJECT.md`、`docs/state/`、`docs/manual/`

### 4.2 规划式（新项目）

条件：不满足检测式。

1. `apm bootstrap`
2. Agent 规划式确认技术栈与 IDE
3. Agent 决定并执行 `apm add …`（用户确认后）；默认 `-t cursor -t kiro`；输出足够详细。
4. Agent：生成 PROJECT / state / manual（可大量 TODO）

两路径 **不** 把 global-setup 项再装进 project。`init-workflow.md` 须写 Agent 决策原则与输出要求（**不**写死 preset 映射表）。

---

## 5. CLI 规则

| 项 | 规则 |
|----|------|
| 无参 install/add | 直接报错，提示 global-setup / bootstrap / 显式 add |
| install↔add、uninstall↔remove | 全树同义词 |
| check | 无别名；`apm skill check foo` ≡ `apm check skill foo` |
| 类型参数 | 必填 `preset\|skill\|rule\|…`；禁止 `apm add gitflow` |
| 输出 | 每项 `[ok]`；跳过必 `[skip] <原因>`（如无 cursor target） |
| Preset | **先校验全部条目**，任一失败 → **整单不装** |
| `--scope` 批量 | 逐项校验；任一不允许 → 整单失败 |

`-t cursor` 时 kiro-only ability（如 `quick-plan`）输出 skip，不报错。

---

## 6. `apm show`

- 仅列 **常规 ability**（skill / rule / agent / hook / gitignore / prompt）；**不**列 preset 名、**不**列 scaffold。
- **每个 ability 一行**；target 为说明文字（如 `cursor, kiro`）。
- user 与 project **合并为一行**展示状态；若 **两侧均存在安装** → 状态旁 **`[warn] installed in both user and project`**（并简述两侧路径或 scope）。
- 状态：`not installed` / `installed` / `installed with local change`（相对 global apm 包 baseline）。
- `--scope` 可过滤。**与 update 无关。**

示例：

```text
skill:apm        installed (user)     cursor, kiro
skill:apm        installed (user+project) [warn] installed in both user and project   cursor, kiro
rule:cursor-scope  not installed        cursor
```

---

## 7. `apm update`

1. 枚举本地 **已安装** 常规 ability（user+project）。
2. baseline 仅来自 **composer global** 的 apm 包；若当前执行环境是项目 `vendor/bin/apm`（或非 global 安装）→ **报错** 并提示使用 global 安装。
3. **无参**：仅输出 **有变化** 的项；无变化时一句 `up to date`。
4. **`--force`**：覆盖同名已安装项。preset 级无 `--scope` override（用各 ability 默认 scope）。

---

## 8. 平台验证（阻塞）

验证 `~/.cursor`、`~/.kiro` 加载；user hook；同名 user/project 行为仅记录。

---

## Non-Goals

省略类型 `apm add <name>`；自动迁移；global-setup 装 preset；cleanup 动 scaffold/docs/user；capture/ingest。

---

## Breaking changes

无参 `install` → `bootstrap`/`global-setup`；删除 preset `default`；README 三阶段首装。

---

## 文档同步（实现 PRP 时一并改，不在 proposal 阶段改 SSOT 外的副本）

| 文件 | 待改内容 |
|------|----------|
| `docs/README.md` | §「初始化约定」：将 `apm install` bootstrap 改为 `apm bootstrap`（或 `/apm init` 触发的 bootstrap），与无参 `install` 废弃一致 |
| `README.md`、`docs/manual/usage.md` | Quick Start：`global-setup` → 进仓 `/apm init`；去掉无参 `apm install` |
| `docs/state/cli-commands.md`、`install-behavior.md` | `install`/`bootstrap`/`global-setup`/`cleanup`/`show`/`update` 全量行为 |
| `.cursor/skills/apm/SKILL.md`（及 kiro 副本） | 命令表、init 必调 bootstrap、cleanup→init 路径 |

---

## 验收（摘要）

1. global-setup 仅 user 列表；kiro-only 在 `-t cursor` 时 skip 且可观测。
2. init 必 bootstrap；检测式会 `add preset php` 等；project 不重复 global-setup 项。
3. preset 一项非法 → 零写入。
4. show 三态；update 无参只报告、`--force` 覆盖。
5. cleanup 仅移除 project ability；`docs/`/`AGENTS.md` 仍在；再 `/apm init` 可重装 project 能力。

---

## 已关闭决策

| 项 | 决策 |
|----|------|
| default / project-default preset | 删除 / 取消 |
| global-setup 当 preset | 否，仅 yaml 键 |
| 无参 install deprecation | 否，直接报错 |
| git-conventions | global-setup user，通用流程 |
| bootstrap | 代码 `ProjectInitializer`，不进 yaml |
| ability 定义 | state `abilities-model.md`：可装且可卸 |
| cleanup 范围 | 仅 uninstall ability（project）；不碰 scaffold/docs/user |
| show 格式 | 仅常规 ability；无 preset 行；user+project 合并一行，双侧必有 warn |
| init 装什么 | Agent 自定；默认 `-t cursor -t kiro`；输出须详尽 |
| cleanup 后 | `/apm cleanup` → `/apm init`，无需 global-setup |
| update 入口 | 仅 composer global apm；vendor 执行报错 |
| global-setup | 幂等 |
