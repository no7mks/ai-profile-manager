# PRP: Abilities Relocation

**Status**: accepted

---

## 概述

将 `abilities/` 从独立模板仓库迁移为"源即目标"结构，同时简化 apm 功能范围（移除 capture/ingest），降低项目复杂度。

---

## 动机

1. 当前 `abilities/` 是一个独立的模板存储目录，文件通过后缀（`.cursor.mdc` / `.kiro.md`）区分 target，install 时复制到真实路径。这导致同一份内容有"源"和"目标"两个位置，维护成本高。
2. apm 的 capture/ingest 功能增加了系统复杂度，但当前阶段使用频率低，投入产出不成比例。

---

## 目标

### Phase 1：迁移文件

将 `abilities/` 和 `scaffold/` 下的所有文件迁移到它们真实生效的路径，使本项目自身也能直接使用这些 ability（包括 Kiro session）。

- ability 文件直接放在 `.cursor/` 和 `.kiro/` 下
- scaffold 文件直接放在项目根目录对应位置
- target 通过路径前缀区分，文件名不再携带 target 后缀
- 新增 `abilities.yaml` 作为 ability 注册表
- 废弃 `abilities/` 和 `scaffold/` 目录

### Phase 2：系统支持新路径

apm CLI 代码适配新的文件布局：

- install/check 命令从 `abilities.yaml` 解析 ability 列表，从真实路径读取文件
- 废弃后缀解析逻辑，改为路径前缀判定
- gitignore 命令直接操作项目 `.gitignore` 中的 marker block
- skill 的 install 逻辑处理目录级 duplicate

### 功能精简

移除以下功能以降低复杂度：

- `capture`（捕获本地变更回流到 ability 仓库）
- `ingest`（从外部导入变更）

---

## 命名规则变更

| 改造前（后缀区分 target） | 改造后（路径前缀区分 target） |
|--------------------------|------------------------------|
| `abilities/rules/git/git-conventions.cursor.mdc` | `.cursor/rules/git/git-conventions.mdc` |
| `abilities/rules/git/git-conventions.kiro.md` | `.kiro/steering/rules/git/git-conventions.md` |
| `abilities/agents/code-reviewer.cursor.md` | `.cursor/agents/code-reviewer.md` |
| `abilities/agents/code-reviewer.kiro.md` | `.kiro/agents/code-reviewer.md` |
| `abilities/skills/gitflow/SKILL.md` | `.cursor/skills/gitflow/SKILL.md` + `.kiro/skills/gitflow/SKILL.md` |

---

## 迁移映射

### Rules

| 源文件 | `.cursor/rules/` 目标 | `.kiro/steering/rules/` 目标 |
|--------|----------------------|------------------------------|
| `rules/cursor-scope.cursor.mdc` | `cursor-scope.mdc` | — |
| `rules/kiro-scope.kiro.md` | — | `kiro-scope.md` |
| `rules/graphify.*` | `graphify.mdc` | `graphify.md` |
| `rules/doc/writing-conventions.*` | `doc/writing-conventions.mdc` | `doc/writing-conventions.md` |
| `rules/git/branch-overview.*` | `git/branch-overview.mdc` | `git/branch-overview.md` |
| `rules/git/git-conventions.*` | `git/git-conventions.mdc` | `git/git-conventions.md` |
| `rules/plugin/superpowers-integration.cursor.mdc` | `plugin/superpowers-integration.mdc` | — |
| `rules/safety/command-safety.*` | `safety/command-safety.mdc` | `safety/command-safety.md` |
| `rules/safety/config-safety.*` | `safety/config-safety.mdc` | `safety/config-safety.md` |
| `rules/spec/spec-execution.*` | `spec/spec-execution.mdc` | `spec/spec-execution.md` |
| `rules/spec/spec-goal.*` | `spec/spec-goal.mdc` | `spec/spec-goal.md` |
| `rules/spec/spec-planning.*` | `spec/spec-planning.mdc` | `spec/spec-planning.md` |
| `rules/spec/manual-testing.*` | `spec/manual-testing.mdc` | `spec/manual-testing.md` |
| `rules/spec/kiro-spec-steering.*` | `spec/kiro-spec-steering.mdc` | `spec/kiro-spec-steering.md` |
| `rules/spec/gk-design.kiro.md` | — | `spec/gk-design.md` |
| `rules/spec/gk-requirements.kiro.md` | — | `spec/gk-requirements.md` |
| `rules/spec/gk-tasks.kiro.md` | — | `spec/gk-tasks.md` |

### Agents

| 源文件 | `.cursor/agents/` | `.kiro/agents/` |
|--------|-------------------|-----------------|
| `agents/code-reviewer.cursor.md` | `code-reviewer.md` | — |
| `agents/code-reviewer.kiro.md` | — | `code-reviewer.md` |
| `agents/spec-planner.cursor.md` | `spec-planner.md` | — |
| `agents/spec-gatekeeper.kiro.md` | — | `spec-gatekeeper.md` |

### Skills（每个 target 各一份 duplicate）

