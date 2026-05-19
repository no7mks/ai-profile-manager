---
name: spec-execution
description: 执行 spec tasks 时的规范，含执行模型、质量标准、特殊任务与异常处理。
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

## 流程入口

- 执行模型：见 [references/execution-model.md](references/execution-model.md)
- 质量标准：见 [references/quality-standards.md](references/quality-standards.md)
- 特殊任务：见 [references/special-tasks.md](references/special-tasks.md)
- 异常处理：见 [references/error-handling.md](references/error-handling.md)
