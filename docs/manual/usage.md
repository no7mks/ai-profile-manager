# Usage

apm 的日常使用说明。

---

## 安装

```bash
composer global require no7mks/ai-profile-manager
```

---

## 初始化新项目

```bash
# 在目标项目根目录执行
apm install
```

这会安装 scaffold（`docs/`、`issues/`、`AGENTS.md`）和 `default` preset 中定义的 ability。之后使用 Agent skill 命令 `/apm init` 生成 SSOT 基线。

---

## 安装 ability

```bash
# 按 preset 批量安装（preset 中包含的 hook 会一并安装）
apm install gitflow -t cursor

# 安装单个 skill
apm skill:install graphify -t kiro

# 安装单个 rule
apm rule:install git-conventions -t cursor

# 安装单个 agent
apm agent:install code-reviewer -t kiro
```

Hook 类型 ability 通过 preset 安装。安装时 Kiro 平台会将 hook 文件复制到 `.kiro/hooks/`，Cursor 平台会将 hook 目录复制到 `.cursor/hooks/` 并将条目注入 `.cursor/hooks.json`。

---

## 检查漂移

```bash
# 检查 preset 下所有 ability 的状态（含 hook）
apm check gitflow -t cursor

# 检查单个 ability
apm skill:check graphify -t kiro
```

---

## 查看可用 ability

```bash
# 查看所有 ability（含 skill/agent/rule/hook）
apm show -t cursor

# 按类型过滤
apm show --type hook
apm show --type skill
apm show --type rule
apm show --type agent
```

---

## 卸载

```bash
# 卸载前会检查是否有本地修改
apm skill:uninstall graphify -t cursor

# 强制卸载（忽略本地修改）
apm skill:uninstall graphify -t cursor --force

# 卸载整个 preset（含其中的 hook）
apm preset:uninstall gitflow -t cursor

# 强制卸载 preset（忽略 drift）
apm preset:uninstall gitflow -t cursor --force
```