| 源 skill | `.cursor/skills/` | `.kiro/skills/` |
|----------|-------------------|-----------------|
| `skills/apm/` | `apm/` | `apm/`（同内容） |
| `skills/gitflow/` | `gitflow/`（含 references/） | `gitflow/`（同内容） |
| `skills/graphify/` | `graphify/`（含 references/） | `graphify/`（同内容） |
| `skills/spec-planning/` | `spec-planning/`（含 references/） | `spec-planning/`（同内容） |

### Gitignore

`abilities/gitignore/template.gitignore` 的内容用 `@apm:block` marker 合并到项目根 `.gitignore`。

### Scaffold

`scaffold/` 目录包含项目脚手架模板，这些文件没有 target 概念（不区分 cursor/kiro），直接安装到目标项目根目录。

| 源文件 | 安装目标 |
|--------|----------|
| `scaffold/AGENTS.md` | `AGENTS.md` |
| `scaffold/CHANGELOG.md` | `CHANGELOG.md` |
| `scaffold/docs/README.md` | `docs/README.md` |
| `scaffold/issues/README.md` | `issues/README.md` |
| `scaffold/docs/state/.gitkeep` | `docs/state/.gitkeep` |
| `scaffold/docs/manual/.gitkeep` | `docs/manual/.gitkeep` |
| `scaffold/docs/notes/.gitkeep` | `docs/notes/.gitkeep` |
| `scaffold/docs/proposals/.gitkeep` | `docs/proposals/.gitkeep` |
| `scaffold/docs/changes/.gitkeep` | `docs/changes/.gitkeep` |

与 abilities 同理，scaffold 文件也应迁移到它们真实生效的路径（即本项目根目录），`scaffold/` 目录废弃。scaffold 类 ability 在 `abilities.yaml` 中用 `type: scaffold` 声明。

---

## `abilities.yaml` 设计

```yaml
version: "1"

abilities:
  - name: git-conventions
    type: rule
    description: 日常 git 操作的基础规则
    targets:
      cursor: .cursor/rules/git/git-conventions.mdc
      kiro: .kiro/steering/rules/git/git-conventions.md

  - name: code-reviewer
    type: agent
    description: 基于 diff 的 code review agent
    targets:
      cursor: .cursor/agents/code-reviewer.md
      kiro: .kiro/agents/code-reviewer.md

  - name: gitflow
    type: skill
    description: GitFlow start/finish 统一执行
    targets:
      cursor: .cursor/skills/gitflow/
      kiro: .kiro/skills/gitflow/

  - name: gitignore-php
    type: gitignore
    description: PHP 相关忽略规则
    marker: "php"
    target: .gitignore

  - name: agents-md
    type: scaffold
    description: Agent 使用原则与项目约定
    target: AGENTS.md

  - name: docs-readme
    type: scaffold
    description: 文档分层规范
    target: docs/README.md

  - name: issues-readme
    type: scaffold
    description: Issue 管理规范
    target: issues/README.md

  - name: changelog
    type: scaffold
    description: 根级 CHANGELOG 模板
    target: CHANGELOG.md
```

---

## 影响范围

### 数据层

- `abilities/` 目录整体废弃，内容迁移到真实生效路径
- `scaffold/` 目录整体废弃，内容迁移到项目根目录
- 新增 `abilities.yaml` 作为 ability 注册表（涵盖 rules/agents/skills/gitignore/scaffold 所有类型）
- `.gitignore` 中的 managed block 从模板文件变为就地维护

### 代码层（apm CLI）

- install/check 命令改为从 `abilities.yaml` 解析，从真实路径读取
- 后缀解析逻辑废弃，改为路径前缀判定
- gitignore 命令直接操作 `.gitignore` marker block
- skill install 处理目录级 duplicate
- scaffold install 逻辑改为从项目根目录读取模板文件
- 移除 capture/ingest 相关代码和命令注册

### 规则/文档层

- `spec-planner.cursor.md` 中引用 `abilities/skills/` 路径需更新
- `spec-planning.cursor.mdc` 和 `spec-planning.kiro.md` 中引用需更新
- `SKILL.md`（apm skill）命令说明需更新
- `README.md` 中 abilities 目录结构说明需更新

### 测试层

- 涉及 `abilities/` 路径的 fixture 和断言需全部更新
- 新增 `abilities.yaml` 解析和校验的测试
- 移除 capture/ingest 相关测试

---

## 约束与决策

- Kiro 的 ability rules 放在 `.kiro/steering/rules/` 子目录下，与项目级 steering 文件区分
- `.cursor/rules/` 和 `.kiro/steering/` 下已有的项目级规则（`agent-entry-point`、`git-release-flow`）不属于 ability 体系，保持不动
- Skills 在两个 target 下是完全相同的 duplicate，接受冗余
- `abilities.yaml` 是 apm 的唯一 ability 注册入口

---

## Non-Goals

- 不做跨项目的 ability 分发（那是未来的事）
- 不做 ability 版本管理
- 不做 capture/ingest 功能
