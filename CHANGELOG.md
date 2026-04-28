# Changelog

本文件记录版本间的用户可见变更。**每次发布前**请对照 `git log` 自行整理并更新（例如相对上一 tag：`git log v0.1.1..HEAD --oneline`，将上一 tag 换成实际发布的基准）。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Added

- Cursor 规则：Agent 开工入口与中文沟通约定（`.cursor/rules/agent-entry-point.mdc`）。
- Cursor 规则：Git 分支与发布流程（`.cursor/rules/git-release-flow.mdc`）。
- 根目录 `CHANGELOG.md` 与发布前更新约定。

### Changed

- 默认安装目标调整为 Cursor 与 Kiro。

## [0.1.1] - 2026-04-28

### Fixed

- 调整 CLI 引导逻辑，兼容 Composer 全局安装等路径下的 autoload 解析。

## [0.1.0] - 2026-04-27

### Added

- 首次公开发布：`aipm` CLI、abilities 与 sample 工作区、安装/检查/捕获与相关文档等。
