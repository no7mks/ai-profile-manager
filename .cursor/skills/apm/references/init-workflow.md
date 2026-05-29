# apm init Workflow

本文件定义 `/apm init` 命令的完整执行流程。Agent 执行 init 时必须严格按照以下阶段顺序推进。

---

## 1. Detection Phase

Agent 在生成任何内容前，先收集项目元数据。以下为必须检查的信息源：

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
| 已有 `PROJECT.md` | 已填充的字段（用于 idempotent 补齐） |
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

Agent 内部形成一份 **metadata summary**（不输出给用户），包含：

- 项目名称
- 一句话描述
- 语言 + 版本
- 构建/包管理工具
- 测试框架
- 构建/测试/lint 命令
- 版本号位置
- 敏感文件列表
- 核心模块/子系统
- 外部依赖/平台

---

## 2. Confirmation Phase

Detection 完成后，Agent 将字段分为两类处理：

### Auto-fillable 字段（无需用户确认）

以下字段可从文件中明确提取，Agent 直接填充：

- 项目名称（来自 package manifest 的 `name` 字段）
- 语言（来自配置文件类型）
- 构建/包管理工具（来自 lock file 或 manifest）
- 测试框架（来自测试配置文件）
- 构建命令（来自 scripts 或 Makefile）
- 测试命令（来自 scripts 或 CI 配置）
- 版本号位置（来自 manifest 文件路径 + 字段名）
- 敏感文件（来自 `.gitignore` 中的模式）

### User-confirmation-required 字段

以下字段需要用户确认或补充，Agent 应主动询问：

| 字段 | 原因 |
|------|------|
| 一句话项目描述 | 需要人类视角的精炼表达 |
| 架构概览（核心模块/子系统） | 代码结构可推断但需确认边界 |
| 关键外部依赖或平台 | 运行时依赖可能不在代码中体现 |
| 敏感文件补充 | `.gitignore` 可能遗漏实际敏感文件 |

### Confirmation 行为规则

1. Agent 先展示 auto-filled 结果供用户审阅
2. 对 user-confirmation-required 字段逐一询问
3. 用户回答 "不确定" 或跳过时，填入 `TODO` 占位
4. 用户确认后进入 Generation Phase

---

## 3. Generation Phase

### 3.1 PROJECT.md 最低内容要求

生成的 `PROJECT.md` 必须包含以下 section（即使部分字段为 `TODO`）：

| Section | 最低要求 |
|---------|----------|
| 标题 + 一句话描述 | 必须有项目名称；描述可为 `TODO` |
| 架构概览 | 至少列出 1 个核心模块或写 `TODO` |
| 技术栈 | 语言、构建/包管理、测试框架三项必填（可为 `TODO`） |
| 构建与测试命令 | 至少填充 1 个已知命令；未知命令写 `<command>` |
| 测试执行约定 | 固定文本，不可省略 |
| 版本号位置 | 至少 1 个位置或写 `TODO` |
| 敏感文件 | 列出已知项或写"无" |

模板格式参照 `references/project.md`。

### 3.2 State Baseline 最低内容要求

在 `docs/state/` 下创建至少 1 个文件，内容必须包含：

| 内容项 | 说明 |
|--------|------|
| 当前版本 | 从 manifest 提取，或写 `TODO` |
| 活跃分支 | 从 git 提取主要分支名 |
| 最近变更摘要 | 最近 3-5 条 commit 的概要 |
| 待确认风险 | 已知的技术债或不确定项（可为空列表） |

文件名由 Agent 根据项目上下文决定（如 `baseline.md`、`current-status.md` 等）。

### 3.3 Manual Baseline 最低内容要求

在 `docs/manual/` 下创建至少 1 个文件，内容必须包含：

| 内容项 | 说明 |
|--------|------|
| 常用命令 | 开发者日常使用的 build/test/run 命令 |
| 发布流程 | 发布步骤概要（可为 `TODO` 或简述） |
| 排障入口 | 日志位置、调试命令、常见问题（可为 `TODO`） |

文件名由 Agent 根据项目上下文决定（如 `getting-started.md`、`operations.md` 等）。

---

## 4. Idempotency Rules

`/apm init` 可被重复执行。重复执行时遵循以下规则：

| 规则 | 行为 |
|------|------|
| 已填充字段 | **保留不动**，不覆盖 |
| `TODO` 占位字段 | 尝试重新检测并填充 |
| 缺失 section | 补充完整 section |
| 已存在的 state/manual 文件 | 不覆盖，仅在文件不存在时创建 |
| 新增检测到的信息 | 追加到对应 section（不删除已有内容） |

### 幂等性保证

- 对已有内容执行 init 后，`git diff` 应仅显示新增内容，不应出现已有内容的删除或修改
- 如果所有字段已填充且 state/manual 文件已存在，init 应输出"项目上下文已完整，无需更新"并退出

---

## 5. Existing-Content Strategy

当目标文件已存在时，Agent 按以下决策矩阵处理：

### 决策矩阵

| 场景 | 策略 | 说明 |
|------|------|------|
| `PROJECT.md` 不存在 | **Create** | 从模板生成完整文件 |
| `PROJECT.md` 存在，有 `TODO` 字段 | **Merge** | 仅替换 `TODO` 为检测到的值，保留其余内容 |
| `PROJECT.md` 存在，无 `TODO` 字段 | **Skip** | 不修改，告知用户"已完整" |
| `PROJECT.md` 存在，用户要求重新生成 | **Overwrite** | 用户显式确认后整体重写 |
| `docs/state/<file>` 不存在 | **Create** | 生成 baseline 文件 |
| `docs/state/<file>` 已存在 | **Skip** | 不修改已有 state 文件 |
| `docs/manual/<file>` 不存在 | **Create** | 生成 manual 文件 |
| `docs/manual/<file>` 已存在 | **Skip** | 不修改已有 manual 文件 |
| `docs/state/` 目录不存在 | **Create** | 创建目录并生成文件 |
| `docs/manual/` 目录不存在 | **Create** | 创建目录并生成文件 |

### 策略定义

- **Create**：从零生成文件/目录
- **Merge**：保留已有内容，仅填充空缺（`TODO` 或缺失 section）
- **Skip**：不做任何修改
- **Overwrite**：整体替换文件内容（仅在用户显式要求时触发）

### 冲突处理

- 当 Agent 检测到的值与已有内容矛盾时（如版本号不一致），**不自动覆盖**，而是向用户报告差异并请求决策
- 用户未响应时，保留已有内容并在旁边添加注释标记差异
