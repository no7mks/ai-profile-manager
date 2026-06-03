# Spec Goal: Deploy Scope and CLI Onboarding

本 spec 在 `feature/deploy-scope-and-cli` 上交付 PRP-002，并纳入 ISS-31532、ISS-01592；按 phase 分阶段实现，finish 时统一发布。

---

## 来源

| 来源 | 路径 | 说明 |
|------|------|------|
| Proposal | `docs/proposals/PRP-002-deploy-scope-and-cli.md` | Status: `in-progress`；user/project scope、global-setup、CLI 重构 |
| Issue | `issues/ISS-31532-baseline-resolver-xdg-path.md` | P1：Composer baseline 未兼容 XDG `~/.config/composer` |
| Issue | `issues/ISS-01592-rule-installed-check-ignores-category.md` | P2：`isInstalledOnTarget` 对 `category:name` rule 匹配失败 |
| SSOT | `docs/state/abilities-model.md`、`install-behavior.md`、`cli-commands.md` 等 | 实现须与 state 同步 |

---

## 背景摘要

v0.8.0 在 macOS（Composer 2.x XDG 布局）上，`ComposerBaselineResolver` 无法定位 `~/.config/composer/.../installed.json`，导致 `check`/`show` 的 baseline diff 全线 `unknown`。`show` 退化为 `Installer::isInstalledOnTarget()`，而该实现对带 category 的 rule（如 `plan:quick-plan-conventions`）用 basename 匹配，误报为 not-installed。

PRP-002 在此基础上引入 user/project 部署 scope、`apm global-setup` / `bootstrap` / `cleanup`、重写 `show`/`update`、删除 preset `default`、无参 `install`/`add` 直接报错等 **breaking** 变更，并依赖 **composer global** baseline 作为 `update` 与 show 三态（installed / installed with local change）的基础。

本 feature **不拆 hotfix 分支**：两个 issue 与 PRP 在同一分支、同一 finish 版本内交付，仅 **实现顺序** 分 phase。

---

## 目标

### Phase 1（先行，可独立验收）

1. **ISS-31532**：`ComposerBaselineResolver` fallback 依次尝试 `$HOME/.composer` 与 `$HOME/.config/composer`（及既有 `COMPOSER_HOME` / `APM_BASELINE_ROOT` 优先级不变）；补单元测试；同步 `docs/state/install-behavior.md` baseline 解析说明。
2. Phase 1 完成后：macOS XDG 环境下 `apm show`、`apm rule:check` 等应恢复 baseline diff（不再全员 `unknown`）。

### Phase 2（与 PRP-002 一并）

3. **ISS-01592**：修复 `Installer::isInstalledOnTarget()` 对 rule 的判定（registry `targets[target]` 完整路径或 `category` 转路径分隔符后的相对路径匹配，**禁止** strip category 后仅比 basename）；保留 baseline 不可用时的 show fallback；补测试（含 category rule）。
4. **PRP-002 全量实现**（与 proposal 验收摘要一致）：
   - Scope 模型（`project` / `user`、`--scope`、仅 project 能力列表）
   - `abilities.yaml`：`global-setup:` 清单、删除 preset `default`
   - 命令：`global-setup`、`bootstrap`、`cleanup`；无参 `install`/`add` 报错；install↔add、uninstall↔remove 同义词
   - `show`：仅常规 ability、user+project 合并一行、双侧安装 `[warn]`、三态相对 global baseline
   - `update`：仅 composer global apm；vendor 执行报错；无参只报告差异、`--force` 覆盖
   - §4 init 两条路径 + `init-workflow` / apm skill 文档更新（检测式 / 规划式、必调 bootstrap、默认 `-t cursor -t kiro`）
   - PRP 文档同步表：`README.md`、`docs/manual/usage.md`、`docs/state/*`、`.cursor`/`.kiro` apm skill 副本
5. **§8 平台验证**：作为 finish 前阻塞项——验证 `~/.cursor`、`~/.kiro` 加载、user hook、同名 user/project 行为记录。
6. Issue 闭环：实现并验证后将 ISS-31532、ISS-01592 标为 closed（`Fixed In` 填实际版本）。

---

## 不做的事情（Non-Goals）

- 省略类型 `apm add <name>`（PRP Non-Goal）
- 自动迁移旧安装布局到 user/project
- `global-setup` 安装 preset；cleanup 动 scaffold、`PROJECT.md`、`docs/state|manual`、user 目录内容
- capture/ingest 复活
- 为两个 issue 单独开 hotfix 分支或 patch 先行发布（与 Q1 决策一致）
- Phase 2 中取消 `isInstalledOnTarget` fallback（除非 requirements/design 阶段基于 PRP show 明确替换且仍有等价行为；默认保留并修复，见 Q2）

---

## Clarification 记录

**Q1: ISS 与 PRP 在本 feature 上的交付方式？**

- A: **同一 feature 一次发布**——Phase 1 先合 31532，Phase 2 再 01592 + PRP；finish 时统一版本。

**Q2: ISS-01592 在 PRP 重写 show 时怎么处理？**

- A: Phase 2 **仍修复** `isInstalledOnTarget`（registry 路径判定），**保留** baseline 不可用时的 fallback，直至 PRP show 在后续阶段明确取消该路径。

**Q3: PRP-002 在本 spec 中的范围？**

- A: **PRP 全量**（scope、global-setup、bootstrap、cleanup、show、update、init 文档与 breaking CLI）；**§8 平台验证** 作为 finish 前阻塞项。

**用户初始输入（2026-06-03）**

- 来源：PRP-002 + ISS-01592 + ISS-31532。
- 实现顺序：先修 31532，再 01592 与 PRP-002 一起解决（与 Q1 phase 划分一致）。

---

## 约束与决策

| 项 | 决策 |
|----|------|
| Spec 路径 | `.cursor/specs/deploy-scope-and-cli/` |
| 分支 | `feature/deploy-scope-and-cli` |
| 路径类型 | Feature（非 hotfix）；issue 作为 PRP 前置/并行修复纳入同一 spec |
| Phase 顺序 | 31532 →（01592 + PRP-002）；`tasks.md` 用 **wave** 编排执行批次；规划文档用 phase，避免 Phase 2 阻塞于 baseline |
| Baseline | PRP `update` 强制 global；31532 为 macOS/XDG 上该前提的修复 |
| Breaking | 无参 `install` 废弃、删 preset `default`、README 三阶段首装；须在 requirements 中可测化 |
| SSOT | 代码与 `docs/state/` 同步；proposal 非 SSOT，以实现后 state 为准 |
| 31532 与 01592 关系 | 31532 修好后 category rule 在**有 baseline** 时通常已由 diff 判对；01592 仍必须修（fallback 与无 baseline 场景） |
