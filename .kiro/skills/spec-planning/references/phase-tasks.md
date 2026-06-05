# Tasks 阶段（产物：tasks.md）

> 本文件定义 spec planning 的第四阶段：将 design 拆解为可执行的任务列表，产出 `tasks.md`。

---

## TOC

- [触发条件](#触发条件)
- [执行步骤](#执行步骤)
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

1. **读取前置文件**：
   - i. `<spec-dir>/<name>/design.md`
   - ii. design Gatekeep Log 中已回答的 Architecture Decision
   - iii. `<spec-dir>/<name>/requirements.md`
   - iv. 知识图谱（若项目维护了持久化图谱）：查询依赖顺序、变更边界、受影响模块
2. **尝试委托系统 sub-agent**：调用 Kiro 内置的 create tasks spec 子代理（注意：不是 spec-task-execution），将 design 内容与本文件的产物格式约束作为输入，由其生成 tasks.md 初稿
3. **若委托成功** → 跳至步骤 16（Socratic Review）
4. **若委托不可用或失败** → 继续以下手动步骤：
5. **识别实现单元**：从 design 的 Components/Interfaces 中提取可独立实现的单元
6. **编排任务顺序**：根据依赖关系确定 top-level task 顺序，遵循「顶层结构约束」
7. **拆分 sub-task**：每个 top-level task 拆分为 sub-task，每个 sub-task 引用对应 Requirement
8. **编排 Test First**：每个功能 sub-task 内部按 RED → GREEN 顺序编排
9. **添加 Checkpoint**：每个 top-level task 末尾添加 checkpoint sub-task
10. **添加 E2E 测试 task**：覆盖关键用户场景
11. **添加文档收敛 task**：与 design Impact Analysis 一致
12. **添加 Code Review task**：委托给 code-reviewer sub-agent
13. **生成 Task Dependency Graph**：JSON waves 格式
14. **写入产物**：按文档结构写入 `tasks.md`
15. **诊断检查**：对写入的 `tasks.md` 执行 `getDiagnostics`，有问题则修正
16. **Socratic Review**：读取 steering `gatekeeping/gk-log-format.md` 获取格式，自检写入 `gk-logs.md`
17. **输出完成报告**：按「完成后输出」格式报告

---

## 关键约束

- 产物名统一为 `tasks.md`（历史 `plan.md` 为同义）
- **所有任务均为 mandatory，禁止标记 optional（`*` 或其他标记）**
- **Test First（RED → GREEN）编排**：每个功能 sub-task 内部先写失败测试，再写实现让测试通过。测试不拆分为独立 task
- Checkpoint 推荐作为每个 top-level task 的最后一个 sub-task，包含验证与 commit
- Checkpoint 在本 task 改变了系统事实（行为、边界、配置等）时，同步相关 `docs/state/` 文件；无变化可省略
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
| `## Notes` | 必须 — 执行注意事项，至少提及遵循 `spec-execution` 流程 |
| `## Task Dependency Graph` | 必须 — JSON waves 格式 |

### 顶层结构约束（Feature/Hotfix）

固定顺序：

| 序号 | 类型 |
|------|------|
| 1 ~ N | 自动化实现 task |
| N+1 | E2E 测试 task |
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

#### 粒度规则（强制）

**单一职责原则**：每个 sub-task 应只完成一个原子性变更，满足以下条件之一：
- 新增/修改/删除一个方法或函数
- 新增/修改/删除一个类或接口
- 修改一个文件的一组紧密耦合的改动（如构造函数参数变更 + 对应属性声明）
- 修复一个测试文件的 fixture

**拆分信号**（出现任一则应拆分）：
- sub-task 描述中出现"并且"/"同时"/"另外"
- sub-task 涉及超过 3 个不相关的方法/类
- sub-task 的 bullet 列表超过 3 项且各项独立
- sub-task 修改超过 3 个文件

**允许合并的例外**：
- 删除一组紧密耦合的废弃方法（同一类内、同一次 commit 有意义）
- Checkpoint sub-task（本身就是聚合验证）

**top-level task 数量**：当 sub-task 拆细后，top-level task 数量自然增多是正常的，不应为减少 top-level task 数量而强行合并不相关的 sub-task。

**top-level task 容量上限**：每个 top-level task 的 sub-task 数量（不含 Checkpoint）原则上不超过 8 个，硬上限 10 个。超过时应拆分为多个 top-level task。

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

验证命令须为可执行的完整 shell 命令，不得空泛（如「运行测试」「确认通过」）。**至少**列出静态分析与单元测试（具体命令见 `PROJECT.md`「构建与测试命令」）；按影响范围在 tasks.md 中追加其他验证（如 E2E、集成测试等）。

```markdown
- [ ] 1.N Checkpoint
  - 运行验证：`<具体命令>`（至少静态分析 + 单元测试）
  - （如本 task 改变了系统事实）更新 `docs/state/<相关文件>.md`
  - commit: `<改动范围>` & 符合 `git-conventions` 的 message
```

### Task Dependency Graph

#### 粒度前置原则

生成 TDG 前，必须先确保 sub-task 已按「粒度规则」拆分到原子级别。粒度越细，可并行的 sub-task 越多，TDG 的 wave 内并行度越高。

#### 并行分析原则

生成 TDG 时，必须逐对 sub-task 分析依赖关系，判断是否可并行：

**可放入同一 wave 的条件**（全部满足才可并行）：
- 不修改同一个文件
- 不存在数据依赖（一个的输出不是另一个的输入）
- 运行环境不冲突（如不同时修改同一配置、同一数据库表）
- 不存在逻辑前置关系（如接口定义必须先于实现）

**必须拆到不同 wave 的情况**（任一满足即不可并行）：
- 修改同一个文件（即使不同 section）
- 存在调用/引用关系（A 定义的接口被 B 使用）
- 共享测试 fixture 且会互相影响
- Checkpoint 必须在其所属 top-level task 的所有 sub-task 之后

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
- 同一 wave 内的 task 确实可并行（满足上述并行条件）
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
    - 运行验证：`<具体命令>`（至少静态分析 + 单元测试；见 `PROJECT.md`）
    - （如有系统事实变化）更新 `docs/state/<file>.md`
    - commit: `<改动范围>` & 符合 `git-conventions` 的 message

- [ ] N+1. E2E 测试
  - [ ] N+1.1 <场景>

- [ ] N+2. 文档收敛
  - [ ] N+2.1 更新 state 文档
  - [ ] N+2.2 Checkpoint

- [ ] Last. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 流程
- commit 随 checkpoint 一起执行

## Task Dependency Graph

（waves JSON 用 ```json 代码块包裹）
```

---

## 完成后输出

- 报告 `tasks.md` 路径（必要时注明与 `plan.md` 同义关系）
- 摘要任务编排与并行点
- 明确 spec planning 四阶段已完成（若确已完成）
