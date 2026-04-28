# AI Profile Manager (`aipm`)

`aipm` 是一个 PHP CLI 工具，用于在不同 IDE/CLI 工具之间管理 AI profile item。
当前显式区分三种 item type：`skill`、`rule`、`agent`。

## 安装

本地开发安装：

```bash
composer install
```

全局安装（推荐终端直接调用）：

```bash
composer global require no7mks/ai-profile-manager
```

将 Composer 全局 bin 目录加入 `PATH`（macOS/zsh 示例）：

```bash
echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

验证：

```bash
which aipm
aipm --help
```

运行方式：

```bash
php bin/aipm --help
```

## 使用方式

安装 `skill`：

```bash
aipm skill:install graphify
```

安装 `rule`（在 Kiro 中，`rule` 会按 `steering` 处理）：

```bash
aipm rule:install spec-core -t kiro
```

安装 `agent`：

```bash
aipm agent:install gatekeeper flow-finisher
```

安装 preset（顶层 `install` 只处理 preset）：

```bash
aipm install gitflow
```

```bash
aipm install kiro-spec -t kiro
```

检查 typed item（当前为 placeholder 语义）：

```bash
aipm skill:check graphify -t cursor
```

```bash
aipm rule:check spec-core -t kiro
```

```bash
aipm agent:check gatekeeper -t cursor
```

### Baseline（对照版本）

Capture 将 **当前仓库工作区**下的 `abilities/{skills,rules,agents}/…` 与 **Composer 全局安装**中的 `no7mks/ai-profile-manager` 包目录做目录级 diff（新增、修改、删除文件）。Baseline 来自 `~/.composer/vendor/composer/installed.json` 解析出的安装路径；无需也不支持额外的 baseline CLI 开关。

开发与测试可设置 **`AIPM_BASELINE_ROOT`** 指向一棵「模拟全局包根目录」以代替解析（须包含相同的 `abilities/…` 布局）。

生成的 **CaptureEvent** 仅使用 **`schema_version`: `2`**，顶层包含必填字段 **`baseline`**：`package`、`version`、`install_path`，以及在有则写入的 **`reference`**。字段 **`base_ref`** 由 CLI 填入 `baseline.reference` 或 `baseline.version`（字符串，可为空）。

### Typed capture（显式 ability）

```bash
aipm skill:capture graphify -t cursor
```

```bash
aipm rule:capture spec-core -t kiro
```

```bash
aipm agent:capture gatekeeper -t cursor
```

有无变更都会打印摘要；**仅有变更时**才向 `~/.aipm/events` 写入 event。可按需传入 `--source-repo`、`--source-commit`、`--event-id`、`--captured-at`。**`--base-ref` 已忽略**（保留仅为兼容旧脚本）。

### Preset capture 与全仓库快照

按 preset 展开 ability 列表后做同类 diff：

```bash
aipm check gitflow -t cursor
```

```bash
aipm capture kiro-spec -t kiro
```

未带 preset 参数时，对当前仓库 `abilities/skills|rules|agents` 下**所有子目录名**做全量快照；默认会交互确认是否生成 event，**`--yes` / `-y`** 可跳过确认（适合脚本）。

```bash
aipm capture -t cursor
```

```bash
aipm capture -t cursor --yes
```

### Preset 清单（`abilities/_presets.json`）

若存在 **`abilities/_presets.json`**，则其中内容为 preset 定义的**唯一来源**（否则回退到内置 `AppConfig::PRESET_ITEMS`）。变更 preset 定义后通过回流更新集中仓库：

```bash
aipm preset:create my-flow --skill gitflow --agent flow-starter
```

```bash
aipm preset:add-ability gitflow extra-skill --skill
```

```bash
aipm preset:remove-ability gitflow extra-skill --skill
```

```bash
aipm preset:delete my-flow
```

上述命令在 manifest 相对 Composer baseline 有差异时，会写入含 `type: preset` item 的 capture event，供 `ingest` 将 `abilities/_presets.json` 写回 aipm-repo。

更新 knowledge base：

```bash
aipm update
```

`ingest` 读取本机 `~/.aipm/events` 中的 capture event（**仅 schema v2**），并 write-back 到 `<aipm-repo>/abilities/…`（含 **preset manifest** `abilities/_presets.json` 及删除文件时卸载目标路径）：

```bash
aipm ingest
```

可选参数：

- `--events-dir <dir>`：指定 events 目录（默认 `~/.aipm/events`）

`ingest` 默认将 event 写回当前执行目录下的 `abilities/{skills,rules,agents}/{name}/{target}`。

说明：`ingest` 只负责把 event 写回仓库文件并形成变更；后续 git 提交流程（hotfix/PR）由你的仓库流程决定。

## Preset 列表

- `gitflow`
  - skills: `gitflow`
  - agents: `flow-starter`, `flow-finisher`
- `kiro-spec`
  - skills: `kiro-spec-planning`, `kiro-spec-execution`
  - rules: `kiro-spec-steering`
  - agents: `gatekeeper`

## 当前默认值

- Default skills: `graphify`
- Default rules: `(none)`
- Default agents: `(none)`
- Default targets: `cursor`, `kiro`

## Check/Capture 状态模型

Item `status` 取值包括：`unchanged`、`modified`、`missing`、`unknown`（check 占位路径仍可能出现 `unknown`）。

Exit code：

- `0`：无漂移需上报，或检查全部 acceptable
- `2`：存在 modified/missing（capture 在有文件变更时）
- `1`：命令或校验错误（含无法解析 Composer baseline）

## CaptureEvent v2

**`schema_version` 必须为 `2`**；必填 **`baseline`**（见上文）；文件项可增加 **`deleted`: true** 表示相对 baseline 删除。

推荐路径约定：

- other repo 将 event 写入 `~/.aipm/events/*.json`
- `aipm ingest` 扫描 events 并写回 `<aipm-repo>/abilities/*`
