# Changes

本目录用于记录项目的**版本级变更日志**与**release 归档材料**。

---

## 目录结构

```
CHANGELOG.md                   # 仓库根目录全局摘要
docs/
└── changes/
    ├── unreleased/            # 已完成但未 release 的 feature 变更记录
    │   └── <feature-name>.md  # 每个 feature 一个文件
    ├── <version>/             # 已 release 的版本目录
    │   ├── CHANGELOG.md       # 该版本的详细变更记录
    │   └── artifacts/         # 该版本归档材料（可选）
    │       └── <feature-name>/# 与该版本相关的补充资料
```

`CHANGELOG.md` 与 `docs/changes/` 是一组配套结构：前者做版本索引，后者做版本详情与归档。

---

## 边界

- 本目录只记录“已经发生的变更”。
- 本目录不记录需求意图（属于 `docs/proposals/`）。
- 本目录不定义当前系统事实（属于 `docs/state/`）。
- 全局版本摘要放在仓库根目录 `CHANGELOG.md`，本目录只保留版本级详细内容与附属归档。

## 根目录 CHANGELOG.md（摘要）格式

```md
# Changelog

## v0.2 - 2026-03-31

一句话摘要。详见 [docs/changes/0.2/CHANGELOG.md](docs/changes/0.2/CHANGELOG.md)。

## v0.1 - 2026-03-20

一句话摘要。详见 [docs/changes/0.1/CHANGELOG.md](docs/changes/0.1/CHANGELOG.md)。
```

保持简洁摘要，详细内容见 `docs/changes/<version>/CHANGELOG.md`。

---

## 版本 CHANGELOG.md 格式

```md
# Changelog v0.2

本文件记录 v0.2 release 的变更内容。

---

## 包含的 Feature

### Feature 名称（PRP-xxx）

- 变更点 1
- 变更点 2

---

## 修复的 Issue

- [ISS-xxx](fixed/ISS-xxx.md)：问题描述

---

## 工程变更

- 变更点

---

## 测试覆盖

- 测试统计
```

---

## 设计原则

- `unreleased/` 按 feature 拆文件，避免并行开发时冲突
- 每个 release 是自包含目录（changelog + artifacts）
- 根目录 `CHANGELOG.md` 保持简洁，只做索引
- 附属归档材料用于追溯，不作为系统事实来源

---

## 命名与最小模板

- `unreleased/` 文件命名：`<feature-name>.md`（kebab-case）
- 版本目录命名：`<major>.<minor>`（如 `0.2`）
- 版本日志文件固定命名：`CHANGELOG.md`

`docs/changes/unreleased/<feature-name>.md` 最小模板：

```md
# <Feature Name>（PRP-xxx）

## Added
- ...

## Changed
- ...

## Fixed
- ...
```
