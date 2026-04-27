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

Capture typed item drift（当前为 placeholder 语义）。`capture` 会默认把 event 写入 `~/.aipm/events`：

```bash
aipm skill:capture graphify -t cursor
```

```bash
aipm rule:capture spec-core -t kiro
```

```bash
aipm agent:capture gatekeeper -t cursor
```

你可以通过 `--source-repo`、`--source-commit`、`--base-ref`、`--event-id`、`--captured-at` 覆盖 event metadata。

按 preset 进行 check 与 capture：

```bash
aipm check gitflow -t cursor
```

```bash
aipm capture kiro-spec -t kiro
```

更新 knowledge base：

```bash
aipm update
```

`ingest` 读取本机 `~/.aipm/events` 中的 capture event，并 write-back 到 `<aipm-repo>/abilities/{skills,rules,agents}/{name}/{target}` 的原样文件结构：

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
- Default target: `cursor`

## Check/Capture 状态模型

当前 `check`/`capture` 行为仍是 placeholder。状态值包括：

- `unchanged`
- `modified`
- `missing`
- `unknown`

Exit code：

- `0`：全部 unchanged
- `2`：存在 modified 或 missing
- `1`：命令或校验错误

## CaptureEvent v1（用于本机 events 交换）

Payload 结构：

```json
{
  "schema_version": 1,
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "source_repo": "acme/external-repo",
  "source_commit": "abc123...",
  "base_ref": "v1.2.3",
  "captured_at": "2026-04-27T12:00:00Z",
  "target": "cursor",
  "items": [
    {
      "type": "skill",
      "name": "graphify",
      "status": "modified",
      "content_hash": "sha256...",
      "files": [
        {
          "path": "SKILL.md",
          "content": "# graphify\n\nskill content",
          "patch": "--- a/SKILL.md\n+++ b/SKILL.md\n@@ -0,0 +1,3 @@\n+# graphify\n+\n+skill content"
        }
      ]
    }
  ]
}
```

推荐路径约定：

- other repo 将 event 写入 `~/.aipm/events/*.json`
- `aipm ingest` 扫描 events 并写回 `<aipm-repo>/abilities/*`
