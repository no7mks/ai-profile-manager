# Docs

`docs/` 用于承载项目文档分层。各子目录的定位与边界统一在本文件维护。

---

## 分层概览

| 层 | 目录 | 回答问题 |
|---|---|---|
| state | `docs/state/` | 系统现在是什么（SSOT） |
| manual | `docs/manual/` | 如何使用系统（面向人） |
| proposals | `docs/proposals/` | 为什么做（intent） |
| notes | `docs/notes/` | 想做什么（lightweight intent） |
| changes | `docs/changes/` | 改了什么（history + 归档） |

---

## `docs/state/`

### 作用

记录系统当前状态（SSOT），回答"产品交付的是什么"——直到每一个细节。

### 粒度要求

- 每个 proposal 的需求落地后，其用户可见行为、边界条件、错误处理必须在 state 中有对应描述。
- 每个设计决策（数据模型、接口契约、算法策略、配置格式等）必须在 state 中记录。
- state 的描述粒度应足以作为 functional test 和 integration test 的用例来源。

### 边界

- 应包含：架构与工程约束、接口定义、数据模型、功能行为规则、边界条件与错误场景、配置格式与默认值、设计决策及其理由。
- 不应包含：设计过程（spec）、需求讨论（proposal）、教程说明（manual）。

### 命名

按领域拆分：`<domain>.md`（kebab-case）。内容过长时可拆为子目录。

---

## `docs/manual/`

### 作用

记录使用与理解说明（面向人），回答如何使用、常见操作方式。

### 边界

- 应包含：使用说明、示例、FAQ。
- 不应定义系统规则（规则在 state）。

### 命名

按主题拆分：`<topic>.md`（kebab-case）。

---

## `docs/proposals/`

### 作用

管理正式需求提案（intent），说明为什么做、解决什么问题、目标与范围。

### 生命周期

`draft` → `accepted` → `in-progress` → `implemented` → `released`（另有 `rejected`、`superseded`）。

### 分支规则

- 创建、review、状态变更默认在 `develop` 分支。
- `in-progress` 可在 feature 分支标记（gitflow start 流程处理）。
- `implemented` / `released` 由 finish 流程统一处理。

### 命名

`PRP-<NNN>-<slug>.md` 递增编号。

---

## `docs/notes/`

### 作用

轻量意图暂存，记录尚未成熟为 proposal 的想法、观察与改进方向。

### 边界

- 应包含：零散想法、观察、后续可推进方向。
- 不应包含：已结构化需求（proposal）、已确认缺陷（issue）、实现计划（spec）。

### 流转

- note → proposal：基于 note 创建 proposal 后，原 note 归档。
- note → 直接解决：note 描述的内容已落地后，归档。

### 命名

每条 note 一个文件，关键词命名：`<keyword>.md`（kebab-case）。

---

## `docs/changes/`

### 作用

归档已完成使命的文档，按版本组织历史。

### 结构

```
docs/changes/
├── unreleased/          # 当前开发周期已归档但未发布
│   ├── notes/           # 已解决的 note
│   ├── proposals/       # 已 implemented 的 proposal
│   └── fixed/           # 已关闭的 issue
└── <version>/           # Release 后的版本归档
    ├── notes/
    ├── proposals/
    └── fixed/
```

### 归档入口

| 来源 | 触发时机 | 归档目标 |
|------|---------|---------|
| `docs/notes/<name>.md` | note 已解决 | `docs/changes/unreleased/notes/` |
| `docs/proposals/<name>.md` | proposal status → implemented | `docs/changes/unreleased/proposals/` |
| `issues/<name>.md` | issue closed | `docs/changes/unreleased/fixed/` |

### Release 归档

Release Finish 时将 `docs/changes/unreleased/` rename 为 `docs/changes/<version>/`，然后重建空的 unreleased 子目录。

### 与根 CHANGELOG.md 的关系

- 根 `CHANGELOG.md`：版本摘要索引，面向用户的变更概述。
- `docs/changes/<version>/`：该版本归档的完整文档原文。
