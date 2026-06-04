# Design Document: Deploy Scope and CLI Onboarding

基于 `requirements.md` 与 GK CR1–CR4；**Phase 1 → Phase 2** 合入；finish 前 Platform Verification。术语：**phase** = 规划/需求批次（本文档）；**wave** = `tasks.md` 执行编排（与 phase 对应，tasks 专用）。

---

## Overview

扩展 **Deploy Scope**（project/user），修复 Baseline XDG 与 category rule **Installation Fallback**，交付 PRP-002 CLI（`global-setup`、`bootstrap`、`cleanup`）及 Show/Update 重写。路径根统一 `DeployRootResolver`；Show 三态与 Check 内态分离（CR1）；scaffold 仍 `ProjectInitializer` + `bootstrap`。

---

## Architecture

CLI → `DeployRootResolver` → `Installer` / `CheckService` / `InstallationProbe`；`ComposerBaselineResolver` 供 diff；`ShowStatusPresenter`、`AbilityUpdateService` + `GlobalInstallDetector`。

- **P1**: `ComposerBaselineResolver` only
- **P2**: scope、probe、新命令、Show/Update、`abilities.yaml`

---

## Components and Interfaces

### P1: `ComposerBaselineResolver`

`installedJsonPath()`：`APM_BASELINE_ROOT`（resolve 已有）→ override → `COMPOSER_HOME` → `$HOME/.composer` → `$HOME/.config/composer`（首个可读 `vendor/composer/installed.json`）。新增 `candidateComposerHomes(): list<string>`。

### P2: Scope

```php
enum DeployScope: string { case Project = 'project'; case User = 'user'; }
final class DeployRootResolver {
    public function resolve(DeployScope $scope): string;
    public function parseScopeOption(?string $value): DeployScope;
    public function absoluteTargetPath(DeployScope $scope, string $relativeTarget): string;
}
```

`AbilityEntry` 增 `scopes`（默认 `[project]`）。`AbilityRegistry`: `globalSetupIncludes()`, `projectOnlyPaths()`, `getEntry()`. **Project-only**: gitignore, prompt, tga-query, lark-sheets, cursor-scope, kiro-scope, superpowers-integration.

```php
final class ScopeGuard {
    public function assertBatchAllowed(DeployScope $scope, array $items): void; // 零部分写入
}
```

YAML: `global-setup.includes`（typed）；删 preset `default`；global-setup 不作 preset。

### P2: `InstallationProbe`（R2）

```php
public function isPresent(string $type, string $name, string $target, DeployScope $scope): bool;
```

Rule 用 registry `targets[target]` + `absoluteTargetPath`；禁止 basename 匹配 `category:name`。`Installer` 全路径改 scope；`uninstallProjectScope()` 供 cleanup。

### P2: Check + Show（CR1）

`CheckService::checkTypedForScope($items, $targets, $scope)` — workspaceRoot 来自 DeployRootResolver。

| 内态 | Show |
|------|------|
| unchanged | installed |
| modified | installed with local change |
| missing | not installed |
| unknown | Probe → installed / not installed |

`ShowStatusPresenter::lines(?scope, targets, ?type)` — 枚举 registry 中 skill/rule/agent/hook/gitignore/prompt；**targets 列**为 `cursor, kiro` 可读文本（R9.2）；双 scope → `[warn] installed in both user and project` + scope 提示（R9.3）。gitignore 仍不参与 diff（同现 state）。`show [-t] [--scope] [--type]`；输出文案不与 update 的 `up to date`/changed 混用（R9.6）。

`CheckService` 对 hook 在 user scope 使用 `HookChecker` + user root（与 project 对称）。

### P2: Update（CR3–4）

`GlobalInstallDetector::isGlobalInvocation()` — argv[0] 在 global `vendor/bin`。`AbilityUpdateService::reportChanges(bool $force)` — 枚举 user+project；无 force 仅 diff 报告；force 两侧各自覆盖 baseline。非 global → 报错。**删除** `KnowledgeBaseUpdater` 及测试、`Application`/`ConsoleRegistration` 注入与 `architecture.md` 描述（D-CR3 A）。

### P2: 命令

| 命令 | 行为 |
|------|------|
| global-setup | globalSetupIncludes；固定 user；任意 cwd（R5.3）；幂等/`--force`（R5.4）；缺 target `[skip]`（R5.5）；成功 stdout 提示 `/apm init`（R5.6） |
| bootstrap | ProjectInitializer only；不装 ability/preset（R6.2） |
| cleanup | 遍历 registry 全部 conventional ability，对 project 路径探测，存在则 `uninstall`（D-CR4 A）；不动 user/scaffold/docs（R7.1–2）；文档 cleanup→init 无需 global-setup（R7.3） |
| install/add | 无参失败+指引（R8.1）；typed add 缺 type 失败（R8.3）；preset 先 ScopeGuard+全量校验（R8.4）；`default` → 迁移文案（CR2） |
| remove | uninstall 别名（R8.2）；**check** 不新增别名（R8.5，保持 `apm check` / `apm skill:check` 等价） |

`ConsoleRegistration` 注册新命令与依赖注入。

---

## Data Models

- project 根 = `getcwd()`；user 根 = `$HOME`
- `GlobalSetupRef = {type, path}`
- `ShowAbilityRow`（presenter 内存）：status, scopeLabel, dualScopeWarning, targetsText

---

## Correctness Properties

### Property 1: Baseline 顺序

