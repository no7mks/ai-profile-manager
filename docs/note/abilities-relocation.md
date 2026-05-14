# Abilities Relocation

将 `abilities/` 从独立模板仓库迁移为"源即目标"结构的改造计划。

---

## 动机

当前 `abilities/` 作为技能仓库的独立文件夹，通过文件名后缀（`.cursor.mdc` / `.kiro.md`）区分 target。改造后：

- 源位置与目标位置的相对路径一致（所见即所得）
- target 通过路径前缀（`.cursor/` vs `.kiro/`）区分，不再依赖后缀
- 用一个声明式清单文件（`abilities.yaml`）管理 ability 元数据

---

## 命名规则变更

| 改造前（后缀区分 target） | 改造后（路径前缀区分 target） |
|--------------------------|------------------------------|
| `abilities/rules/git/git-conventions.cursor.mdc` | `.cursor/rules/git/git-conventions.mdc` |
| `abilities/rules/git/git-conventions.kiro.md` | `.kiro/steering/rules/git/git-conventions.md` |
| `abilities/agents/code-reviewer.cursor.md` | `.cursor/agents/code-reviewer.md` |
| `abilities/agents/code-reviewer.kiro.md` | `.kiro/agents/code-reviewer.md` |
| `abilities/skills/gitflow/SKILL.md` | `.cursor/skills/gitflow/SKILL.md` + `.kiro/skills/gitflow/SKILL.md`（duplicate） |

---

## 迁移映射

### Rules

| 源文件 | `.cursor/rules/` 目标 | `.kiro/steering/rules/` 目标 |
|--------|----------------------|------------------------------|
| `rules/cursor-scope.cursor.mdc` | `cursor-scope.mdc` | —（无 kiro 版） |
| `rules/kiro-scope.kiro.md` | —（无 cursor 版） | `kiro-scope.md` |
| `rules/graphify.*` | `graphify.mdc` | `graphify.md` |
| `rules/doc/writing-conventions.*` | `doc/writing-conventions.mdc` | `doc/writing-conventions.md` |
| `rules/git/branch-overview.*` | `git/branch-overview.mdc` | `git/branch-overview.md` |
| `rules/git/git-conventions.*` | `git/git-conventions.mdc` | `git/git-conventions.md` |
| `rules/plugin/superpowers-integration.cursor.mdc` | `plugin/superpowers-integration.mdc` | —（无 kiro 版） |
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
| `skills/apm/` | `apm/`（SKILL.md） | `apm/`（同内容） |
| `skills/gitflow/` | `gitflow/`（含 references/） | `gitflow/`（同内容） |
| `skills/graphify/` | `graphify/`（含 references/） | `graphify/`（同内容） |
| `skills/spec-planning/` | `spec-planning/`（含 references/） | `spec-planning/`（同内容） |

### Gitignore

`abilities/gitignore/template.gitignore` 的内容用 `@apm:block` marker 合并到项目根 `.gitignore` 中。

---

## `abilities.yaml` 设计

放在项目根目录，声明所有 ability 的元数据：

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
```

字段说明：

- `name`：ability 唯一标识
- `type`：`rule` | `agent` | `skill` | `gitignore`
- `description`：一句话描述
- `targets`：按 target 平台列出文件/目录路径
- `marker`（gitignore 专用）：`@apm:block` 中的 ability 标识

---

## 影响范围

这不只是文件搬家。以下维度都需要改动：

### 数据层

- `abilities/` 目录整体废弃，内容迁移到真实生效路径
- 新增 `abilities.yaml` 作为 ability 注册表（取代目录结构 + 后缀的隐式约定）
- `.gitignore` 中的 managed block 从模板文件变为就地维护

### 代码层（apm CLI）

- 所有读取 `abilities/` 路径的命令（`install`、`check`、`capture`）需要改为从 `abilities.yaml` 解析 ability 列表，再从真实路径读取文件
- 后缀解析逻辑（`.cursor.mdc` / `.kiro.md` → target 判定）废弃，改为路径前缀判定
- gitignore 相关命令需要直接操作项目 `.gitignore` 中的 marker block，不再读模板文件
- skill 的 install/capture 逻辑需要处理目录级 duplicate（两个 target 各一份）

### 规则/文档层

- `spec-planner.cursor.md` 中引用了 `abilities/skills/spec-planning/` 路径，需要更新为 `.cursor/skills/spec-planning/`
- `spec-planning.cursor.mdc` 和 `spec-planning.kiro.md` 中引用了 `abilities/skills/spec-planning/` 路径，同上
- `SKILL.md`（apm skill）中的命令说明可能需要更新以反映新的路径约定
- `README.md` 中如有 abilities 目录结构说明需要更新

### 测试层

- 现有测试中涉及 `abilities/` 路径的 fixture 和断言需要全部更新
- 需要新增测试覆盖 `abilities.yaml` 的解析和校验

---

## 约束与决策

- Kiro 的 ability rules 放在 `.kiro/steering/rules/` 子目录下，与已有的项目级 steering 文件（`agent-entry-point.md`、`git-release-flow.md`）区分
- `.cursor/rules/` 下已有的 `agent-entry-point.mdc` 和 `git-release-flow.mdc` 是项目级规则，不属于 ability 体系，保持不动
- Skills 在两个 target 下是完全相同的 duplicate，接受这个冗余
- `abilities.yaml` 是 apm 的唯一 ability 注册入口，取代原来的目录扫描 + 后缀推断
