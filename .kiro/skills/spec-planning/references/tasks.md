# Tasks 阶段（产物：tasks.md）

## 触发条件

- 当前阶段判定为 Tasks（见 `phase-detection.md`）

## 前置读取

1. `<spec-dir>/<name>/design.md`
2. design Gatekeep Log 中已回答的 Clarification
3. `<spec-dir>/<name>/requirements.md`

## 关键约束

- 产物名统一为 `tasks.md`（历史 `plan.md` 为同义）
- **所有任务均为 mandatory，禁止标记 optional（`*` 或其他标记）**
- **Test First（RED → GREEN）编排**：每个功能 sub-task 内部先写失败测试，再写实现让测试通过。测试不拆分为独立 task。
- Checkpoint 推荐作为每个 top-level task 的最后一个 sub-task（也可作为独立 top-level task），包含验证与 commit
- Checkpoint 必须包含 `docs/state/` 同步：将本阶段实现的行为、规则、边界条件更新到对应的 state 文件
- 包含 `## Notes` section
- 文末补全 `## Socratic Review`（写入 `<spec-dir>/<name>/gk-logs.md`，不写在 tasks.md 中）
- 完成后提示可运行 GK 校验 tasks

## 产物位置

- `<spec-dir>/<name>/tasks.md`

## 文档 Section 结构

一级标题不约束格式。必须包含以下 section（英文名）：

| Section | 必要性 |
|---------|--------|
| `## Overview` | 必须 — 概述执行策略和关键决策 |
| `## Tasks` | 必须 — 任务列表 |
| `## Notes` | 必须 — 执行注意事项，至少提及遵循 `spec-execution` 规则 |
| `## Task Dependency Graph` | 必须 — JSON waves 格式 |

## 顶层结构约束（Feature/Hotfix）

固定顺序：

| 序号 | 类型 |
|------|------|
| 1 ~ N | 自动化实现 task |
| N+1 | 手工测试 task |
| N+2 | 文档收敛 task |
| 最后一个 | Code Review task |

### 文档收敛 task 要求

- 包含 state 文档更新 sub-task
- 包含 manual 文档更新 sub-task（如适用）
- 包含 migration guide sub-task（如适用）
- 包含 checkpoint sub-task
- 内容与 design.md 的 Impact Analysis 一致

### Code Review task 要求

- 描述为"委托给 code-reviewer sub-agent 执行"
- 不展开 review checklist

## Sub-task 格式要求

### Requirement 追溯

每个实现类 sub-task 必须引用对应的 Requirement 编号。引用到 AC 是 bonus，不强制。格式灵活，以下均可：

```markdown
- [ ] 1.1 实现 XXX
  - _Ref: Requirement 2, AC 1-3_

- [ ] 1.2 实现 YYY
  - _Requirements: 3.1, 3.2_
```

### Checkpoint 格式

```markdown
- [ ] 1.N Checkpoint
  - 运行 `<具体验证命令>`
  - 更新 `docs/state/<相关文件>.md`
  - commit: `<scope>: <描述>`
```

## Task Dependency Graph

`## Task Dependency Graph` section 格式：

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2"] },
  { "id": 1, "tasks": ["2.1"] }
]}
```

- task ID 与 sub-task 编号一致
- wave 顺序反映正确的依赖关系
- 同一 wave 内的 task 确实可并行
- 所有 leaf sub-task 都出现在 TDG 中

## Test First 编排规则

每个功能 sub-task 的内部步骤：

1. 编写测试（覆盖该 sub-task 对应的 AC）
2. 确认测试失败（RED）
3. 编写实现代码
4. 确认测试通过（GREEN）

**禁止**将测试拆分为独立的 sub-task。测试与实现在同一个 sub-task 内完成。

例外：
- 纯删除类 task 不需要 test-first
- 文档类 task 不需要 test-first
- 属性测试可以作为独立的 top-level task，但仍然是 mandatory

## Skeleton 模板

```markdown
# <自由标题>

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

## Socratic Review

<自问自答>

## Task Dependency Graph

{"waves": [...]}
```

## 完成后输出

- 报告 `tasks.md` 路径（必要时注明与 `plan.md` 同义关系）
- 摘要任务编排与并行点
- 明确 spec planning 四阶段已完成（若确已完成）
