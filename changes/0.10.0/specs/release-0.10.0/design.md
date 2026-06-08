# Design: Release 0.10.0

## Overview

本设计覆盖 release 0.10.0 的两项交付：

1. **Cursor Skill 拆分**：创建 `.cursor/skills/quick-plan/` 和 `.cursor/skills/build-plan/` 两个 Cursor Skill，更新 `abilities.yaml` 注册与引导列表，删除旧 rule。
2. **Release 收口**：版本号同步、CHANGELOG 收敛、annotated tag。

设计原则：最小变更、与现有 Skill 结构一致、不引入新抽象。

---

## Architecture

### 变更模块

| 模块/文件 | 变更类型 | 说明 |
|-----------|---------|------|
| `abilities.yaml` | 修改 | skills section 新增 cursor target；rules section 删除旧条目；bootstrap.includes 清理旧引用 |
| `.cursor/skills/quick-plan/SKILL.md` | 新建 | Cursor 侧 quick-plan Skill 内容 |
| `.cursor/skills/build-plan/SKILL.md` | 新建 | Cursor 侧 build-plan Skill 内容 |
| `.cursor/rules/plan/quick-plan-conventions.mdc` | 删除 | 废弃的旧 rule |
| `.cursor/rules/cursor-scope.mdc` | 修改 | 移除对旧 rule 的路径引用 |
| `composer.json` | 修改 | version → `0.10.0` |
| `src/Core/Application.php` | 修改 | version 参数 → `0.10.0` |
| `CHANGELOG.md` | 修改 | 新增 0.10.0 section |

### 不涉及的模块

- PHP 源码（`src/`）中除 `Application.php` 版本号外无变更
- Kiro 侧 Skill（已存在，不修改）
- 测试代码（无新功能逻辑需要测试；registry 解析由现有 E2E 覆盖）

---

## Components and Interfaces

### 1. Cursor quick-plan Skill

**路径**: `.cursor/skills/quick-plan/SKILL.md`

**结构**（参照 `spec-planning` SKILL.md 模板）：

```markdown
---
name: quick-plan
description: <触发描述>
---

# Quick Plan

## 触发场景
## 执行协议
## 工作流程
  ### Step 1: 声明模式
  ### Step 2: 收集需求
  ### Step 3: Clarification Round
  ### Step 4: 确认计划名称
  ### Step 5: 制定计划
  ### Step 6: 展示计划
  ### Step 7: 等待确认（终止点）
## 规则
## 禁止事项
```

**关键接口约束**：
- frontmatter `name` 与 `description` 字段必须存在
- 声明 Cursor Plan mode 要求：开头明确说明需在 Plan mode 下运行，非 Plan mode 时拒绝执行
- 唯一文件输出路径：`.cursor/specs/<slug>-plan.md`
- 禁止创建/修改/删除项目中其他文件
- Plan 模板包含：Goal、Scope、Assumptions、Files、Plan（checkboxes）、Validation、Risks

**内容来源**：基于 Kiro 侧 `.kiro/skills/quick-plan/SKILL.md`，适配 Cursor 平台差异：
- 路径从 `.kiro/specs/` 改为 `.cursor/specs/`
- 增加 Cursor Plan mode 声明与拒绝逻辑

### 2. Cursor build-plan Skill

**路径**: `.cursor/skills/build-plan/SKILL.md`

**结构**：

```markdown
---
name: build-plan
description: <触发描述>
---

# Build Plan

## 触发场景
## 执行协议
## 工作流程
  ### Step 1: 声明模式
  ### Step 2: 查找计划
  ### Step 3: 审阅计划
  ### Step 4: 执行计划
  ### Step 5: 验证
  ### Step 6: 更新计划
  ### Step 7: 汇报结果
  ### Step 8: 收敛计划文档
## 规则
## 需要重新确认的情况
## 禁止事项
```

**关键接口约束**：
- 声明 Cursor Agent mode 要求：开头明确说明需在 Agent mode 下运行，非 Agent mode 时拒绝执行
- Plan 查找路径：`.cursor/specs/<slug>-plan.md`
- 计划不存在时：告知用户并建议使用 quick-plan 先创建计划
- 计划归档路径：`changes/unreleased/specs/<slug>-plan.md`

