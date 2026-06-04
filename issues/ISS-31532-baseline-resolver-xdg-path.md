# ISS-31532 ComposerBaselineResolver 未兼容 XDG 路径

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `closed` |
| Found In | `v0.8.0` |
| Fixed In | `2c96333` |
| Related Test | `tests/ComposerBaselineResolverTest.php` |

---

## Description

`ComposerBaselineResolver::installedJsonPath()` 在 `COMPOSER_HOME` 未设置时，仅 fallback 到 `$HOME/.composer/vendor/composer/installed.json`。macOS 新版 Composer 遵循 XDG 规范，实际路径为 `$HOME/.config/composer/vendor/composer/installed.json`。

导致 `resolve()` 返回 `null`，`CheckService::checkTyped` 走入 `buildUnknownResults`，所有 ability status 均为 `unknown`——`show` 命令只能靠 fallback 的 `isInstalledOnTarget` 判断安装状态，baseline diff 完全失效。

---

## Steps to Reproduce

1. macOS 环境，Composer 全局目录位于 `~/.config/composer/`（非 `~/.composer/`）
2. 未设置 `COMPOSER_HOME` 或 `APM_BASELINE_ROOT` 环境变量
3. 执行 `apm show -t cursor` 或 `apm rule:check <name> -t cursor`

---

## Expected Behavior

正常解析 `~/.config/composer/vendor/composer/installed.json`，返回 baseline 路径，diff 正常执行。

---

## Actual Behavior

`resolve()` 返回 `null`，所有 ability 状态为 `unknown`，`rule:check` 输出 `[todo] rule <name> unknown on cursor`。

---

## Analysis

`installedJsonPath()` 的 fallback 逻辑只考虑了 `$HOME/.composer`，未处理 XDG 标准下的 `$HOME/.config/composer`。Composer 2.x 在 macOS 上优先使用 `~/.config/composer`。

修复方案：fallback 时按优先级依次尝试 `$HOME/.composer` 和 `$HOME/.config/composer`，取第一个存在的路径。

---

## History

- `2026-06-04` `2c96333` [关闭] Phase 1：`ComposerBaselineResolver` 增加 `~/.config/composer` fallback；spec `deploy-scope-and-cli`
- `2026-06-03 17:30 +08` `v0.8.0` [发现] 调查 `plan:quick-plan-conventions` 显示 not-installed 时发现
