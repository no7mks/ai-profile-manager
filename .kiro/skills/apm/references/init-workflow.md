# apm init Workflow

本文件定义 `/apm init` 命令的完整执行流程。

---

## 1. Target Structure（目标产出）

`apm init` 执行完成后，项目根目录应具备以下 agentic 开发基础结构：

```
<project-root>/
├── PROJECT.md                   # 项目上下文（技术栈、构建命令、版本号位置、敏感文件）
├── AGENTS.md                    # Agent 协作与表达规范（由 bootstrap 创建，内容由用户维护）
├── README.md                    # 面向用户的项目介绍与快速上手
├── CHANGELOG.md                 # 面向用户的版本摘要
├── docs/
│   ├── state/                   # SSOT：当前系统状态基线
│   │   └── <至少 1 个文件>       #   内容：版本、分支、最近变更、待确认风险
│   └── manual/                  # 人工操作手册
│       └── <至少 1 个文件>       #   内容：常用命令、发布流程、排障入口
├── changes/                     # 归档：已完成文档按版本组织
├── issues/                      # 缺陷管理
├── .kiro/                       # Kiro 平台配置（由 bootstrap + ability 安装管理）
│   └── steering/                #   scope 规则、steering 文件
├── .cursor/                     # Cursor 平台配置（由 bootstrap + ability 安装管理）
│   └── rules/                   #   scope 规则、rule 文件
```

### 内容规格

| 文件/目录 | 最低内容要求 |
|-----------|-------------|
| `PROJECT.md` | 按模板（`references/project.md`）填充；未知字段写 `TODO`，不臆测 |
| `README.md` | 项目名称、一句话描述、快速上手步骤；格式参照 `references/readme.md` |
| `CHANGELOG.md` | 至少包含当前版本条目（或初始化占位） |
| `docs/state/<file>` | 当前版本、活跃分支、最近变更摘要（3-5 条）、待确认风险 |
| `docs/manual/<file>` | 常用命令（build/test/run）、发布流程、排障入口 |
| `changes/` | 目录存在即可；已有内容不动 |
| `issues/` | 目录存在即可；已有内容不动 |
| `AGENTS.md` | 由 bootstrap 创建骨架；init 不修改已有内容 |
| `.kiro/` / `.cursor/` | 由 bootstrap 创建目录结构，ability 安装写入具体文件；init 不直接操作 |

### 文件命名

`docs/state/` 与 `docs/manual/` 的文件名、拆分粒度由 Agent 根据项目上下文决定，不预设固定名称。

### 幂等性

| 规则 | 行为 |
|------|------|
| 已填充字段 | 保留不动，不覆盖 |
| `TODO` 占位字段 | 尝试重新检测并填充 |
| 缺失 section | 补充完整 section |
| 已存在的 state/manual 文件 | 不覆盖，仅在文件不存在时创建 |
| 新增检测到的信息 | 追加到对应 section（不删除已有内容） |
| 检测值与已有内容矛盾 | 不自动覆盖，向用户报告差异并请求决策 |
| 所有字段已完整 | 输出"项目上下文已完整，无需更新"并跳到 Ability 推荐 |

`git diff` 应仅显示新增内容，不应出现已有内容的删除或修改。

### Existing-Content Strategy

| 场景 | 策略 |
|------|------|
| 文件不存在 | **Create** — 从模板/问答结果生成 |
| 文件存在，有 `TODO` 字段 | **Merge** — 仅替换 `TODO`，保留其余 |
| 文件存在，无 `TODO` 字段 | **Skip** — 不修改 |
| 用户显式要求重新生成 | **Overwrite** — 用户确认后整体重写 |

冲突处理：Agent 检测到的值与已有内容矛盾时不自动覆盖，向用户报告差异并请求决策。

---

## 2. Bootstrap Phase（硬性前置）

在任何 Detection / 内容生成之前：

1. 确认当前目录为**业务仓库根**（用户目标项目）。
2. 执行 `apm bootstrap`（默认 `-t cursor -t kiro`，除非用户仅使用单平台）。
3. bootstrap 执行两项工作：
   - 创建 scaffold（`docs/`、`issues/`、`changes/`、`AGENTS.md` 等）
   - 安装 `abilities.yaml` 中 `bootstrap.includes` 列表定义的 ability 到 project scope
4. 若 scaffold 已存在且无 `--force`，binary 可能报错——向用户确认是否加 `--force` 或跳过。
5. 安装平台 scope 规则（必装，无需用户确认）：
   - target 包含 kiro → `apm rule:install kiro-scope -t kiro`
   - target 包含 cursor → `apm rule:install cursor-scope -t cursor`

**禁止**：
- 用无参 `apm install` 代替 bootstrap

