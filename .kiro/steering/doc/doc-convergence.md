---
inclusion: manual
description: 文档归档操作规范——notes/proposals/specs/issues 的归档判定与操作
---

# Doc Convergence

文档归档的操作规范。定义何时、如何将已完成使命的文档归档。

---

## 总则

- 归档操作使用 `mv`（git 视为 rename），不使用 delete + create。
- 归档时可更新文件内容，标注解决状态和关键实现引用。

---

## 归档 Notes

### 判定标准

逐个检查 `docs/notes/` 下的文件：

1. **已解决**：note 描述的问题/想法已落地。
2. **部分解决**：部分内容已落地，部分仍 open——更新 note 内容标注已解决项，保留原位。
3. **未解决**：内容仍未推进——保留原位不动。

### 操作

已解决的 note：`mv docs/notes/<name>.md → changes/unreleased/notes/<name>.md`

---

## 归档 Proposals

- 将 proposal status 更新为 `implemented`。
- `mv docs/proposals/<name>.md → changes/unreleased/proposals/<name>.md`

---

## 归档 Specs

- 由 gitflow finish 流程触发。
- `mv .kiro/specs/<name>/ → changes/unreleased/specs/<name>/`

---

## 归档 Issues

- 将 issue status 更新为 `closed`，填写 `Fixed In`。
- issue 文件留在 `issues/` 原位（status=closed 表示已修复未发布）。
- release/hotfix finish 时，review tag 后 `mv issues/<name>.md → issues/fixed/<name>.md`。

---

## 更新 CHANGELOG

更新时机由各触发流程（gitflow finish-flow 等）保障，本章节只定义格式规范。

需更新：

- `changes/unreleased/CHANGELOG.md`（详细，含 issue 编号、spec 引用）

根 `CHANGELOG.md` 不维护 `[Unreleased]` 段落，release/hotfix finish 时从 `changes/unreleased/CHANGELOG.md` 提炼摘要写入新版本小节。

### 覆盖维度

| 分类 | 判定依据 |
|------|---------|
| Breaking | 不兼容变更：路径/格式/接口/行为变更导致旧用法失效 |
| Removed | 功能/命令/文件被移除 |
| Added | 新功能、新命令、新文档 |
| Changed | 行为调整、重构（不破坏兼容性） |
| Fixed | Bug 修复 |

### 原则

- 面向用户描述，不写内部实现细节。
- 每条变更一句话，必要时附简短说明。
- 不遗漏 Breaking change。

---

## 确认 State / Manual 一致性

1. **State**：对照本次变更涉及的需求，确认每个已实现需求在 `docs/state/` 中有对应行为描述。
2. **Manual**：确认 `docs/manual/` 中的使用说明与实际行为一致。

### 常见遗漏

- 新增功能未写入 manual。
- 移除功能未从 manual 删除。
- State 中残留已废弃模块的描述。

---

## Release 归档

1. 将 `changes/unreleased/` rename 为 `changes/<version>/`。
2. 从 `changes/<version>/CHANGELOG.md` 提炼摘要写入根 `CHANGELOG.md` 新版本小节。
3. 重建空的 `changes/unreleased/{notes,proposals,specs}/` 及空 `CHANGELOG.md`。
