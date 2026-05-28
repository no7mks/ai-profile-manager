# Architecture

apm 系统架构与模块边界。

---

## 模块结构

| 模块 | 职责 |
|------|------|
| CLI Commands (ConsoleRegistration) | Symfony Console 命令注册与参数解析；统一在 `ConsoleRegistration::register()` 中完成所有命令的实例化与注入 |
| InstallCommand | 安装 preset 中的 ability 到目标平台；无 preset 参数时执行 bootstrap（scaffold + scope rules + 默认 skill/agent） |
| ShowCommand | 展示所有可安装 ability（skill/agent/rule/hook）及其安装状态与 preset 映射；支持 `--type` 选项按类型过滤输出 |
| CheckCommand | 检查指定 preset 中所有 ability 在目标平台的安装状态 |
| SkillInstallCommand / RuleInstallCommand / AgentInstallCommand | 按类型安装单个或多个 ability |
| SkillUninstallCommand / RuleUninstallCommand / AgentUninstallCommand | 按类型卸载单个或多个 ability；卸载前执行 drift 检查 |
| PresetUninstallCommand | 卸载整个 preset 中的所有 ability |
| SkillCheckCommand / RuleCheckCommand / AgentCheckCommand | 按类型检查 ability 安装状态 |
| PresetCreateCommand | 在 abilities.yaml 的 presets section 中创建新 preset |
| PresetAddAbilityCommand / PresetRemoveAbilityCommand | 向 preset 添加/移除 ability 引用 |
| PresetDeleteCommand | 删除 preset 定义 |
| UpdateCommand | 更新本地 knowledge base 快照 |
| AbilityRegistry | 解析 abilities.yaml，按 section 返回结构化 AbilityEntry 列表；验证必填字段并收集错误后一次性报告 |
| Installer | 委托 AbilityRegistry 获取 ability 列表，按类型分发安装/卸载：skill/rule/agent 走文件复制，hook 走 HookInstaller，gitignore 走 GitIgnoreTemplateService |
| CheckService | 对比已安装 ability 与源文件的 diff 状态；hook 委托 HookChecker；输出 exit code（有 modified/missing → 2） |
| HookInstaller | 跨平台 hook 安装/卸载：Kiro（文件复制）、Cursor（目录递归复制 + hooks.json JSON merge） |
| HookChecker | 跨平台 hook 状态检查：Kiro（文件内容对比 → ok/drift/missing）、Cursor（目录+脚本+hooks.json 条目存在性 → ok/missing） |
| HookRegistryException | hooks.json 内容为无效 JSON 时抛出的运行时异常 |
| AbilityDiffService | 对 skill/rule/agent 执行逐文件 diff（委托 AbilityDirectoryDiff）；支持 baseline 布局与 installed 布局两种模式 |
| DirectoryMirrorService | 递归目录复制（含 overwrite）、单文件复制、目录创建；被 Installer、ProjectInitializer、HookInstaller 共用 |
| ComposerBaselineResolver | 解析全局 Composer installed.json 定位 apm 包安装路径；支持 `APM_BASELINE_ROOT` 环境变量覆盖 |
| PresetRegistry | Preset 定义管理：读取 abilities.yaml 的 presets section；fallback 到 AppConfig 硬编码默认值 |
| ProjectInitializer | 项目 bootstrap：复制 scaffold（docs/、issues/、AGENTS.md）+ 安装平台 scope rules |
| KnowledgeBaseUpdater | 将当前 ability 列表写入 `~/.config/apm/knowledge-base.json` 供外部工具查询 |
| GitIgnoreTemplateService | 操作 `.gitignore` 中的 managed section：从模板文件按 `@apm:block` 渲染规则并 merge 到目标文件 |
| AppConfig | 静态配置常量：KNOWN_TARGETS、DEFAULT_TARGETS、DEFAULT_SKILLS/RULES/AGENTS、KNOWN_PRESETS |

---

## 数据流

```
abilities.yaml ──→ AbilityRegistry.parse() ──→ AbilityEntry[]
                                                    │
                                                    ▼
                                    ┌───────────────────────────────┐
                                    │          Installer            │
                                    │  installTyped / uninstallTyped│
                                    └───────────────────────────────┘
                                         │         │         │
                          ┌──────────────┼─────────┼─────────┼──────────────┐
                          ▼              ▼         ▼         ▼              ▼
                   DirectoryMirror  HookInstaller  GitIgnoreTemplate   CheckService
                   (skill/rule/agent)  (hook)      (.gitignore)        (diff 状态)
                          │              │         │                        │
                          ▼              ▼         ▼                        ▼
                   目标项目            目标项目   .gitignore              状态报告
                (.cursor/ | .kiro/)  (.cursor/ | .kiro/)              (exit code)

abilities.yaml (presets section) ──→ PresetRegistry ──→ preset spec
                                                    │
                                                    ▼
                                         Install/Uninstall/Check

ComposerBaselineResolver ──→ baseline install_path ──→ AbilityDiffService
                                                            │
                                                            ▼
                                                    diff 结果 (unchanged/modified/missing)
```

---

## 目标平台

| 平台 | 配置根 | rules 路径 | agents 路径 | skills 路径 | hooks 路径 |
|------|--------|-----------|-------------|-------------|------------|
| Cursor | `.cursor/` | `.cursor/rules/` | `.cursor/agents/` | `.cursor/skills/` | `.cursor/hooks/` + `.cursor/hooks.json` |
| Kiro | `.kiro/` | `.kiro/steering/` | `.kiro/agents/` | `.kiro/skills/` | `.kiro/hooks/` |

---

## 配置常量

| 常量 | 值 |
|------|-----|
| KNOWN_TARGETS | `['cursor', 'kiro']` |
| DEFAULT_TARGETS | `['cursor', 'kiro']` |