**内容来源**：基于 Kiro 侧 `.kiro/skills/build-plan/SKILL.md`，适配 Cursor 平台差异：
- 路径从 `.kiro/specs/` 改为 `.cursor/specs/`
- 增加 Cursor Agent mode 声明与拒绝逻辑
- Step 4 执行方式改为 Cursor Agent 直接执行（Kiro 使用 sub-agent，Cursor 无此机制）

### 3. abilities.yaml 变更

**skills section 修改**：

```yaml
# 现有条目增加 cursor target
- path: quick-plan
  description: 快速计划生成（交互式确认 plan.md）
  targets:
    cursor: .cursor/skills/quick-plan/
    kiro: .kiro/skills/quick-plan/

- path: build-plan
  description: 按已确认 plan.md 执行实施
  targets:
    cursor: .cursor/skills/build-plan/
    kiro: .kiro/skills/build-plan/
```

**rules section 删除**：

```yaml
# 删除此条目
- path: plan:quick-plan-conventions
  description: Cursor 快速计划（非 Spec）的路径、命名、模板与归档
  targets:
    cursor: .cursor/rules/plan/quick-plan-conventions.mdc
```

**bootstrap.includes 修改**：

```yaml
bootstrap:
  includes:
    # ...保留其他条目...
    - skill:quick-plan       # 已存在，保留
    - skill:build-plan       # 已存在，保留
    # 删除: - rule:plan:quick-plan-conventions
```

### 4. cursor-scope.mdc 修改

移除"Plan mode / 快速计划"section 中对 `plan/quick-plan-conventions.mdc` 的引用。改为引导至新 Skill：

```markdown
## Plan mode / 快速计划

进入 **Plan mode** 时，参考 `.cursor/skills/quick-plan/`。
进入 **Build Plan** 时，参考 `.cursor/skills/build-plan/`。
计划 SSOT **仅**为 `.cursor/specs/<slug>-plan.md`。
`~/.cursor/plans/` 与 `.cursor/plans/` **禁止**作为计划来源；若 IDE 写入该处，须在同一轮对话内立即迁到 `.cursor/specs/`。
```

### 5. 版本号同步

| 文件 | 位置 | 新值 |
|------|------|------|
| `composer.json` | `version` 字段 | `0.10.0` |
| `src/Core/Application.php` | `new SymfonyApplication('apm', '...')` 第二参数 | `0.10.0` |

### 6. CHANGELOG 收敛

在 `CHANGELOG.md` 最近 unreleased 位置前新增 `## [0.10.0] - <release-date>` section，格式遵循 Keep a Changelog，包含：

- **Breaking**: 废弃 `--scope` 参数；废弃 `global-setup` 命令
- **Changed**: `bootstrap` 统一 scaffold + ability 安装；三阶段首装缩减为两阶段
- **Removed**: user scope 概念移除；`quick-plan-conventions` rule 删除
- **Added**: `quick-plan` Cursor Skill；`build-plan` Cursor Skill

### 7. Release Tag

`v0.10.0` annotated tag，在 master merge commit 上创建，message: `Release 0.10.0`。

---

## Data Models

本次无新数据模型。变更仅涉及已有 `abilities.yaml` 格式的条目增删，格式不变。

---

## Correctness Properties

### Property 1: Registry Consistency

- **Validates: Requirements 1, 2, 3, 4**
- **条件**: abilities.yaml 被解析后
- **保证**: `quick-plan` 和 `build-plan` 的 skills entry 含 cursor target（保留已有 kiro target）；旧 rule 条目不存在；bootstrap.includes 不含旧 rule 引用
- **违反后果**: `apm bootstrap` 或 `apm install` 安装错误的 ability 集合

### Property 2: Skill Installability

- **Validates: Requirements 4.3, 5.1, 6.1**
- **条件**: `apm bootstrap --target cursor` 执行时
- **保证**: `.cursor/skills/quick-plan/SKILL.md` 和 `.cursor/skills/build-plan/SKILL.md` 存在且内容非空
- **违反后果**: Agent 无法识别和激活 Skill

### Property 3: Version Consistency

- **Validates: Requirements 7.1, 7.2, 7.3**
- **条件**: release 准备完成后
- **保证**: `composer.json` version 与 `Application.php` version 字符串相同且均为 `0.10.0`
- **违反后果**: 版本报告不一致

### Property 4: Mode Enforcement

- **Validates: Requirements 5.2, 6.2**
- **条件**: Skill 被 Agent 加载时
- **保证**: quick-plan 声明 Plan mode 要求，build-plan 声明 Agent mode 要求，mode 不匹配时指令为拒绝执行
- **违反后果**: Agent 在错误模式下执行 Skill 导致非预期行为

