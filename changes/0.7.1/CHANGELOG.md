# Changelog (Unreleased)

面向开发者的详细变更日志。Release 时随目录 rename 为 `changes/<version>/CHANGELOG.md`。

---

## Added

- 新增 `quick-release` skill，支持不走 release/hotfix 分支的快速发布流程

## Changed

- `bootstrap` 命令改用 default preset 驱动
- README Quick Start 优化可读性

## Fixed

- `mirrorDirectory` 修复 trailing slash 截断问题

## Internal

- gitflow finish-flow 添加分支保留强约束
