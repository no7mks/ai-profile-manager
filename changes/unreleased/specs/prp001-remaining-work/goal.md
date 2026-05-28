# Spec Goal: PRP-001 Remaining Work

## 来源

- `docs/notes/prp001-phase2-gap.md`：Phase 2 代码适配未完成
- `docs/notes/apm-init-reference.md`：apm init 缺乏可执行的详细流程文档
- `changes/unreleased/proposals/PRP-001-abilities-relocation.md`：原始 proposal

---

## 背景摘要

PRP-001（Abilities Relocation）将 `abilities/` 从独立模板仓库迁移为"源即目标"结构。Phase 1（文件迁移）已完成——文件已在 `.cursor/` 和 `.kiro/` 下，`abilities/` 和 `scaffold/` 目录已删除。

但 Phase 2（代码适配）基本未动。经代码审查确认的完整差距如下：

### 代码层（旧路径引用）

| 文件 | 问题 |
|------|------|
| `Installer::installAbilityBundle()` | skill 源路径硬编码为 `$packageRoot/abilities/skills/<name>` |
| `Installer::installAgentFile()` | agent 源路径硬编码为 `$packageRoot/abilities/agents/<name>.<target>.md` |
| `Installer::findRuleSourceFiles()` | 在 `$packageRoot/abilities/rules/` 下按后缀递归搜索 |
| `Installer::resolveInstallTargetRuleFile()` | 用 `abilities/rules/` 前缀计算 category |
| `Installer::pickPreferredRuleSource()` | 后缀优先级排序（新布局无后缀） |
| `Installer::installGitIgnore()` | 模板路径为 `getcwd()/abilities/gitignore/template.gitignore`（文件已不存在） |
| `Installer::resolveInstallTargetDir()` | default 分支指向 `abilities/unknown-items/` |
| `AbilityDiffService::diffSkill()` | baseline 路径为 `$baselineRoot/abilities/skills/<name>` |
| `AbilityDiffService::diffAgent()` | baseline 路径为 `$baselineRoot/abilities/agents/<name>.<target>.md` |
| `AbilityDiffService::diffRule()` | 在 `$root/abilities/rules` 下按后缀搜索 |
| `AbilityDiffService::resolveRuleRelativePath()` | 同上 |
| `AbilityDiffService::pickPreferredRuleSourcePath()` | 后缀优先级排序 |
| `AbilityDiffService::diffForCapture()` | capture 功能残留方法 |
| `PresetRegistry` | `PRESETS_RELATIVE_PATH = 'abilities/_presets.json'`，目录已不存在 |
| `PresetCreateCommand` | description 引用 `abilities/_presets.json` |
| `PresetDeleteCommand` | description 引用 `abilities/_presets.json` |

### 死代码（capture/ingest 残留）

- `AbilityDiffService::diffForCapture()` 方法仍存在
- 测试中 3 个 capture 相关测试方法仍存在

### 测试层

- 约 15+ 个测试文件中的 fixture 使用 `abilities/skills/`、`abilities/rules/`、`abilities/agents/` 旧路径
- E2E `EndToEndTestCase::createGitignoreTemplate()` 使用 `abilities/gitignore/` 路径
- 多个测试创建 `abilities/_presets.json` fixture

### 文档层（SSOT 过时）

| 文件 | 问题 |
|------|------|
| `docs/state/install-behavior.md` | 全部源路径描述为旧布局 |
| `docs/state/architecture.md` | PresetRegistry 描述引用 `abilities/_presets.json`；数据流图包含旧路径 |
| `docs/state/cli-commands.md` | preset:create/delete 描述引用 `abilities/_presets.json` |
| `docs/state/abilities-model.md` | preset 运行时存储描述引用旧路径 |
| `docs/state/gitignore.md` | 模板文件路径为 `abilities/gitignore/template.gitignore` |
| `docs/design.md` | 全部能力路径约定描述为旧布局 |

### 其他发现

- `template.gitignore` 文件已不存在，但 `Installer::installGitIgnore()` 仍尝试读取它
- `ProjectInitializer` 已适配新路径（从 `.cursor/` 和 `.kiro/` 读取 scope rules），无需修改
- `KnowledgeBaseUpdater` 不依赖文件路径，无需修改
- `ConsoleRegistration` 无 capture/ingest 命令注册（已清理），无需修改
- `AbilityRegistry` 已正确解析 `abilities.yaml`，无需修改
- `abilities.yaml` 的 `presets` section 已定义好，可直接替代 `_presets.json`
- PRP-001 proposal status 仍为 `implemented`，应修正

---

## 目标

1. **代码适配新路径**：让 Installer、AbilityDiffService、PresetRegistry 从 `abilities.yaml` 的 `targets` 字段读取源路径，实现"源即目标"逻辑
2. **死代码清理**：完全移除 capture/ingest 相关代码、命令注册、测试
3. **测试修复**：更新单元/集成测试 fixture 到新布局，修复 E2E 测试使其通过
4. **SSOT 同步**：更新 `docs/state/install-behavior.md` 反映新路径逻辑
5. **apm init reference 补充**：在 apm skill 下新增详细的 init 流程文档，覆盖探测、确认、生成、幂等性等方面
6. **PRP-001 状态修正**：将 proposal status 从 `implemented` 改为 `in-progress`（或标注 Phase 分期）

---

## 不做的事情（Non-Goals）

- 不做跨项目 ability 分发
- 不做 ability 版本管理
- 不重新引入 capture/ingest 功能
- 不改变 `abilities.yaml` 的 schema 设计（已定义好）
- 不做 apm init 的代码实现变更（本次只补文档，代码改动聚焦在 install/check 路径）

---

## Clarification 记录

**Q1: 本次 feature 的范围边界？**
- A: 两者都做——Phase 2 代码适配 + apm init reference 文档补充

**Q2: capture/ingest 死代码处理？**
- A: 完全移除（删除相关代码、命令注册、测试）

**Q3: 执行优先级？**
- A: 无偏好，由 agent 安排

**Q4: 测试修复范围？**
- A: 完整测试修复（单元测试 fixture 更新 + E2E 测试修复，确保全绿）

---

## 约束与决策

- 核心设计思想"源即目标"不变：`abilities.yaml` 的 `targets` 字段声明了每个 ability 在 package root 中的真实路径
- install 逻辑简化为：从 `abilities.yaml` 解析 → 从 `$packageRoot/<targets[target]>` 读取 → 复制到 `$workspace/<targets[target]>`
- 不再需要后缀判定、category 推导、preferred source 排序
- PresetRegistry 应统一从 `abilities.yaml` 的 `presets` section 读取，废弃 `abilities/_presets.json`
- `docs/state/install-behavior.md` 作为 SSOT 必须与代码实现保持同步
