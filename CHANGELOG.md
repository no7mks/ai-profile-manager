# Changelog

本文件记录版本间的用户可见变更。**每次发布前**请对照 `git log` 自行整理并更新（例如相对上一 tag：`git log v0.1.1..HEAD --oneline`，将上一 tag 换成实际发布的基准）。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

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
