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
- `Installer`：从全局包安装源拷贝到当前仓库：`skill` 用 `abilities/skills/{name}/`（跨 target 单份语义）分发到目标平台目录，`agent` 用 `abilities/agents/{name}/{target}/` 镜像；`rule` 用 `abilities/rules/{category}/{name}.{cursor|kiro}.*` 按名称匹配复制；缺失来源时报 `[fail]` 且 `exit_code` 非 0；末尾合并 `.gitignore` 托管段。
- `DirectoryMirrorService`：递归目录拷贝（`init` 脚手架与 Installer 共用）。
- `GitIgnoreTemplateService`：从 `abilities/gitignore/template.gitignore` 读取 marker block，按安装的 ability/target 渲染并幂等写入用户仓库 `.gitignore` 的托管段。
- `CheckService`：状态评估与结果渲染模型。
- `CaptureService`：capture、change 组装与落盘（`~/.aipm/changes`）。
- `KnowledgeBaseUpdater`：写入本地 knowledge snapshot。

### `AiProfileManager\Capture`

- `CaptureChangeSchema`：仅校验 **CaptureChange v2**。
- `CaptureChangeIngestor`：执行 validate/ingest/dedupe，并处理 changes 批量文件。
- `CaptureWriteBackService`：将 change 写回 `aipm-repo` 文件（含删除与 `preset` manifest）。

## Command 面

`src/Command` 下的 command 构成 CLI API：

- Install：`install`、`skill:install`、`rule:install`、`agent:install`
- Check：`check`、`skill:check`、`rule:check`、`agent:check`
- Capture：`capture`、`skill:capture`、`rule:capture`、`agent:capture`
- Preset：`preset:create`、`preset:add-ability`、`preset:remove-ability`、`preset:delete`
- Ingest：`ingest`
- Update：`update`

typed command 是稳定核心模型；`install`（无参数）承担项目初始化（scaffold + apm skill，并提示 `/apm init` 完成 SSOT ready 初始化：`PROJECT.md` + `docs/state/` 与 `docs/manual/` 下按项目上下文生成的内容文件，不固定文件名）；`capture`（无参）对全仓库 abilities 做快照并可交互确认；preset 子命令通过 manifest diff 产生 capture change。

## 安装期 `.gitignore` 注入（单模板）

`install`、`skill:install`、`rule:install`、`agent:install` 在安装输出末尾执行内置 `.gitignore` 注入步骤，不提供额外 flag 开关。

- 模板来源：`abilities/gitignore/template.gitignore`（aipm-repo 内唯一模板文件）。
- marker 语法：
  - `## @aipm:block ability=<id> target=<target>`
  - `## @aipm:end`
- `<id>` 约定：
  - `skill:<name>` / `rule:<name>` / `agent:<name>`：匹配 typed ability 安装
  - 无前缀（如 `gitflow`）：匹配 preset 名称
- `<target>` 约定：`cursor` / `kiro` / `*`。

安装时根据本次安装请求构建匹配键集合并渲染规则，写入业务仓库根 `.gitignore` 的托管段：

- `# BEGIN aipm-managed-gitignore v1`
- `# END aipm-managed-gitignore v1`

行为：

- `.gitignore` 不存在则创建。
- 托管段存在则整体替换（幂等）；不存在则追加。
- 非托管段内容保持不变。

## Capture Feedback 设计

约定术语：

- `other-repo` / `user-repo`：发生内容变更并执行 `aipm capture` 的仓库（当前工作目录为能力文件根）。
- `aipm-repo`：集中接收 change 并执行 ingest / write-back 的仓库。

### 1) 在 other-repo 生成 CaptureChange

在 `other-repo` 本地执行 capture 子命令时：

- **Baseline** 仅来自 Composer 全局安装解析结果（或 `AIPM_BASELINE_ROOT`），**不由**用户通过 CLI 自定义对照版本。
- 载荷为 **`schema_version`: 2**，包含必填 **`change_id`** 与 **`baseline`**：`package`、`version`、`install_path`，可选 **`reference`**。
- `items[]` 仅包含相对 baseline **确有变更**的 ability（或 preset manifest）；每个 item 含 `files[]`，文件级可带 **`deleted`: true** 表示删除。

落盘路径：`~/.aipm/changes/*.json`（或 `AIPM_HOME`）。

### 2) 写入本机 changes（由 other-repo 执行）

- `change_id` 使用 UUID（推荐 UUID v4）用于幂等与追踪。

### 3) 在 aipm-repo 执行 ingest（即 write-back）

`ingest` 会扫描 changes，并直接执行 write-back：

- schema 校验（仅 v2）
- 基于 `source_repo::change_id` 的 dedupe
- 归档到 `processed-changes` / `failed-changes`，并维护处理索引
- 将 change 写回当前执行目录下的 `abilities/…`：`skills`（无 target 子目录）/`rules`/`agents` 路径结构、`abilities/_presets.json`，以及对标记 `deleted` 的文件执行删除

默认本机目录：

- aipm home：`~/.aipm`（或 `AIPM_HOME`）
- changes：`~/.aipm/changes`
- processed：`~/.aipm/processed-changes`
- failed：`~/.aipm/failed-changes`
- dedupe index：`~/.aipm/processed-change-ids.json`
- aipm-repo root：当前执行目录

## 数据契约：CaptureChange v2（摘要）

顶层必填 / 约定字段：

- `schema_version`：`2`
- `change_id`、`source_repo`、`source_commit`、`captured_at`、`target`
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
