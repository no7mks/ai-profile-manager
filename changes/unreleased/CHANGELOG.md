# Changelog (Unreleased → 0.7.5)

## Fixed

- scaffold 不再将 apm 自身业务文档复制到用户项目（ISS-32020）。`ProjectInitializer` 改为显式复制骨架文件（`docs/README.md`、`issues/README.md`、`AGENTS.md`）+ 创建子目录 `.gitkeep`。

## Added

- `apm init` 新增规划式路径（Planning-mode Path）：对空项目或无 manifest 的项目，通过交互引导收集选型意图并生成 PROJECT.md / state / manual 基线。

## Changed

- agent / steering / skill 中移除 graphify 硬编码引用，改为由 `graphify` skill 自身提供知识图谱能力。
- PROJECT.md 中 graphify 描述改为 `` `graphify` skill `` 表述。
