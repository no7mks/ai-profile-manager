---
inclusion: auto
description: Spec planning 兼容入口规则。详细流程迁移到 spec-planning skill；本规则仅保留重定向与关键一致性约束。
---

# Spec Planning 兼容入口

## 统一入口

详细规范统一由以下 skill 承载：

- `abilities/skills/spec-planning/SKILL.md`
- `abilities/skills/spec-planning/references/phase-detection.md`
- `abilities/skills/spec-planning/references/goal.md`
- `abilities/skills/spec-planning/references/requirements.md`
- `abilities/skills/spec-planning/references/design.md`
- `abilities/skills/spec-planning/references/tasks.md`

## 兼容约束（保留）

1. 产物顺序固定：`goal.md -> requirements.md -> design.md -> tasks.md`。
2. 一次只做一步，不得跨阶段连续生成。
3. 若用户提到 `plan.md`，按 `tasks.md` 同义处理并显式说明命名映射。
4. 阶段完成后必须停下，等待用户或 GK 反馈。

## 说明

此规则用于兼容旧触发点，避免重复维护“双份真相”。新增或调整细节时，优先修改 skill references。
