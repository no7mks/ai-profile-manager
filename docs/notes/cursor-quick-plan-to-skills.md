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

## 待定事项

- plan.md 归档路径是否统一为 `changes/unreleased/specs/`（与 Kiro 侧一致）。

---

## 已关闭决策

| 项 | 决策 |
|----|------|
| mode 不匹配时的行为 | Skill 检测到运行在错误 mode 下时直接报错退出，不尝试降级或继续 |
| old rule 处理 | 直接删除 `quick-plan-conventions.mdc`，不保留 fallback |
