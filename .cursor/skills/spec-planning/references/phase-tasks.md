# Tasks 阶段（产物：tasks.md）

> 本文件定义 spec planning 的第四阶段：将 design 拆解为可执行的任务列表，产出 `tasks.md`。

---

## TOC

- [触发条件](#触发条件)
- [执行步骤](#执行步骤)
- [前置读取](#前置读取)
- [关键约束](#关键约束)
- [产物格式](#产物格式)
  - [文档 Section 结构](#文档-section-结构)
  - [顶层结构约束](#顶层结构约束featurehotfix)
  - [Sub-task 格式要求](#sub-task-格式要求)
  - [Task Dependency Graph](#task-dependency-graph)
  - [Test First 编排规则](#test-first-编排规则)
  - [Skeleton 模板](#skeleton-模板)
- [完成后输出](#完成后输出)

---

## 触发条件

- 当前阶段判定为 Tasks（见 `phase-detection.md`）

---

## 执行步骤

1. **读取前置文件**：按「前置读取」清单获取 design、GK Clarification、requirements
2. **识别实现单元**：从 design 的 Components/Interfaces 中提取可独立实现的单元
3. **编排任务顺序**：根据依赖关系确定 top-level task 顺序，遵循「顶层结构约束」
4. **拆分 sub-task**：每个 top-level task 拆分为 sub-task，每个 sub-task 引用对应 Requirement
5. **编排 Test First**：每个功能 sub-task 内部按 RED → GREEN 顺序编排
6. **添加 Checkpoint**：每个 top-level task 末尾添加 checkpoint sub-task
7. **添加手工测试 task**：覆盖关键用户场景
8. **添加文档收敛 task**：与 design Impact Analysis 一致
9. **添加 Code Review task**：委托给 code-reviewer sub-agent
10. **生成 Task Dependency Graph**：JSON waves 格式
11. **写入产物**：按文档结构写入 `tasks.md`
12. **Socratic Review**：读取 rule `gatekeeping/gk-log-format.mdc` 获取格式，自检写入 `gk-logs.md`
13. **输出完成报告**：按「完成后输出」格式报告

---

## 前置读取

1. `<spec-dir>/<name>/design.md`
2. design Gatekeep Log 中已回答的 Clarification
3. `<spec-dir>/<name>/requirements.md`

---

## 关键约束

- 产物名统一为 `tasks.md`（历史 `plan.md` 为同义）
- **所有任务均为 mandatory，禁止标记 optional（`*` 或其他标记）**
- **Test First（RED → GREEN）编排**：每个功能 sub-task 内部先写失败测试，再写实现让测试通过。测试不拆分为独立 task
- Checkpoint 推荐作为每个 top-level task 的最后一个 sub-task，包含验证与 commit
- Checkpoint 必须包含 `docs/state/` 同步
- Socratic Review 和 Gatekeep Log 写入 `<spec-dir>/<name>/gk-logs.md`，不写在 tasks.md 中
- 完成后提示可运行 GK 校验 tasks

---

## 产物格式

### 产物位置

- `<spec-dir>/<name>/tasks.md`

### 文档 Section 结构

一级标题必须为 `# Implementation Plan: <spec-name>`（严格匹配）。必须包含以下 section：

| Section | 必要性 |
|---------|--------|
| `## Overview` | 必须 — 概述执行策略和关键决策 |
| `## Tasks` | 必须 — 任务列表 |
| `## Notes` | 必须 — 执行注意事项，至少提及遵循 `spec-execution` 规则 |
| `## Task Dependency Graph` | 必须 — JSON waves 格式 |

### 顶层结构约束（Feature/Hotfix）

固定顺序：

| 序号 | 类型 |
|------|------|
| 1 ~ N | 自动化实现 task |
| N+1 | 手工测试 task |
| N+2 | 文档收敛 task |
| 最后一个 | Code Review task |

#### 文档收敛 task 要求

- 包含 state 文档更新 sub-task（与 design Impact Analysis 一致）
- 包含 manual 文档更新 sub-task（如适用）
- 包含 migration guide sub-task（如适用）
- 包含 checkpoint sub-task

#### Code Review task 要求

- 描述为"委托给 code-reviewer sub-agent 执行"
- 不展开 review checklist

### Sub-task 格式要求

#### Requirement 追溯（强制）

每个实现类 sub-task 必须引用对应的 Requirement 编号。格式灵活：

```markdown
- [ ] 1.1 实现 XXX
  - 具体描述...
  - _Ref: Requirement 2, AC 1-3_

- [ ] 1.2 实现 YYY
  - 具体描述...
  - _Requirements: 3.1, 3.2_
```

引用规则：
- requirements.md 中的每条 requirement 至少被一个 task 引用
- 引用的编号在 requirements.md 中确实存在

#### Checkpoint 格式（强制）

```markdown
- [ ] 1.N Checkpoint
  - 运行 `<具体验证命令>`
  - 更新 `docs/state/<相关文件>.md`
  - commit: `<scope>: <描述>`
```

### Task Dependency Graph

#### 格式

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2"] },
  { "id": 1, "tasks": ["2.1"] }
]}
```

#### 校验规则

- task ID 与 sub-task 编号一致
- wave 顺序反映正确的依赖关系
- 同一 wave 内的 task 确实可并行（无数据依赖）
- 所有 leaf sub-task 都必须出现在 TDG 中
- waves JSON 必须用 ` ```json ``` ` 代码块包裹（硬约束）

### Test First 编排规则

每个功能 sub-task 的内部步骤：

1. 编写测试（覆盖该 sub-task 对应的 AC）
2. 确认测试失败（RED）
3. 编写实现代码
4. 确认测试通过（GREEN）

**禁止**：
- ❌ 将测试拆分为独立的 sub-task
- ❌ 将测试标记为 optional
- ❌ 先写实现再补测试

**例外**：
- 纯删除类 task 不需要 test-first
- 文档类 task 不需要 test-first
- 属性测试可以作为独立的 top-level task，但仍然是 mandatory


### Skeleton 模板

```markdown
# Implementation Plan: <spec-name>

## Overview

<执行策略概述，关键决策引用>

## Tasks

- [ ] 1. <Top-level task>
  - [ ] 1.1 <Sub-task>
    - <描述>
    - _Ref: Requirement N_
  - [ ] 1.2 Checkpoint
    - 运行 `<验证命令>`
    - 更新 `docs/state/<file>.md`
    - commit: `<scope>: <描述>`

- [ ] N+1. 手工测试
  - [ ] N+1.1 <场景>

- [ ] N+2. 文档收敛
  - [ ] N+2.1 更新 state 文档
  - [ ] N+2.2 Checkpoint

- [ ] Last. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 规则
- commit 随 checkpoint 一起执行

## Task Dependency Graph

（waves JSON 用 ```json 代码块包裹）
```

---

## 完成后输出

- 报告 `tasks.md` 路径（必要时注明与 `plan.md` 同义关系）
- 摘要任务编排与并行点
- 明确 spec planning 四阶段已完成（若确已完成）
