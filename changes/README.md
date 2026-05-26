# Changes

归档已完成使命的文档，按版本组织历史。根目录级目录。

---

## 结构

```
changes/
├── README.md
├── unreleased/              # 当前开发周期已归档但未发布
│   ├── notes/               # 已解决的 note
│   ├── proposals/           # 已 implemented 的 proposal
│   └── specs/               # 已完成的 spec
└── <version>/               # Release 后的版本归档
    ├── CHANGELOG.md          # 该版本详细变更日志（含 fixed issue 编号）
    ├── notes/
    ├── proposals/
    └── specs/
```

---

## 归档入口

| 来源 | 触发时机 | 归档目标 |
|------|---------|---------|
| `docs/notes/<name>.md` | note 已解决 | `changes/unreleased/notes/` |
| `docs/proposals/<name>.md` | proposal status → implemented | `changes/unreleased/proposals/` |
| `.kiro/specs/<name>/` | spec 对应功能完成（gitflow finish） | `changes/unreleased/specs/` |

注意：issue 不归档到 `changes/`，统一在 `issues/fixed/` 管理。

---

## Release 归档

Release finish 时：

1. 将 `changes/unreleased/` rename 为 `changes/<version>/`
2. 在 `changes/<version>/CHANGELOG.md` 中记录本版本修复的 issue 编号
3. 重建空的 `changes/unreleased/{notes,proposals,specs}/`（含 `.gitkeep`）
4. 根 `CHANGELOG.md` 中将 `[Unreleased]` 内容归入新版本小节

---

## 版本 CHANGELOG 与根 CHANGELOG 的关系

| 文件 | 定位 |
|------|------|
| 根 `CHANGELOG.md` | 面向用户的版本摘要 |
| `changes/<version>/CHANGELOG.md` | 面向开发者的详细变更，含 issue 编号、spec 引用 |

---

## 初始化

新项目初始化后，以 `.gitkeep` 占位：

- `changes/unreleased/notes/.gitkeep`
- `changes/unreleased/proposals/.gitkeep`
- `changes/unreleased/specs/.gitkeep`