---

## Error Handling

| 场景 | 处理 |
|------|------|
| `apm bootstrap` 时旧 rule 引用未清理 | AbilityRegistry 校验 bootstrap.includes 引用存在性，抛 `invalidBootstrapReference` |
| Skill 源目录不存在 | Installer 安装时 DirectoryMirrorService 发现源路径不存在，返回失败 |
| CHANGELOG 日期格式错误 | 人工审查，非自动校验 |

---

## Testing Strategy

| 验证方式 | 覆盖范围 |
|---------|---------|
| 现有 E2E `apm bootstrap` 测试 | 验证 bootstrap.includes 中的 Skill 安装成功（Property 2） |
| 现有 E2E `apm check` 测试 | 验证 registry 解析无错误（Property 1 部分） |
| 手动检查 | Skill 内容结构、mode 声明、CHANGELOG 格式 |
| PHPStan 静态分析 | Application.php 版本字符串类型安全 |

不新增单元测试——本次变更均为声明式文件（YAML/Markdown），无新 PHP 逻辑。

---

## Impact Analysis

| 检查项 | 影响 |
|--------|------|
| 受影响的 state 文档 | `docs/state/abilities-model.md` 无需修改（格式未变）；无新 state 文档需要 |
| 现有模块行为变化 | AbilityRegistry 解析结果变化：少一条 rule、多两条 skill cursor target。Installer 安装集合变化。无代码逻辑变更 |
| 数据模型变更 | 无 |
| 外部系统交互变化 | 无 |
| 配置项变更 | `abilities.yaml` 条目增删（见 Components section）；`cursor-scope.mdc` 引用更新 |

---

## Alternatives Considered

### Alternative 1: 保留旧 rule 作为 fallback

- **方案**：不删除 `quick-plan-conventions.mdc`，仅标记 deprecated
- **落选理由**：goal.md 已决策"直接删除，不保留 fallback"；保留会导致 Agent 同时加载 rule 和 Skill 产生冲突

### Alternative 2: Cursor Skill 增加 references 子目录

- **方案**：将 SKILL.md 拆分为入口 + references（与 spec-planning 结构相同）
- **落选理由**：RD-2 决策为"SKILL.md 不超 100 行则单文件自包含"。预估 quick-plan 约 80 行、build-plan 约 90 行，无需拆分。若实际超出再调整

---

## Architecture Decision

| # | 问题 | 选项 | 回答 |
|---|------|------|------|
| AD-1 | abilities.yaml 中 `quick-plan` 和 `build-plan` 的 targets：design 保留 `kiro` 并新增 `cursor`（双 target），但 R1-AC1 / R2-AC1 明确要求"does not include a `kiro` key"。以哪个为准？ | A) 以 design 为准——保留双 target，视为 requirements AC 表述过窄需勘误 B) 以 requirements 为准——仅保留 cursor target，移除 kiro target C) 拆分为两条独立条目：一条 kiro-only、一条 cursor-only | **A** — 保留双 target（kiro + cursor），requirements R1-AC1 / R2-AC1 需勘误 |
| AD-2 | tasks 拆分粒度：Skill 内容撰写（quick-plan SKILL.md、build-plan SKILL.md）是拆为两个独立 task 还是合并为一个"创建两个 Cursor Skill 文件"task？ | A) 两个独立 task（各自可独立验收） B) 合并为一个 task（减少切换开销、内容有共性） C) 一个 task 但拆两个 sub-step | **B** — 合并为一个 task |
| AD-3 | abilities.yaml 修改与旧 rule 删除的执行顺序：是否需要在同一 task 中完成以避免中间状态不一致？ | A) 同一 task 中完成（registry 增删 + 源文件删除） B) 分两个 task：先删除旧 rule 条目，再新增 cursor target C) 分两个 task：先新增 cursor target，再删除旧 rule 条目 | **A** — 同一 task 中完成 |
| AD-4 | CHANGELOG 和版本号同步是作为独立 task 还是合并到一个"release 收口"task？ | A) 合并为一个 release 收口 task（版本号 + CHANGELOG + tag 指令） B) 版本号同步一个 task、CHANGELOG 一个 task、tag 一个 task C) 版本号 + CHANGELOG 合并，tag 单独 | **A** — 合并为一个 release 收口 task |
