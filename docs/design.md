# ai-profile-manager 设计说明

## 概览

`aipm` 是一个 PHP CLI 工具，用于管理 AI profile item（`skill`、`rule`、`agent`），并支持跨仓库的 Capture feedback 回流。

## Namespace 分层

### `AiProfileManager\Core`

- `Application`：CLI composition root。
- 负责创建并组装 service，再注册 Symfony Console command。

### `AiProfileManager\Config`

- `AppConfig`：静态 runtime configuration 与默认集合。
- 定义默认 preset、known target；当仓库中不存在 `abilities/_presets.json` 时作为 preset 来源。

### `AiProfileManager\Service`

- `ComposerBaselineResolver`：从 `~/.composer/vendor/composer/installed.json` 解析全局安装的 `no7mks/ai-profile-manager` 路径与版本元数据；支持环境变量 `AIPM_BASELINE_ROOT` 覆盖（测试或本地模拟）。
- `AbilityDirectoryDiff`：对 baseline 包内目录与工作区目录做递归 diff，生成带 `patch` / 可选 `deleted` 的文件项。
- `PresetRegistry`：读写 `abilities/_presets.json`；文件存在时其为 preset 定义的权威来源。
- `Installer`：安装流程（当前仍是按 target 的 placeholder 行为）。
- `CheckService`：状态评估与结果渲染模型。
- `CaptureService`：capture、事件组装与落盘。
- `KnowledgeBaseUpdater`：写入本地 knowledge snapshot。

### `AiProfileManager\Capture`

- `CaptureEventSchema`：仅校验 **CaptureEvent v2**。
- `CaptureEventIngestor`：执行 validate/ingest/dedupe，并处理 events 批量事件。
- `CaptureWriteBackService`：将 event 写回 `aipm-repo` 文件（含删除与 `preset` manifest）。

## Command 面

`src/Command` 下的 command 构成 CLI API：

- Install：`install`、`skill:install`、`rule:install`、`agent:install`
- Check：`check`、`skill:check`、`rule:check`、`agent:check`
- Capture：`capture`、`skill:capture`、`rule:capture`、`agent:capture`
- Preset：`preset:create`、`preset:add-ability`、`preset:remove-ability`、`preset:delete`
- Ingest：`ingest`
- Update：`update`

typed command 是稳定核心模型；`capture`（无参）对全仓库 abilities 做快照并可交互确认；preset 子命令通过 manifest diff 产生 capture event。

## Capture Feedback 设计

约定术语：

- `other-repo` / `user-repo`：发生内容变更并执行 `aipm capture` 的仓库（当前工作目录为能力文件根）。
- `aipm-repo`：集中接收 event 并执行 ingest / write-back 的仓库。

### 1) 在 other-repo 生成 CaptureEvent

在 `other-repo` 本地执行 capture 子命令时：

- **Baseline** 仅来自 Composer 全局安装解析结果（或 `AIPM_BASELINE_ROOT`），**不由**用户通过 CLI 自定义对照版本。
- 事件载荷为 **`schema_version`: 2**，包含必填 **`baseline`**：`package`、`version`、`install_path`，可选 **`reference`**。
- `items[]` 仅包含相对 baseline **确有变更**的 ability（或 preset manifest）；每个 item 含 `files[]`，文件级可带 **`deleted`: true** 表示删除。

落盘路径：`~/.aipm/events/*.json`（或 `AIPM_HOME`）。

### 2) 写入本机 events（由 other-repo 执行）

- event_id 使用 UUID（推荐 UUID v4）用于幂等与追踪。

### 3) 在 aipm-repo 执行 ingest（即 write-back）

`ingest` 会扫描 events，并直接执行 write-back：

- schema 校验（仅 v2）
- 基于 `source_repo::event_id` 的 dedupe
- 归档到 `processed` / `failed`，并维护处理索引
- 将 event 写回当前执行目录下的 `abilities/…`：`skills|rules|agents` 路径结构、`abilities/_presets.json`，以及对标记 `deleted` 的文件执行删除

默认本机目录：

- aipm home：`~/.aipm`（或 `AIPM_HOME`）
- events：`~/.aipm/events`
- processed：`~/.aipm/processed-events`
- failed：`~/.aipm/failed-events`
- dedupe index：`~/.aipm/processed-event-ids.json`
- aipm-repo root：当前执行目录

## 数据契约：CaptureEvent v2（摘要）

顶层必填 / 约定字段：

- `schema_version`：`2`
- `event_id`、`source_repo`、`source_commit`、`captured_at`、`target`
- `base_ref`：字符串（遗留字段；由 baseline 推导）
- **`baseline`**：`package`、`version`、`install_path`，可选 `reference`
- `items`（可为空数组；无变更时可不生成文件）

每个 item：`type`（含 `skill`|`rule`|`agent`|`preset`）、`name`、`status`、`content_hash`、`files[]`。

每个 file：`path`、`content`（字符串，删除时可为空）、`patch`、可选 **`deleted`**（bool）。

## Exit Code 模型

状态模型：

- `unchanged`
- `modified`
- `missing`
- `unknown`

Exit code：

- `0`：全部 unchanged / 无可上报变更
- `2`：存在 modified 或 missing（capture 在有变更时）
- `1`：校验或命令错误（含 baseline 解析失败）
