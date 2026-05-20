---
inclusion: manual
description: Spec gatekeeper 校验 tasks 阶段的详细指引。由 spec-gatekeeper agent 在校验 tasks.md 时读取。
---

# Tasks Gatekeep 指引

本文件定义 tasks.md 的校验标准。Gatekeeper 按以下清单逐项检查，发现问题直接修正。

---

## 执行顺序

1. 机械扫描
2. 结构校验
3. Task 格式校验
4. Requirement 追溯校验
5. 依赖与排序校验
6. Graphify 跨模块依赖校验（如 `graphify_ready`）
7. Checkpoint 校验
8. Test-first 校验
9. Task 粒度校验
10. E2E 测试 Task 校验
11. Code Review Task 校验
12. 执行注意事项校验
13. Socratic Review 校验
14. 目的性审查
15. Task Dependency Graph 校验
16. 文档收敛 Task 校验
17. 将修正项写入 Gatekeep Log
18. Completion：向 main-agent 返回结果

---

## 1. 机械扫描

- [ ] 无 TBD / TODO / 待定 / 占位符
- [ ] 无空 section 或不完整的列表
- [ ] 内部引用一致（requirement 编号、design 中的模块名）
- [ ] checkbox 语法正确（`- [ ]`）
- [ ] 无 markdown 格式错误

发现问题直接修正。

---

## 2. 结构校验

一级标题必须为 `# Implementation Plan: <spec-name>`（严格匹配）。

Feature / Hotfix 的 tasks.md top-level task 遵循固定顺序：

| 序号 | 类型 |
|------|------|
| 1 ~ N | 自动化实现 task |
| N+1 | E2E 测试 task |
| N+2 | 文档收敛 task |
| 最后一个 | Code Review task |

Release spec 结构不同：task → sub-task → test item 三级嵌套。

### 检查项

- [ ] `## Overview` section 存在
- [ ] `## Tasks` section 存在
- [ ] 倒数第一个 top-level task 是 Code Review
- [ ] 倒数第二个是文档收敛（feature / hotfix）
- [ ] 倒数第三个是 E2E 测试（feature / hotfix）

---

## 3. Task 格式校验

- [ ] 所有 task 使用 `- [ ]` checkbox 语法
- [ ] top-level task 有序号，sub-task 有层级序号
- [ ] 序号连续，无跳号

---

## 4. Requirement 追溯校验

- [ ] 每个实现类 sub-task 引用了对应的 Requirement 编号（引用到 AC 是 bonus，不强制）
- [ ] requirements.md 中的每条 requirement 至少被一个 task 引用
- [ ] 引用的编号在 requirements.md 中确实存在

---

## 5. 依赖与排序校验

- [ ] top-level task 按依赖关系排序
- [ ] 无循环依赖
- [ ] 并行计划的并行条件成立

---

## 6. Graphify 跨模块依赖校验

> 仅在 `graphify_ready` 时执行。

- [ ] 已对核心模块执行 graphify 依赖查询
- [ ] task 排序与模块依赖一致
- [ ] 如发现遗漏的依赖，已调整排序或补充说明

---

## 7. Checkpoint 校验

- [ ] checkpoint 存在（推荐作为 top-level task 的最后一个 sub-task，也可作为独立 top-level task）
- [ ] 包含具体的验证命令以及 commit 动作
- [ ] 不是空泛的"确认完成"

---

## 8. Test-first 校验

推荐顺序：① 编写测试 → ② 确认失败 → ③ 编写实现 → ④ 确认通过。推荐而非强制。

---

## 9. Task 粒度校验

- [ ] 每个 sub-task 足够具体，可独立 session 执行
- [ ] 无过粗或过细的 task
- [ ] 所有 task 均为 mandatory

---

## 10. E2E 测试 Task 校验

- [ ] E2E 测试 top-level task 存在
- [ ] 覆盖 requirements 中的关键用户场景
- [ ] 场景描述具体，可执行
- [ ] 测试方式与项目类型匹配（CLI→命令执行、UI→交互模拟、库→下游消费者视角）

---

## 11. Code Review Task 校验

- [ ] Code Review 是最后一个 top-level task
- [ ] 描述为"委托给 code-reviewer sub-agent 执行"
- [ ] 不展开 review checklist

---

## 12. 执行注意事项校验

- [ ] `## Notes` section 存在
- [ ] 明确提到遵循 `spec-execution.md`
- [ ] 明确说明 commit 随 checkpoint 一起执行
- [ ] 包含当前 spec 特有的执行要点（如有）

---

## 13. Socratic Review 校验

检查 `gk-logs.md` 中是否存在 `## Tasks Phase — Socratic Review` section。如果缺少，gatekeeper 应按 `gatekeeping/gk-log-format.md` 的格式补充，Q&A 至少覆盖：

- tasks 是否完整覆盖了 design 中的所有实现项？
- task 之间的依赖顺序是否正确？
- 每个 task 的粒度是否合适？
- checkpoint 是否覆盖了关键阶段？
- 并行标注是否满足并行条件？
- E2E 测试是否覆盖了关键用户场景？

---

## 14. 目的性审查

Tasks 的核心目的是：**提供一份可直接执行的实现计划，让执行者无需回溯 design 即可逐条完成所有实现工作**。

### 审查清单

- [ ] **Design CR 回应**：用户在 design GK CR 中的决策是否在 tasks 编排中体现。
- [ ] **Design 全覆盖**：tasks 是否覆盖了 design 中的所有模块、接口和实现项？
- [ ] **可独立执行**：每个 sub-task 的描述是否足够自包含？
- [ ] **验收闭环**：checkpoint + E2E 测试 + 文档收敛 + code review 是否构成完整验收闭环？
- [ ] **执行路径无歧义**：排序和依赖关系是否清晰？

如果不达标，直接修正。修正后在 Gatekeep Log 中记录（修正类型为 `目的`）。

---

## 15. Task Dependency Graph 校验

tasks.md 必须包含 `## Task Dependency Graph` section。

### 格式要求

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2"] },
  { "id": 1, "tasks": ["2.1"] }
]}
```

- [ ] TDG section 存在，使用 `{"waves": [...]}` JSON 格式
- [ ] waves JSON 使用 ` ```json ``` ` 代码块包裹（硬约束）
- [ ] TDG 中的 task ID 与 sub-task 编号一致
- [ ] wave 顺序反映正确的依赖关系
- [ ] 同一 wave 内的 task 确实可并行
- [ ] 所有 leaf sub-task 都出现在 TDG 中

---

## 16. 文档收敛 Task 校验

Feature / Hotfix spec 必须包含文档收敛 top-level task。

- [ ] 文档收敛 top-level task 存在
- [ ] 包含 state 文档更新 sub-task
- [ ] 包含 manual 文档更新 sub-task
- [ ] 包含 migration guide sub-task
- [ ] 包含 checkpoint sub-task
- [ ] 文档收敛内容与 design.md 的 Impact Analysis 一致

---

## 17. Gatekeep Log

将校验过程中的修正项写入 `<spec-dir>/<name>/gk-logs.md` 的 `## Tasks Gatekeep Log` section（不写在 tasks.md 中）。

---

## 18. Completion

向 main-agent 返回：

1. **校验结果摘要**：通过 / 已修正后通过，列出修正项
2. **下一步建议**：tasks 校验完成后，spec 三阶段校验全部结束，可以开始执行 task
