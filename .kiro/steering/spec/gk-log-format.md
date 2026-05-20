---
inclusion: manual
description: 当写入或校验 gk-logs.md 时读取，定义 Socratic Review 与 Gatekeep Log 的统一格式
---

# GK Log Format

定义 `gk-logs.md` 的统一写入格式，供 spec-planning（Socratic Review 步骤）和 spec-gatekeeper（校验步骤）共同遵循。

---

## 文件位置

- `<spec-dir>/<name>/gk-logs.md`

---

## 整体结构

按阶段分 `##` section，每个阶段包含 Socratic Review 和 Gatekeep Log 两个 `###` 子 section：

```markdown
# GK Logs — <spec-name>

## <Phase> Phase — Socratic Review

**日期**: YYYY-MM-DD HH:mm

### 自检清单

| 检查项 | 结果 | 备注 |
|--------|------|------|
| ... | ✓/✗ | ... |

### 发现的问题

<问题描述，无则写"无重大问题">

### 结论

<通过/不通过，及后续建议>

---

## <Phase> Phase — Gatekeep Log

**校验时间**: YYYY-MM-DD HH:mm
**校验结果**: ✅ 通过 / ⚠️ 已修正后通过

### 修正项

（如无修正项，写"无"）

- [修正类型] 修正描述

### 合规检查

- [x/○] 检查项描述
```

---

## Phase 名称

按阶段使用以下名称：

| 阶段 | Section 名称 |
|------|-------------|
| Requirements | `Requirements Phase` |
| Bugfix | `Bugfix Phase` |
| Design | `Design Phase` |
| Tasks | `Tasks Phase` |

---

## 时间格式

- 统一使用 `YYYY-MM-DD HH:mm`（24 小时制）

---

## 修正类型

修正项使用以下类型标签：`结构`、`语体`、`内容`、`格式`、`目的`

---

## CR 不写入 gk-logs

**CR（Clarification Round）保留在源文件末尾**（requirements.md / design.md 的 `## Clarification Round` section），不写入 gk-logs.md。
