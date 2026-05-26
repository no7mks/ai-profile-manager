# Doc Convergence

Feature/Release/Hotfix Finish 时的文档收敛操作细则。

---

## 总则

- 文档收敛是 finish 流程的必要步骤，不可跳过。
- 收敛完成后统一提交，commit scope 为 `doc`。
- 归档操作使用 `mv`（git 视为 rename），不使用 delete + create。

---

## Notes 处理

### 判定标准

逐个检查 `docs/notes/` 下的文件：

1. **已解决**：note 描述的问题/想法已在本次 feature 中实现或被 proposal 覆盖。
2. **部分解决**：部分内容已落地，部分仍 open——更新 note 内容标注已解决项，保留原位。
3. **未解决**：与本次 feature 无关或仍未推进——保留原位不动。

### 操作

- 已解决的 note：`mv docs/notes/<name>.md → docs/changes/unreleased/notes/<name>.md`
- 归档时可更新内容，标注解决状态和关键实现引用。

---

## Proposals 处理

### Feature Finish

- 将 proposal status 更新为 `implemented`。
- `mv docs/proposals/<name>.md → docs/changes/unreleased/proposals/<name>.md`

### Release Finish

- 将 unreleased 中的 proposal status 更新为 `released`（如需）。
- `docs/changes/unreleased/` 整体 rename 为 `docs/changes/<version>/`。

---

## Issues 处理（Hotfix/Bugfix Finish）

- 将 issue status 更新为 `closed`，填写 `Fixed In` 版本。
- `mv issues/<name>.md → docs/changes/unreleased/fixed/<name>.md`

---

## CHANGELOG 更新

### 覆盖维度

检查 feature 分支的 commit 历史，按以下分类整理 `[Unreleased]` 条目：

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

## State / Manual 一致性

### 检查方法

1. **State**：对照本次 feature 的 spec requirements，确认每个已实现需求在 `docs/state/` 中有对应行为描述。
2. **Manual**：确认 `docs/manual/` 中的使用说明与实际 CLI 行为一致（命令签名、参数、示例输出）。

### 常见遗漏

- 新增命令/选项未写入 manual。
- 移除功能未从 manual 删除。
- State 中残留已废弃模块的描述。

---

## Release Finish 额外步骤

1. 将 `docs/changes/unreleased/` rename 为 `docs/changes/<version>/`。
2. 重建空的 `docs/changes/unreleased/{notes,proposals,fixed}/`（含 `.gitkeep`）。
3. 根 `CHANGELOG.md` 中将 `[Unreleased]` 内容归入新版本小节并标注日期。
