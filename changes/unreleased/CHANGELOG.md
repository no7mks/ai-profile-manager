# Changelog (Unreleased)

## Fixed

- ISS-31532: `ComposerBaselineResolver` 在 `COMPOSER_HOME` 未设置时 fallback 至 `~/.config/composer`（XDG）。
- ISS-01592: `InstallationProbe` 按 registry 相对路径检测 rule，修复带 category 的 rule 误报未安装。

## Added

- Deploy scope（`project` / `user`）、`global-setup`、`bootstrap`、`cleanup` 命令；Show/Update 重写。
- Breaking：无参 `apm install` / preset `default` 废弃；三阶段首装（`global-setup` → `bootstrap` → `/apm init`）。

## Docs

- 同步 `README`、`docs/manual/usage.md`、`docs/state/*`、APM skill（cursor/kiro）与 init-workflow。
