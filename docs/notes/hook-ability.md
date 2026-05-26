# Hook Ability

需要在 apm 中支持一种新的 ability 类型：`hook`。

---

## 背景

当前 `abilities.yaml` 支持的类型有 `rules`、`agents`、`skills`、`gitignore`、`presets`。两个目标平台都已支持 hook 机制：

- **Kiro**：`.kiro/hooks/*.kiro.hook`，每个 hook 一个独立 JSON 文件
- **Cursor**：`.cursor/hooks.json`，单文件集中配置所有 hook

现有项目中已有一个 Kiro hook 文件（`check-write-length.kiro.hook`），但 apm 尚未将 hook 纳入 ability 管理体系。

---

## 两平台 Hook 格式对比

### Kiro（多文件，每 hook 一个 JSON）

```json
{
  "enabled": true,
  "name": "Check Write Length",
  "description": "写入前检查内容行数",
  "version": "1",
  "when": {
    "type": "preToolUse",
    "toolTypes": ["fs_write"]
  },
  "then": {
    "type": "runCommand",
    "command": "echo '⚠️ 提醒：...'"
  }
}
```

路径：`.kiro/hooks/<name>.kiro.hook`

### Cursor（单文件集中配置）

```json
{
  "version": 1,
  "hooks": {
    "afterFileEdit": [
      { "command": "hooks/audit.sh" }
    ],
    "stop": [
      { "command": "but cursor stop" }
    ]
  }
}
```

路径：`.cursor/hooks.json`

Cursor 支持的生命周期事件：`beforeSubmitPrompt`、`beforeShellExecution`、`beforeMCPExecution`、`beforeReadFile`、`afterFileEdit`、`stop`。

---

## 需要解决的问题

1. **abilities.yaml 新增 `hooks` 段** — 定义 hook ability 的注册格式
2. **安装策略差异** — Kiro 是文件级复制（类似 rule）；Cursor 是合并到单个 `hooks.json`（需要 JSON merge 逻辑）
3. **install/check/uninstall 逻辑** — Kiro 侧简单复制；Cursor 侧需要读取现有 `hooks.json`、合并/移除条目
4. **show 命令展示** — `apm show` 需要列出 hook 类型的 ability
5. **跨平台映射** — 同一个 hook 概念在两平台的表达方式不同，不是所有 hook 都能跨平台（如 Kiro 的 `preToolUse` 在 Cursor 中没有直接对应）

---

## 初步想法

- hook 在 `abilities.yaml` 中是文件级 ability，类似 rule/agent
- 某些 hook 可能只有单平台 target（如 `check-write-length` 只有 Kiro target）
- Cursor 侧的安装逻辑比较特殊：不是简单复制文件，而是将条目合并进 `.cursor/hooks.json`
- `abilities.yaml` 格式参考：

```yaml
hooks:
  - path: check-write-length
    description: 写入前检查内容行数
    targets:
      kiro: .kiro/hooks/check-write-length.kiro.hook
      # cursor 不需要此 hook，故无 cursor target

  - path: notify-on-stop
    description: 任务完成后发送通知
    targets:
      kiro: .kiro/hooks/notify-on-stop.kiro.hook
      cursor: .cursor/hooks/notify-on-stop.json
```

---

## 待确认

- Cursor 侧安装策略：是合并进 `.cursor/hooks.json`，还是也用独立文件（源文件）+ 安装时合并？
- 卸载时如何从 `hooks.json` 中精确移除对应条目？需要标记机制吗？
- 事件类型映射表：哪些 Kiro 事件能对应到 Cursor 事件？（如 `preToolUse` vs `beforeShellExecution`）
