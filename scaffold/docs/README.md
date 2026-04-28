# Docs

`docs/` 用于承载项目文档分层。各子目录的定位、边界与写作约定统一在本文件维护。

---

## 分层概览

| 层 | 目录 | 回答问题 |
|---|---|---|
| state | `docs/state/` | 系统现在是什么（SSOT） |
| manual | `docs/manual/` | 如何理解/使用系统（面向人） |
| proposals | `docs/proposals/` | 为什么做（intent） |
| notes | `docs/notes/` | 想做什么（lightweight intent） |
| changes | `docs/changes/` | 改了什么（history） |

---

## `docs/state/`

### 作用

记录系统当前状态（SSOT），回答系统能力、行为规则、数据/接口约束、工程约束。

### 边界

- 应包含：架构与工程约束、接口定义、数据模型、关键规则。
- 不应包含：设计过程（spec）、需求讨论（proposal）、教程说明（manual）。

### 原则

- 必须与代码行为一致。
- feature 完成后同步更新。

### 命名建议

- 按领域拆分：`<domain>.md`（kebab-case）。

---

## `docs/manual/`

### 作用

记录使用与理解说明（面向人），回答如何使用、如何理解、常见操作方式。

### 边界

- 应包含：使用说明、示例、FAQ、必要时的简要架构说明。
- 不应定义系统规则（规则在 `state`）。

### 原则

- 必须与实际行为一致（release 前同步）。
- 可以更易读，但不能比 `state` 更“权威”。

### 命名建议

- 按主题拆分：`<topic>.md`（kebab-case）。

---

## `docs/proposals/`

### 作用

管理正式需求提案（intent），说明为什么做、解决什么问题、目标与范围、生命周期状态。

### 边界

- 应包含：用户可见行为、错误场景的用户可见结果、Goals/Non-Goals/Scope。
- 不应包含：内部实现细节、持久化格式、代码架构决策（这些进入 spec/design）。

### 生命周期

`draft` → `accepted` → `in-progress` → `implemented` → `released`（另有 `rejected`、`superseded`）。

### 分支规则

- proposal 的创建、review、状态变更默认必须在 `develop` 分支进行。
- `in-progress` 可在 feature 分支创建后标记（由 gitflow-starter 处理），这是唯一允许在非 `develop` 分支上修改 proposal 状态的场景。
- `implemented` / `released` 的标记与收敛由 finish 流程统一处理。

### 命名建议

- `PRP-001-<slug>.md` 形式递增编号。

---

## `docs/notes/`

### 作用

轻量意图暂存，记录尚未成熟为 proposal 的想法、观察与改进方向。

### 边界

- 应包含：零散想法、观察、后续可推进方向。
- 不应包含：已结构化需求（proposal）、已确认缺陷（issue）、实现计划（spec）。

### 规则

- 每条 note 一个 markdown 文件。
- 命名使用关键词，如 `feishu-batch-api.md`。
- 分支规则：可在 `develop` 或 feature 分支创建与维护；feature 分支中的 note 应在分支合并后回到 `develop` 继续管理。

### 流转规则

- note → proposal：当内容成熟并需要正式立项时，在 `develop` 分支基于 note 创建 proposal（初始状态 `draft`），并将原 note 移动到 `docs/notes/resolved/` 说明已升级。
- note → spec：当内容已明确且不需要 proposal 阶段时，直接将 note 移动到 `docs/notes/resolved/`，并在 spec 工作流中继续推进。

---

## `docs/changes/`

### 作用

记录已发生的版本级变更与 release 归档材料。

### 边界

- 只记录“已经发生的变更”。
- 不记录需求意图（proposal），不定义当前系统事实（state）。

### 结构约定

- 仓库根 `CHANGELOG.md`：版本摘要索引。
- `docs/changes/unreleased/`：未发布 feature 变更。
- `docs/changes/<version>/CHANGELOG.md`：版本详细变更。
- `docs/changes/<version>/artifacts/`：可选归档附件。

### 命名建议

- `unreleased` 条目：`<feature-name>.md`（kebab-case）。
- 版本目录：`<major>.<minor>`（如 `0.2`）。

---

## 使用原则

- 先分层，再落文：文档先放对目录，再考虑模板。
- `docs/state/` + code 是系统事实来源；其它层不要越权定义系统事实。
- 规范统一维护在 `docs/README.md`，避免多处 README 漂移。
