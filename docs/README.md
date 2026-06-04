# Docs

`docs/` 用于承载项目文档分层。各子目录的定位与边界统一在本文件维护。

---

## 分层概览

| 层 | 目录 | 定位 |
|---|---|---|
| state | `docs/state/` | 系统当前状态（SSOT） |
| manual | `docs/manual/` | 使用说明（面向人） |
| proposals | `docs/proposals/` | 正式需求提案（scope + 目标 + 生命周期） |
| notes | `docs/notes/` | 轻量暂存（观察、想法、改进方向） |

归档目录已独立为根目录级 `changes/`，详见 `changes/README.md`。

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

管理正式需求提案：定义做什么、范围、目标、约束，并跟踪生命周期状态。

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

轻量暂存，记录尚未成熟为 proposal 的观察、想法与改进方向。

### 边界

- 应包含：零散想法、观察、后续可推进方向。
- 不应包含：已结构化需求（proposal）、已确认缺陷（issue）、实现计划（spec）。

### 去向

- 升级为 proposal → 归档原 note。
- 内容直接落地 → 归档原 note。
- 归档操作见 doc-convergence steering。

### 命名

每条 note 一个文件，关键词命名：`<keyword>.md`（kebab-case）。

---

## 初始化约定

新项目在业务仓库执行 `apm bootstrap`（或 `/apm init` 触发的 bootstrap）后，各子目录应以 `.gitkeep` 占位确保目录结构存在：

- `docs/state/.gitkeep`
- `docs/manual/.gitkeep`
- `docs/notes/.gitkeep`
- `docs/proposals/.gitkeep`

目录内有实际文件后，对应 `.gitkeep` 可移除。
