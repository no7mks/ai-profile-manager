# Spec Planning 阶段判定

## 目标

在执行任何 spec 生成动作前，确定“当前唯一可执行阶段”。

## 目录与命名

- Spec 目录：`.cursor/specs/<name>/`
- `<name>` 推断：
  - `feature/foo-bar` -> `foo-bar`
  - `hotfix/0.3.1` -> `hotfix-0.3.1`
  - `release/0.4` -> `release-0.4`

## 产物顺序（不可跳步）

1. `goal.md`
2. `requirements.md`
3. `design.md`
4. `tasks.md`（历史 `plan.md` 等价）

## 判定规则

按顺序检查文件是否存在：

- 不存在 `goal.md` -> 当前阶段是 Goal
- 存在 `goal.md` 且不存在 `requirements.md` -> 当前阶段是 Requirements
- 存在 `requirements.md` 且不存在 `design.md` -> 当前阶段是 Design
- 存在 `design.md` 且不存在 `tasks.md` -> 当前阶段是 Tasks
- 四个文件都存在 -> Spec Planning Done（仅允许修订，不生成新阶段）

## 用户显式指定阶段时

- 与判定结果一致：直接执行
- 与判定结果不一致：先告知当前状态并请求确认，再执行

## 单步执行约束

- 本次调用只允许完成“当前阶段”一个产物
- 完成后必须停止，不得自动进入下一阶段
- 等待用户或 GK 反馈后，再进行下一步
