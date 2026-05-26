# Hook Ability

需要在 apm 中支持一种新的 ability 类型：`hook`。

**状态**：已实现（PRP-001 abilities-relocation feature）

---

## 已落地

1. **安装策略**：Kiro 文件复制（`HookInstaller::installKiro`）；Cursor 目录复制 + `<name>.json` 条目 merge 进 `.cursor/hooks.json`（`HookInstaller::installCursor`）。
2. **install/check/uninstall 逻辑**：`HookInstaller`（安装/卸载）+ `HookChecker`（漂移检测）完整实现。
3. **show 命令展示**：`ShowCommand` 支持 `--type hook` 过滤。
4. **卸载精确移除**：通过 `command` 字段匹配从 `hooks.json` 移除条目。

## 设计取舍（有意不做）

- **abilities.yaml 无 `hooks:` 段**：hook 通过 preset 的 `includes` 间接管理，不在 abilities.yaml 顶层独立注册。`HookInstaller` 由 `Installer` 在检测到 hook 类型时调用。
- **跨平台事件映射表**：不做。各平台 hook 独立定义，某些 hook 只有单平台 target。

## 原始调研内容（归档）

两平台 Hook 格式对比、初步想法等见 git 历史。
