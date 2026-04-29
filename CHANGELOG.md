# Changelog

本文件记录版本间的用户可见变更。**每次发布前**请对照 `git log` 自行整理并更新（例如相对上一 tag：`git log v0.2.0..HEAD --oneline`，将上一 tag 换成实际发布的基准）。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Added

- 新增 `preset:uninstall`、`skill:uninstall`、`rule:uninstall`、`agent:uninstall` 四个卸载命令，支持按 target 批量卸载已安装项。

### Changed

- 卸载前统一复用 `check` 状态判定做 preflight：检测到 `modified` 时默认阻断，必须显式传 `--force` 才允许继续删除。
- 抽出共享能力 diff 逻辑，`CheckService`、`CaptureService` 与 uninstall preflight 复用同一套状态语义（`unknown` / `unchanged` / `modified` / `missing`）。

## [0.5.1] - 2026-04-29

### Fixed

- 清理 `abilities/skills` 下遗留的 `<name>/<target>/.gitkeep` 空目录占位，统一为无 target 子目录的 skill 布局约定（`abilities/skills/<name>/...`）。

## [0.5.0] - 2026-04-29

### Breaking

- 包内 **agent** 源文件改为扁平命名：`abilities/agents/<name>.<cursor|kiro>.md`（不再使用 `abilities/agents/<name>/<target>/<name>.md`）。安装目标仍为 `.{cursor|kiro}/agents/<name>.md`。Capture / write-back 路径与 `discoverWorkspaceAbilities` 已对齐。

### Changed

- 统一项目缩写为 `apm`，同步更新文档、CLI 提示、环境变量名、gitignore 托管 marker 与测试命名。

## [0.4.4] - 2026-04-28

### Changed

- 在 scaffold 的 `docs/state`、`docs/manual`、`docs/proposals`、`docs/notes`、`docs/changes` 下补充 `.gitkeep`，确保初始化后目录稳定存在。
- 恢复 init/bootstrap 测试中对 `docs/state` 目录存在性的断言，保持脚手架结构校验闭环。

## [0.4.3] - 2026-04-28

### Changed

- 下线 `doc-operator` agent（Cursor/Kiro 双平台定义），降低未触发子 agent 的维护成本。
- 将 note/proposal 分支与流转约束上提到 `docs/README.md`，将 issue 分流与 release 收敛约束上提到 `issues/README.md`。

## [0.4.2] - 2026-04-28

### Changed

- 调整 `/apm init` 交付约定：不再为 `docs/state`、`docs/manual` 预设固定文件名，改为按项目上下文创建/更新文档。
- `docs` 文档规范入口改为单一 `docs/README.md`，`docs` 下五个子目录不再分别维护 README。

## [0.4.1] - 2026-04-28

### Added

- scaffold 新增根级 `CHANGELOG.md` 模板，初始化后可直接承载版本摘要。

### Changed

- 更新 scaffold 文档结构说明，统一 `docs/changes` 与根级 `CHANGELOG.md` 的职责边界与链接路径。
- `docs/manual`、`docs/state` 补充命名约定与最小模板，降低初始化项目后的落地成本。
- `docs/notes`、`docs/proposals` 去除对特定工具目录的硬编码描述，改为项目约定表述。

## [0.4.0] - 2026-04-28

### Breaking

- CLI 移除 `apm init`，初始化流程并入 `apm install`（无参数）；原交互式 PROJECT.md 预填链路不再提供。
- `abilities/skills` 改为跨 target 单份结构：`abilities/skills/<name>/...`。旧的 `abilities/skills/<name>/<target>/...` 不再被安装器识别。
- 内置引导 skill 名统一为 `apm`（命令前缀统一为 `/apm ...`）。

### Changed

- `apm install` 新增双模式：有 `preset` 时按预设安装；无 `preset` 时执行仓库 bootstrap（`docs/`、`issues/`、`AGENTS.md`）并安装 `apm` skill。
- scaffold 不再包含 `PROJECT.md`，安装结束时改为提示使用 skill 命令 `/apm init` 初始化 SSOT（`PROJECT.md` + `docs/state/README.md` + `docs/manual/README.md`）。
- Capture/write-back 对 skill 路径约定同步为无 target 子目录。

## [0.3.0] - 2026-04-28
### Breaking

