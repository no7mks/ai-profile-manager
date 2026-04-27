# ai-profile-manager 设计说明

## 概览

`aipm` 是一个 PHP CLI 工具，用于管理 AI profile item（`skill`、`rule`、`agent`），并支持跨仓库的 Capture feedback 回流。

## Namespace 分层

### `AiProfileManager\Core`

- `Application`：CLI composition root。
- 负责创建并组装 service，再注册 Symfony Console command。

### `AiProfileManager\Config`

- `AppConfig`：静态 runtime configuration 与默认集合。
- 定义 known preset、known target，以及 preset 到 item 的映射。

### `AiProfileManager\Service`

- `Installer`：安装流程（当前仍是按 target 的 placeholder 行为）。
- `CheckService`：状态评估与结果渲染模型。
- `CaptureService`：capture 结果组装与 event payload 生成。
- `KnowledgeBaseUpdater`：写入本地 knowledge snapshot。

### `AiProfileManager\Capture`

- `CaptureEventSchema`：校验 `CaptureEvent v1`。
- `CaptureEventIngestor`：执行 validate/ingest/dedupe，并处理 events 批量事件。
- write-back 组件：将 event 写回 `aipm-repo` 文件。

## Command 面

`src/Command` 下的 command 构成 CLI API：

- Install：`install`、`skill:install`、`rule:install`、`agent:install`
- Check：`check`、`skill:check`、`rule:check`、`agent:check`
- Capture：`capture`、`skill:capture`、`rule:capture`、`agent:capture`
- Ingest：`ingest`
- Update：`update`

typed command 是稳定核心模型；preset command 是基于 config mapping 的便捷层。

## Capture Feedback 设计

约定术语：

- `other-repo` / `user-repo`：发生内容变更并执行 `aipm capture` 的仓库。
- `aipm-repo`：集中接收 event 并执行 ingest / write-back 的仓库。

### 1) 在 other-repo 生成 CaptureEvent

在 `other-repo`（或 `user-repo`）本地执行 `aipm capture`，命令会生成 event 并写入本机 events 目录：

- `source_repo`
- `source_commit`
- `base_ref`（base 版本，建议 tag 或 commit）
- `event_id`
- `captured_at`

`CaptureService` 会生成标准化 `items[]`、`content_hash`、`files[]`（含 patch）与 `base_ref` 的 `CaptureEvent v1` payload，并落盘到 `~/.aipm/events/*.json`。

### 2) 写入本机 events（由 other-repo 执行）

- `other-repo` 将 event JSON 写入 `~/.aipm/events/*.json`
- event_id 使用 UUID（推荐 UUID v4）用于幂等与追踪

### 3) 在 aipm-repo 执行 ingest（即 write-back）

`ingest` 会扫描 events，并直接执行 write-back：

- schema 校验
- 基于 `source_repo::event_id` 的 dedupe
- 归档到 `processed` / `failed`，并维护处理索引
- 将 event 写回当前执行目录下的 `abilities/{skills,rules,agents}/{name}/{target}`，写回内容来自 event 里的 `files[]`

默认本机目录：

- aipm home：`~/.aipm`（或 `AIPM_HOME`）
- events：`~/.aipm/events`
- processed：`~/.aipm/processed`
- failed：`~/.aipm/failed`
- dedupe index：`~/.aipm/processed-event-ids.json`
- aipm-repo root：当前执行目录

## 数据契约：CaptureEvent v1

顶层必填字段：

- `schema_version`（必须为 `1`）
- `event_id`
- `source_repo`
- `source_commit`
- `base_ref`
- `captured_at`（ISO8601）
- `target`
- `items`（array）

每个 item 必填字段：

- `type`
- `name`
- `status`
- `content_hash`
- `files`（array，表示 target 原样文件集合；每个文件包含 `path`、`content`、`patch`）

## Exit Code 模型

状态模型：

- `unchanged`
- `modified`
- `missing`
- `unknown`

Exit code：

- `0`：全部 unchanged/acceptable
- `2`：存在 modified 或 missing
- `1`：校验或命令错误

