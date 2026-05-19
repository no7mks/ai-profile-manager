# Spec Planning 阶段判定

## 目标

在执行任何 spec 生成动作前，确定"当前唯一可执行阶段"。

## 强制自动执行（最高优先级）

Skill 激活后，agent **必须立即自主执行以下步骤**，不得先向用户提问：

1. 运行 `git branch --show-current` 获取当前分支名
2. 按下方"目录与命名"规则推断 `<name>`
3. 检查 `<spec-dir>/<name>/` 目录是否存在及其中已有文件
4. 按"判定规则"确定当前阶段
5. 将判定结果（spec name、当前阶段、已有产物）简短报告给用户，然后直接执行该阶段

**禁止行为**：在完成上述判定前，不得向用户询问"你想做什么""针对哪个 feature""是新 spec 还是已有 spec"等问题。

**唯一允许提问的情况**：步骤 2 无法推断 name（分支为 develop/main/master 等）**且** `<spec-dir>` 下不存在唯一活跃目录时，才可向用户询问 spec name。

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
- 与判定结果不一致：先简短告知当前状态差异并请求确认，再执行
- 用户输入中的阶段关键词映射：`goal` → Goal、`req`/`requirements` → Requirements、`design` → Design、`tasks`/`plan` → Tasks、`bugfix` → Bugfix Analysis

## 单步执行约束

- 本次调用只允许完成"当前阶段"一个产物
- 完成后必须停止，不得自动进入下一阶段
- 等待用户或 GK 反馈后，再进行下一步
