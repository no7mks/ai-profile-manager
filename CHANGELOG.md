# Changelog

本文件记录版本间的用户可见变更。**每次发布前**请对照 `git log` 自行整理并更新（例如相对上一 tag：`git log v0.2.0..HEAD --oneline`，将上一 tag 换成实际发布的基准）。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [0.3.0] - 2026-04-28
### Breaking

- Capture 对象与 JSON 字段：**`CaptureEvent` / `event_id` 更名为 `CaptureChange` / `change_id`**；本机目录 **`~/.aipm/events` 等改为 `changes` / `processed-changes` / `failed-changes`**，审计文件 `events.jsonl`、`processed-event-ids.json` 同步更名。
- CLI：`--event-id` → **`--change-id`**，`ingest` 的 **`--events-dir` → `--changes-dir`**。
- 包内 **`abilities/rules`** 采用分类目录 + 后缀文件：`abilities/rules/{category}/{name}.cursor.mdc|.kiro.md`（与 sample 与安装目标路径映射一致）；`aipm init` 的 scope 规则源路径已同步。
- 内置 preset **`gitflow` / `kiro-spec`** 的 agent 名称与仓库内目录对齐：`gitflow-starter` / `gitflow-finisher`、`spec-gatekeeper`（原 `flow-*` / `gatekeeper` 占位名不再使用）。

### Added

- **`Installer` 真实实现**：从全局安装包根复制到当前仓库；`skill/agent` 走 `abilities/{skills,agents}/{name}/{target}/`，`rule` 走 `abilities/rules/{category}/{name}.{cursor|kiro}.*`；任一能力缺失来源时输出 `[fail]` 且安装命令返回非 0。
- **`DirectoryMirrorService`**：供 `ProjectInitializer` 与 `Installer` 复用的递归目录拷贝。
- **`aipm init` 项目画像预填**：新增项目探测与交互确认流程，初始化时为 `PROJECT.md` 预填 6 项字段（Project Stack / Full Test Command / Build Command / Run Entry / Version Locations / Sensitive Files），并记录 `detected`、`confirmed`、`confidence`。

### Changed

- 安装命令（`install` / `skill:install` / `rule:install` / `agent:install`）在镜像 abilities 之后，继续支持基于 `abilities/gitignore/template.gitignore` 的 marker 模板自动更新仓库根 `.gitignore` 托管段（`aipm-managed-gitignore v1`）。
- 模板支持 `ability=<id>` 与 `target=<target>` 最小语法：`<id>` 可为 `skill:<name>` / `rule:<name>` / `agent:<name>`，无前缀时按 preset 名匹配。
- `aipm init` 在非交互环境默认失败并提示使用 `--no-prefill`；使用该参数时写入 `UNKNOWN` 占位值。

## [0.2.0] - 2026-04-28

### Added

- **`aipm init`**：向指定目录（默认当前目录，不存在则创建）安装捆绑脚手架（包根 **`scaffold/`**：`docs/`、`issues/`、`AGENTS.md`、`PROJECT.md`），并按 `-t` 安装各平台 scope 规则（`abilities/rules`：`cursor-scope` → `.cursor/rules/cursor-scope.mdc`，`kiro-scope` → `.kiro/steering/kiro-scope.md`）。`-t` 省略时与 `skill:install` 等命令一致，使用默认目标 **cursor 与 kiro**；支持 `--force` / `-f` 覆盖已有文件。
- Cursor 规则：Agent 开工入口与中文沟通约定（`.cursor/rules/agent-entry-point.mdc`）。
- Cursor 规则：Git 分支与发布流程（`.cursor/rules/git-release-flow.mdc`）。
- 根目录 `CHANGELOG.md` 与发布前更新约定。
- Capture 相对 Composer 全局安装包目录做 abilities 递归 diff；**CaptureEvent v2** 含必填 `baseline` 块；文件级支持删除写回。
- `capture` 无参时对全仓库 abilities 快照，可 `--yes` 跳过确认；`preset:*` 命令维护 `abilities/_presets.json` 并可选生成 manifest 类 event。
- 环境变量 **`AIPM_BASELINE_ROOT`**：开发/测试时指定模拟包根目录。

### Changed

- **`aipm init` 脚手架来源**：由 `sample/` 改为专用目录 **`scaffold/`**（`sample/` 仅作演示工作区，不再作为 init 模板来源）。
- `ingest` 与 `CaptureEventSchema` 仅接受 **CaptureEvent schema v2**（不再校验 v1 事件）。
- 默认安装目标调整为 Cursor 与 Kiro。
- `install` / `check` / `capture <preset>` 的 preset 名称解析合并 **`abilities/_presets.json`**（存在则为权威来源）。

## [0.1.1] - 2026-04-28

### Fixed

- 调整 CLI 引导逻辑，兼容 Composer 全局安装等路径下的 autoload 解析。

## [0.1.0] - 2026-04-27

### Added

- 首次公开发布：`aipm` CLI、abilities 与 sample 工作区、安装/检查/捕获与相关文档等。
