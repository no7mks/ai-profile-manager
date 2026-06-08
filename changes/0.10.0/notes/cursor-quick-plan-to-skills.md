# Cursor Quick Plan Rule 拆分为两个 Skill

记录一个改进想法：将 Cursor 侧的 `quick-plan-conventions.mdc` rule 拆分为两个独立 Skill。

---

## 背景

当前 Cursor 侧使用一个 rule（`.cursor/rules/plan/quick-plan-conventions.mdc`）同时覆盖规划和执行两个阶段。实际体验不佳——rule 的触发时机不够精准，且两阶段职责混在一份文件中，Agent 容易混淆上下文。

Kiro 侧已拆为 `quick-plan` 和 `build-plan` 两个 Skill，体验明显更好。

---

## 方案

在 `.cursor/skills/` 下新建两个 Skill，对标 Kiro 侧：

| Skill | 对应 Kiro Skill | Cursor 运行模式 | 职责 |
|-------|----------------|----------------|------|
| `quick-plan` | `.kiro/skills/quick-plan/` | **Plan mode** | 收集需求、制定 plan.md、等待确认 |
| `build-plan` | `.kiro/skills/build-plan/` | **Agent mode** | 读取已确认的 plan.md、执行实施、更新进度 |

### 关键约束

- `quick-plan` 必须在 Cursor 的 **Plan mode** 下运行——只产出 plan.md，不修改任何项目文件。
- `build-plan` 必须在 Cursor 的 **Agent mode** 下运行——拥有完整文件读写和命令执行能力。
- 两者共享 plan.md 路径约定：`.cursor/specs/<slug>-plan.md`。

### 与现有 rule 的关系

拆分完成后，删除 `quick-plan-conventions.mdc`。

---

## 依赖

- **PRP-003**（Deprecate Scope）：PRP-003 将 `global-setup` section 改名为 `bootstrap`。本 note 落地时需修改 `bootstrap.includes`，因此须在 PRP-003 实现之后执行。

---

## `abilities.yaml` 变更

| 操作 | 对象 |
|------|------|
| 删除 | `rules` section 中 `path: plan:quick-plan-conventions` 条目 |
| 新增 | `skills` section 中 `path: quick-plan`，targets 含 `cursor: .cursor/skills/quick-plan/` |
| 新增 | `skills` section 中 `path: build-plan`，targets 含 `cursor: .cursor/skills/build-plan/` |
| 修改 | `bootstrap.includes`：移除 `rule:plan:quick-plan-conventions`，添加 `skill:quick-plan` 和 `skill:build-plan` |

---

## 待定事项

- plan.md 归档由 gitflow finish 统一处理（与 Kiro 侧行为一致），不单独设计归档路径。

---

## 已关闭决策

| 项 | 决策 |
|----|------|
| mode 不匹配时的行为 | Skill 在 SKILL.md 开头声明所需 mode；Agent 识别当前 mode 不匹配时拒绝执行 |
| old rule 处理 | 直接删除 `quick-plan-conventions.mdc`，不保留 fallback |
