---
name: spec-execution
description: 当用户要求执行 spec task、推进 tasks.md、或进入 spec 实现阶段时激活。含执行模型、质量标准、特殊任务与异常处理。
---

# Spec Execution

统一 spec task 执行规范。当用户要求执行 tasks.md 中的 task（编码、测试、code review）时激活。

## 触发场景

- 用户要求执行 spec task、推进 tasks.md
- 用户提到 checkpoint、TDD、测试分层
- 用户提到 release stabilize、alpha tag
- 进入 spec 实现阶段

## 使用原则

1. `tasks.md` 为唯一执行清单，按序逐项完成。
2. 每次开始或恢复执行前做 Pre-execution Review（drift 检测）。
3. Checkpoint 通过后才能推进下一个 top-level task。
4. Checkpoint commit 前必须同步 `docs/state/`。
5. 遇到 Blocker 立即停止并报告，不硬冲。

## Cursor 特有：执行方法

使用 **Cursor Plan Mode** 执行：

1. 读取 `tasks.md`，对当前要执行的 top-level task 进入 Plan Mode 生成执行计划
2. 用户 review 后点击 Build 执行
3. 执行完成后在 `tasks.md` 中标记 `- [x]`，commit，推进下一个 task

### Sub-agent 派发

所有 top-level task 均应派发给 **sub-agent** 执行，以控制主 agent 上下文消耗。主 agent 仅负责读取 tasks、派发 task、汇总结果、标记进度。

| 场景 | 派发方式 |
|------|---------|
| tasks.md 包含 TDG（Task Dependency Graph） | 按 wave 执行：同一 wave 内的 sub-task 并行派发，wave 间串行 |
| Plan 标注 `[Parallel: ...]` 的 task | 并行派发多个 sub-agent，全部完成后统一 review 再推进 |
| 未标注但无依赖关系的 task（不修改同一文件、无调用依赖） | 主 agent 自行判断可否并行派发 |
| 有依赖关系的 task | 串行派发：前一个 sub-agent 完成后，主 agent 验证结果，再派发下一个 |

派发时向 sub-agent 提供：当前 task 的完整描述、相关文件路径、前序 task 的关键产出（如新增的类名 / 接口签名）。避免把整个 tasks 文件传给 sub-agent。详细的上下文准备规则见 [references/execution-model.md](references/execution-model.md) 的「Sub-agent 派发上下文」section。

### Commit 粒度

以 top-level task 为 commit 粒度。谁负责 commit 取决于派发方式：

| 派发方式 | sub-agent 职责 | 主 agent 职责 |
|---------|---------------|-------------|
| 串行 | 写代码 + 标记 `tasks.md` `[x]` + commit | 验证后推进下一个 task |
| 并行 | 写代码 + 标记 `tasks.md` `[x]`（不 commit） | 全部完成后 review → 逐 task stage + commit |

### TDD 循环

每个 sub-step 遵循：写失败测试 → 验证失败 → 实现 → 验证通过。不要先写实现再补测试。

### 术语约定

- `Task 3-5` = Task 3 到 Task 5（范围），不是 Task 3 的 sub-step 5。
- 引用 sub-step 时使用 `Task 3.2`（用句点分隔）。

## 流程入口

- 执行模型：见 [references/execution-model.md](references/execution-model.md)
- 质量标准：见 [references/quality-standards.md](references/quality-standards.md)
- 特殊任务：见 [references/special-tasks.md](references/special-tasks.md)
- 异常处理：见 [references/error-handling.md](references/error-handling.md)