- Capture 对象与 JSON 字段：**`CaptureEvent` / `event_id` 更名为 `CaptureChange` / `change_id`**；本机目录 **`~/.apm/events` 等改为 `changes` / `processed-changes` / `failed-changes`**，审计文件 `events.jsonl`、`processed-event-ids.json` 同步更名。
- CLI：`--event-id` → **`--change-id`**，`ingest` 的 **`--events-dir` → `--changes-dir`**。
- 包内 **`abilities/rules`** 采用分类目录 + 后缀文件：`abilities/rules/{category}/{name}.cursor.mdc|.kiro.md`（与 sample 与安装目标路径映射一致）；`apm init` 的 scope 规则源路径已同步。
- 内置 preset **`gitflow` / `kiro-spec`** 的 agent 名称与仓库内目录对齐：`gitflow-starter` / `gitflow-finisher`、`spec-gatekeeper`（原 `flow-*` / `gatekeeper` 占位名不再使用）。

### Added

- **`Installer` 真实实现**：从全局安装包根复制到当前仓库；`skill/agent` 走 `abilities/{skills,agents}/{name}/{target}/`，`rule` 走 `abilities/rules/{category}/{name}.{cursor|kiro}.*`；任一能力缺失来源时输出 `[fail]` 且安装命令返回非 0。
- **`DirectoryMirrorService`**：供 `ProjectInitializer` 与 `Installer` 复用的递归目录拷贝。
- **`apm init` 项目画像预填**：新增项目探测与交互确认流程，初始化时为 `PROJECT.md` 预填 6 项字段（Project Stack / Full Test Command / Build Command / Run Entry / Version Locations / Sensitive Files），并记录 `detected`、`confirmed`、`confidence`。

### Changed

- 安装命令（`install` / `skill:install` / `rule:install` / `agent:install`）在镜像 abilities 之后，继续支持基于 `abilities/gitignore/template.gitignore` 的 marker 模板自动更新仓库根 `.gitignore` 托管段（`apm-managed-gitignore v1`）。
- 模板支持 `ability=<id>` 与 `target=<target>` 最小语法：`<id>` 可为 `skill:<name>` / `rule:<name>` / `agent:<name>`，无前缀时按 preset 名匹配。
- `apm init` 在非交互环境默认失败并提示使用 `--no-prefill`；使用该参数时写入 `UNKNOWN` 占位值。

## [0.2.0] - 2026-04-28

### Added

- **`apm init`**：向指定目录（默认当前目录，不存在则创建）安装捆绑脚手架（包根 **`scaffold/`**：`docs/`、`issues/`、`AGENTS.md`、`PROJECT.md`），并按 `-t` 安装各平台 scope 规则（`abilities/rules`：`cursor-scope` → `.cursor/rules/cursor-scope.mdc`，`kiro-scope` → `.kiro/steering/kiro-scope.md`）。`-t` 省略时与 `skill:install` 等命令一致，使用默认目标 **cursor 与 kiro**；支持 `--force` / `-f` 覆盖已有文件。
- Cursor 规则：Agent 开工入口与中文沟通约定（`.cursor/rules/agent-entry-point.mdc`）。
- Cursor 规则：Git 分支与发布流程（`.cursor/rules/git-release-flow.mdc`）。
- 根目录 `CHANGELOG.md` 与发布前更新约定。
- Capture 相对 Composer 全局安装包目录做 abilities 递归 diff；**CaptureEvent v2** 含必填 `baseline` 块；文件级支持删除写回。
- `capture` 无参时对全仓库 abilities 快照，可 `--yes` 跳过确认；`preset:*` 命令维护 `abilities/_presets.json` 并可选生成 manifest 类 event。
- 环境变量 **`APM_BASELINE_ROOT`**：开发/测试时指定模拟包根目录。

### Changed

- **`apm init` 脚手架来源**：由 `sample/` 改为专用目录 **`scaffold/`**（`sample/` 仅作演示工作区，不再作为 init 模板来源）。
- `ingest` 与 `CaptureEventSchema` 仅接受 **CaptureEvent schema v2**（不再校验 v1 事件）。
- 默认安装目标调整为 Cursor 与 Kiro。
- `install` / `check` / `capture <preset>` 的 preset 名称解析合并 **`abilities/_presets.json`**（存在则为权威来源）。

## [0.1.1] - 2026-04-28

### Fixed

- 调整 CLI 引导逻辑，兼容 Composer 全局安装等路径下的 autoload 解析。

## [0.1.0] - 2026-04-27

### Added

- 首次公开发布：`apm` CLI、abilities 与 sample 工作区、安装/检查/捕获与相关文档等。
