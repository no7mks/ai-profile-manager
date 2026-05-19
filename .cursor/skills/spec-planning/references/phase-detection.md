# Spec Planning 阶段判定

## 目标

在执行任何 spec 生成动作前，确定“当前唯一可执行阶段”。

## 目录与命名

- Spec 目录：`<spec-dir>/<name>/`
- `<name>` 推断（从当前分支名）：
  - `feature/foo-bar` -> `foo-bar`
  - `hotfix/0.3.1` -> `hotfix-0.3.1`
  - `release/0.4` -> `release-0.4`
- `<name>` 创建（目录不存在时）：
  - 优先从当前分支名推断
  - 若当前分支无法推断（如在 develop 上），由 agent 根据需求描述生成 kebab-case slug，向用户确认后创建

## 产物顺序（不可跳步）

### Feature 路径（默认）

1. `goal.md`
2. `requirements.md`
3. `design.md`
4. `tasks.md`（历史 `plan.md` 等价）

### Bugfix 路径

1. `goal.md`
2. `bugfix.md`（替代 `requirements.md`）
3. `design.md`
4. `tasks.md`

### 路径选择规则

- 分支前缀为 `hotfix/` 时，默认走 Bugfix 路径
- 用户在 Goal 阶段显式声明为 bugfix 时，走 Bugfix 路径
- 其余情况走 Feature 路径
- 已存在 `bugfix.md` 的 spec 目录，视为 Bugfix 路径（无论分支名）

## 判定规则

按顺序检查文件是否存在（先判定路径类型）：

**Feature 路径：**

- 不存在 `goal.md` -> 当前阶段是 Goal
- 存在 `goal.md` 且不存在 `requirements.md` -> 当前阶段是 Requirements
- 存在 `requirements.md` 且不存在 `design.md` -> 当前阶段是 Design
- 存在 `design.md` 且不存在 `tasks.md` -> 当前阶段是 Tasks
- 四个文件都存在 -> Spec Planning Done（仅允许修订，不生成新阶段）

**Bugfix 路径：**

- 不存在 `goal.md` -> 当前阶段是 Goal
- 存在 `goal.md` 且不存在 `bugfix.md` -> 当前阶段是 Bugfix Analysis
- 存在 `bugfix.md` 且不存在 `design.md` -> 当前阶段是 Design
- 存在 `design.md` 且不存在 `tasks.md` -> 当前阶段是 Tasks
- 四个文件都存在 -> Spec Planning Done（仅允许修订，不生成新阶段）

## 用户显式指定阶段时

- 与判定结果一致：直接执行
- 与判定结果不一致：先告知当前状态并请求确认，再执行

## 单步执行约束

- 本次调用只允许完成“当前阶段”一个产物
- 完成后必须停止，不得自动进入下一阶段
- 等待用户或 GK 反馈后，再进行下一步
