# Tasks 阶段（产物：tasks.md）

## 触发条件

- 当前阶段判定为 Tasks（见 `phase-detection.md`）

## 前置读取

1. `.cursor/specs/<name>/design.md`
2. design Gatekeep Log 中已回答的 Clarification
3. `.cursor/specs/<name>/requirements.md`

## 关键约束

- 产物名统一为 `tasks.md`（历史 `plan.md` 为同义）
- 所有任务默认 mandatory，不写 optional
- 推荐 Test First（RED -> GREEN）编排
- Checkpoint 作为每个 top-level task 的最后一个 sub-task，且包含验证与 commit
- 包含 `## Notes`，至少提及遵循 `spec-execution` 规则
- 文末补全 Socratic Review
- 完成后提示可运行 GK 校验 tasks

## 产物位置

- `.cursor/specs/<name>/tasks.md`

## 顶层结构约束（Feature/Hotfix）

1. 实现类 top-level tasks（1..N）
2. 手工测试 top-level task（N+1）
3. Code Review top-level task（最后一个）

## 完成后输出

- 报告 `tasks.md` 路径（必要时注明与 `plan.md` 同义关系）
- 摘要任务编排与并行点
- 明确 spec planning 四阶段已完成（若确已完成）
