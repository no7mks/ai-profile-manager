# PRP-001 Phase 2 代码适配未完成

PRP-001 标记为 implemented，但 Phase 2（代码适配新路径）基本未动。当前 `bin/apm install` 在真实环境中无法工作——代码仍查找已删除的 `abilities/` 目录。

---

## 现状

- Phase 1（文件迁移）：✅ 完成。`abilities/` 和 `scaffold/` 已删除，文件已在 `.cursor/` 和 `.kiro/` 下。
- Phase 2（代码适配）：❌ 未完成。核心路径解析逻辑全部指向旧布局。
- 功能精简：⚠️ 命令已移除，但有死代码残留。

---

## 需要修改的代码

### Installer（核心）

| 方法 | 问题 | 期望 |
|------|------|------|
| `installAbilityBundle()` | skill 源路径为 `$packageRoot/abilities/skills/<name>` | 从 `abilities.yaml` targets 字段读取，源即 `$packageRoot/<target-path>` |
| `installAgentFile()` | agent 源路径为 `$packageRoot/abilities/agents/<name>.<target>.md` | 同上，源为 `$packageRoot/.<target>/agents/<name>.md` |
| `findRuleSourceFiles()` | 在 `$packageRoot/abilities/rules/` 下按后缀搜索 | 同上，源为 `$packageRoot/.<target>/rules/<category>/<name>.mdc` |
| `resolveInstallTargetRuleFile()` | 用 `abilities/rules/` 前缀计算 category | 不再需要，target 路径已在 `abilities.yaml` 中声明 |
| `pickPreferredRuleSource()` | 后缀优先级排序 | 废弃，新布局无后缀 |
| `installGitIgnore()` | 模板路径为 `getcwd()/abilities/gitignore/template.gitignore` | 路径需更新或改为内联 |

### AbilityDiffService

| 方法 | 问题 |
|------|------|
| `diffSkill()` | baseline 路径为 `$baselineRoot/abilities/skills/<name>` |
| `diffAgent()` | baseline 路径为 `$baselineRoot/abilities/agents/<name>.<target>.md` |
| `diffRule()` / `resolveRuleRelativePath()` | 在 `$root/abilities/rules` 下按后缀搜索 |
| `diffForCapture()` | capture 功能残留，应移除 |

### PresetRegistry

- `PRESETS_RELATIVE_PATH = 'abilities/_presets.json'`——`abilities/` 目录已不存在
- `abilities.yaml` 已有 `presets` section 的声明式定义，应统一入口

---

## 设计方向

PRP-001 的核心思想是"源即目标"——`abilities.yaml` 的 `targets` 字段已经声明了每个 ability 在 package root 中的真实路径。install 逻辑应该：

1. 从 `abilities.yaml` 解析 ability 列表
2. 根据 `targets[<target>]` 获取源文件的相对路径
3. 源文件位于 `$packageRoot/<targets[target]>`
4. 目标文件位于 `$workspace/<targets[target]>`
5. install = 从 packageRoot 复制到 workspace；check = 对比两者

这大幅简化了路径解析——不再需要后缀判定、category 推导、preferred source 排序。

---

## 文档同步

- `docs/state/install-behavior.md` 仍记录旧路径，需同步更新
- PRP-001 status 应从 `implemented` 改为 `in-progress`（或拆分 Phase 标记）

---

## 测试影响

- E2E 测试已按新布局编排（当前预期 fail）
- 单元/集成测试的 fixture 全部基于旧布局，Phase 2 完成后需同步更新

---

## 优先级

高。当前 `bin/apm install` 在真实环境中完全不可用。