---

## 3. Detection Phase

Agent 在生成任何内容前，先收集项目元数据。

### 文件检测

| 文件/目录 | 提取信息 |
|-----------|----------|
| `package.json` | 项目名称、版本、scripts（build/test/lint）、依赖 |
| `composer.json` | 项目名称、版本、autoload、scripts、依赖 |
| `Cargo.toml` | 项目名称、版本、依赖 |
| `pom.xml` / `build.gradle` | 项目名称、版本、构建工具 |
| `Makefile` | 构建/测试命令 |
| `Dockerfile` / `docker-compose.yml` | 运行环境、服务依赖 |
| `.github/workflows/` | CI 命令、测试命令 |
| `tsconfig.json` / `jsconfig.json` | 语言版本、模块系统 |
| `phpunit.xml` / `jest.config.*` / `vitest.config.*` | 测试框架、测试命令 |
| `.gitignore` | 敏感文件线索 |
| `.env.example` / `.env.template` | 环境变量（不读 `.env` 本身） |
| `README.md` | 项目描述、快速上手命令 |
| 已有 `PROJECT.md` | 已填充字段（用于幂等补齐） |
| 已有 `docs/state/` | 已存在的 state 文件 |
| 已有 `docs/manual/` | 已存在的 manual 文件 |

### 命令检测（仅在文件不足以确定时执行）

| 命令 | 目的 | 前提 |
|------|------|------|
| `git remote -v` | 获取仓库 URL | 存在 `.git/` |
| `git log --oneline -5` | 最近变更摘要 | 存在 `.git/` |
| `git branch -a` | 活跃分支列表 | 存在 `.git/` |
| `<pkg-manager> --version` | 确认包管理器版本 | 检测到对应配置文件 |

### Detection 输出

Agent 内部形成 **metadata summary**（不输出给用户），包含：项目名称、一句话描述、语言+版本、构建/包管理工具、测试框架、构建/测试/lint 命令、版本号位置、敏感文件列表、核心模块/子系统、外部依赖/平台。

---

## 4. Routing（路径分支）

Detection 完成后，Agent 根据以下条件**二选一**进入对应路径：

| 条件 | 进入路径 |
|------|---------|
| 存在 package manifest **且** 存在 git history（≥1 commit） | → **Path A：检测式路径**（Section 5） |
| 上述条件不满足 | → **Path B：规划式路径**（Section 6） |

**判断规则：**

1. "package manifest" 指项目根目录下存在 `package.json` / `composer.json` / `Cargo.toml` / `pom.xml` / `build.gradle` 等任一文件
2. "git history" 指 `.git/` 存在且 `git log --oneline -1` 能返回至少 1 条记录
3. 两个条件必须**同时满足**才进入 Path A；任一不满足即进入 Path B
4. 重跑 init 时，若项目已满足 Path A 条件（即使上次走的 Path B），自动切换到 Path A

> **两条路径互斥**。执行完选定路径后，统一进入 Section 7（Ability 推荐）。

---

## 5. Path A：检测式路径

适用于已有代码的项目。

### 5.1 Confirmation

将检测结果分为两类处理：

**Auto-fill（直接填充）：**
项目名称、语言、构建/包管理工具、测试框架、构建命令、测试命令、版本号位置、敏感文件。

**需用户确认：**

| 字段 | 原因 |
|------|------|
| 一句话项目描述 | 需要人类视角的精炼表达 |
| 架构概览（核心模块/子系统） | 代码结构可推断但需确认边界 |
| 关键外部依赖或平台 | 运行时依赖可能不在代码中体现 |
| 敏感文件补充 | `.gitignore` 可能遗漏实际敏感文件 |

**行为规则：**
1. 先展示 auto-filled 结果供用户审阅
2. 对需确认字段逐一询问
3. 用户回答"不确定"或跳过时，填入 `TODO`
4. 用户确认后进入 Generation

### 5.2 Generation

按 Section 1（Target Structure）约定的规格生成或补齐文件。具体内容要求见 Section 1「内容规格」表——特别是 `docs/state/` 需包含当前版本、活跃分支、最近变更摘要，`docs/manual/` 需包含常用命令、发布流程、排障入口。

完成后进入 Section 7（Ability 推荐）。

---

## 6. Path B：规划式路径

适用于空项目或刚初始化的项目（无 manifest 或无 git history）。

### 6.1 交互引导（收集选型意图）

Agent 通过问答收集信息，每轮最多问 3 个问题：