- **Validates**: Requirements 1.1–1.4, 1.6
- **条件**: 未设置 `APM_BASELINE_ROOT` 且 Composer 使用 XDG 布局
- **保证**: 解析顺序为 env → `COMPOSER_HOME` → `~/.composer` → `~/.config/composer`
- **违反后果**: Check/Show 全员 `unknown`，Phase 2 依赖 baseline 的命令失效

### Property 2: Category rule

- **Validates**: Requirements 2.1–2.4
- **条件**: rule `path` 含 `category:name` 且文件在 registry 相对路径
- **保证**: `InstallationProbe` 仅 `is_file`/`is_dir` 于 `absoluteTargetPath` 结果
- **违反后果**: Show fallback 误报 not installed（ISS-01592）

### Property 3: 批量原子

- **Validates**: Requirements 3.6, 8.4
- **条件**: 批量安装含不允许 user scope 的项
- **保证**: `ScopeGuard::assertBatchAllowed` 在首次写盘前抛 `InvalidScopeException`
- **违反后果**: 部分安装，cleanup 无法一致回滚

### Property 4: Show 三态

- **Validates**: Requirements 9.4
- **条件**: baseline 可用且用户未改文件
- **保证**: 用户只见 `installed`，内态为 `unchanged`；文案与 update 分离（9.6）
- **违反后果**: 与 update 输出混淆，误判可更新性

### Property 5: Update global

- **Validates**: Requirements 10.1, 10.4
- **条件**: 从 vendor 或非 global `vendor/bin` 调用 update
- **保证**: 拒绝执行；`--force` 时 user/project **各自**对 baseline 覆盖（requirements CR3）
- **违反后果**: 错误 baseline 源或只更新一侧

---

## Error Handling

`InvalidScopeException`（非法 `--scope`、project-only+user）→ stderr 含合法 scope 与 ability 名；exit 1。无参 install/add、缺 type 的 add → 固定指引模板。global-setup `[skip]` 不抬高 exit code。bootstrap 冲突无 `--force` → 同现 `RuntimeException`。

---

## Testing Strategy

P1: ResolverTest + Check 非 unknown。P2: Probe、ScopeGuard、Presenter、UpdateService、GlobalSetup、无参 install。R13: `docs/notes/deploy-scope-platform-verification.md`。

---

## Impact Analysis

**State**: `install-behavior.md`（§Baseline、各类型 user/project 目标路径）；`cli-commands.md`（新命令、show/update、别名表）；`abilities-model.md`（`scopes`、`global-setup:`、删 default）。

**代码**: `ComposerBaselineResolver`、`DeployRootResolver`、`InstallationProbe`、`ScopeGuard`、`Installer`、`CheckService`（含 `checkTypedForScope`）、`ShowCommand`/`ShowStatusPresenter`、`UpdateCommand`/`AbilityUpdateService`、`GlobalInstallDetector`、新命令类、`AbilityRegistry`/`PresetRegistry`、`ConsoleRegistration`/`Application` DI。

**数据/兼容**: 无自动迁移（R3 Non-scope）；删 `default` → 运行时 Unknown preset + README 三阶段（CR2）；删除 `KnowledgeBaseUpdater` 与 `~/.config/apm/knowledge-base.json` 写入路径（D-CR3 A，非 SSOT 行为）。

**外部**: Composer global（含 XDG home）；IDE `~/.cursor`/`~/.kiro` 仅 R13 人工验证；user-scope Cursor hook 的 `hooks.json` merge 行为与现 project 逻辑相同、根改为 user。

**配置**: `abilities.yaml` 增 `global-setup.includes`、per-entry `scopes`；删 preset `default`；其余 preset 保留独立 `scopes`（R4.5）。

---

## Alternatives Considered

1. 保留 show preset 列 — 违 R9，落选。2. env 切 cwd 模拟 user — 难测，落选。3. 取消 Fallback — 违 R2 AC5，落选。4. update 写 knowledge-base — 违 R10，落选。

---

## Requirements Traceability

R1 Resolver | R2 Probe | R3–4 Scope+yaml | R5–7 三命令 | R8 install 族 | R9–10 Presenter/Update | R11–12 tasks 文档 | R13 验证日志 | R14 issue。requirements GK CR 已落在 Show 表、迁移文案、双侧 force、global vendor/bin。

---

## Clarification Round

> **D-CR1**: Phase 1 合入？A 独立 commit 先于 P2 / B 与 P2 首 task 同 commit / C 先合 develop（违 goal）
> - **A:** 单独 commit，先于任何 Phase 2 代码合入 feature 分支

> **D-CR2**: `--scope` 范围？A 仅 install/uninstall 族 / B + show/check / C Application 全局 option
> - **A:** 仅 `install`/`add`/`uninstall`/`remove` 及 typed `*:install`/`*:uninstall` 族；`show` 已有 `--scope` 过滤（R9.5），`check` 不新增 scope option

> **D-CR3**: `KnowledgeBaseUpdater`？A 删除类与测试 / B 保留不注册 / C 改 dev 子命令并存
> - **A:** 删除 `KnowledgeBaseUpdater`、`KnowledgeBaseUpdaterTest` 及相关 DI/文档；`update` 仅 `AbilityUpdateService`

> **D-CR4**: `cleanup` 枚举？A registry 全 conventional 探测 project / B 新 manifest（超 scope）/ C 复用 show project 已装集
> - **A:** `CleanupCommand` 遍历 registry conventional 列表，逐项 `InstallationProbe`/等价探测 project 路径，存在则卸载；不依赖 show、不引入 manifest