| 轮次 | 收集内容 | 示例问题 |
|------|---------|---------|
| 第 1 轮 | 项目目标、语言/框架选型 | "这个项目打算做什么？计划使用什么语言和框架？" |
| 第 2 轮 | 预期架构、核心模块 | "预期的项目结构是怎样的？有哪些核心模块？" |
| 第 3 轮 | 构建工具、测试框架、部署目标 | "打算用什么构建工具？测试框架有偏好吗？部署到哪里？" |

**行为规则：**
1. 用户回答"不确定"或跳过时，给出合理默认建议并标注为建议值
2. 用户可随时说"就这些"提前结束问答
3. 如果用户一次性给出完整信息，不再逐轮追问

### 6.2 Generation（规划式）

按 Section 1（Target Structure）约定的规格生成文件，填充来源为用户问答结果：

| Section | 填充来源 |
|---------|---------|
| 标题 + 一句话描述 | 用户回答的项目目标 |
| 架构概览 | 用户描述的核心模块/预期结构 |
| 技术栈 | 用户选型 |
| 构建与测试命令 | 基于选型推断的标准命令 |
| 版本号位置 | 基于选型推断 |
| 敏感文件 | 基于选型推断的常见模式 |

`docs/state/` 侧重"初始化决策记录"（选型理由、约束条件、待定事项、下一步行动）。
`docs/manual/` 侧重"开发环境搭建"（前置依赖、初始化命令、开发服务器、测试运行、目录约定）。

与检测式路径不同，规划式路径应给出**具体步骤**而非 `TODO` 占位，因为信息来源是用户的明确选型。

### 6.3 输出确认

Generation 完成后，Agent 向用户展示生成的文件摘要并询问：

1. 是否需要调整选型决策
2. 是否立即执行初始化命令（如 `composer init`、`npm init`）

用户确认后进入 Section 7（Ability 推荐）。

---

## 7. Ability 推荐

**无论走 Path A 还是 Path B，Generation 完成后都执行本阶段。**

### 7.1 流程

1. Agent 执行 `apm show -t <target>`，获取完整 ability 列表及安装状态
2. 从输出中筛选 `[not-installed]` 的 ability（已安装的不推荐）
3. 基于已收集的项目信息（技术栈、框架、工作流特征），从未安装列表中选出与项目相关的推荐项
4. 向用户输出推荐列表：

```
建议安装以下 ability：

- <ability-name>：<一句话推荐理由>
- <ability-name>：<一句话推荐理由>
- ...

是否安装？（可选择全部安装，或告诉我只装哪几个）
```

5. 用户同意后，Agent 对选定的 ability 逐一执行 `apm install`（默认 `-t cursor -t kiro`）
6. 执行后记录每条命令的输出（`[ok]` / `[skip]` / `[fail]`），向用户汇报结果

### 7.2 推荐逻辑

推荐范围**仅限 `apm show` 输出中标记为 `[not-installed]` 的 ability**。以 preset 为单位优先推荐（避免零散安装）：

| Preset / Ability | 推荐条件 | 优先级 |
|-----------------|---------|--------|
| `spec-core` | 所有项目（强烈推荐） | 高 |
| `gitflow` | 项目使用 git 且有多分支协作需求 | 高 |
| `graphify` | 仅 Path A（已有代码的项目），代码量较大或模块关系复杂 | 中 |
| `gitignore:php` / `python` / `kotlin` | 检测到对应语言 | 中 |

**不主动推荐的 ability：**

| Ability | 原因 |
|---------|------|
| `lark-sheets` | 业务专属，需用户主动安装 |
| `tga-query` | 业务专属，需用户主动安装 |
| `plugin:superpowers-integration` | 依赖特定插件环境 |

如果 `apm show` 中无 `[not-installed]` 项，输出"所有可用 ability 已安装"并结束。

### 7.3 安装规则

1. 仅安装 registry 中存在的 preset / typed ability；名称不得臆造
2. preset 安装失败时整单不写入，向用户报告后调整计划

### 7.4 用户交互

- 用户说"全部安装" → 全部执行
- 用户指定部分 → 只装指定的
- 用户说"不需要" / "跳过" → 结束流程，不安装

---

## 8. 结束总则

init 流程结束前，Agent 须向用户汇报本次执行的**所有 apm 命令及结果**，包括：

- Bootstrap 阶段的 `apm bootstrap` 输出
- Scope 规则安装（`apm rule:install kiro-scope` / `cursor-scope`）输出
- Ability 推荐阶段的各条 `apm install` 输出

格式示例：

```
本次执行的 apm 命令：

1. apm bootstrap -t cursor -t kiro          [ok]
2. apm rule:install kiro-scope -t kiro      [ok]
3. apm rule:install cursor-scope -t cursor  [ok]
4. apm install spec-core -t cursor -t kiro  [ok]
5. apm install gitflow -t cursor -t kiro    [skip: already installed]
```
