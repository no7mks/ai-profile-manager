# CLI Commands

apm 所有已注册命令的签名、行为与错误条件。

---

## install

**签名**: `install [preset] [-t|--target TARGET...] [-f|--force]`

**正常行为**:

- 有 preset 参数时：从 PresetRegistry 获取 preset spec，对其中所有 ability 执行 `Installer::installTyped()`，按 target 逐一安装 skill/rule/agent/hook。
- 无 preset 参数时（bootstrap 模式）：
  1. 调用 ProjectInitializer 复制 scaffold（docs/、issues/、AGENTS.md）
  2. 安装平台 scope rules（cursor-scope.mdc / kiro-scope.md）
  3. 安装默认 ability（skill:apm + agent:code-reviewer）
  4. 输出 bootstrap 完成提示

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| preset 不在 PresetRegistry 中 | 输出 "Unknown preset: X. Known presets: ..." ，exit FAILURE |
| bootstrap 模式下目标已存在 docs/issues/AGENTS.md 且无 --force | 抛出 RuntimeException，exit FAILURE |
| ability 源文件缺失 | 输出 `[fail]` 行，最终 exit FAILURE |

---

## show

**签名**: `show [-t|--target TARGET...] [--type TYPE]`

**正常行为**:

1. 从 AbilityRegistry 获取所有可用 ability
2. 通过 CheckService 获取安装状态
3. 从 PresetRegistry 获取 preset 映射
4. 按类型分组输出每个 ability 的安装状态与所属 preset

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| --type 值不在 `[rule, agent, skill, hook, gitignore, preset]` 中 | 输出 "Unknown type: X"，exit FAILURE |

---

## skill:install

**签名**: `skill:install [skills...] [-t|--target TARGET...]`

**正常行为**:

- 无 skills 参数时使用 DEFAULT_SKILLS
- 对每个 skill 执行目录递归复制到目标平台 skills 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| skill 源目录不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## rule:install

**签名**: `rule:install [rules...] [-t|--target TARGET...]`

**正常行为**:

- 无 rules 参数时使用 DEFAULT_RULES
- 按 `<name>.<target>.(mdc|md)` 查找源文件，复制到目标平台 rules 路径
- 保留源文件的子目录结构

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| rule 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## agent:install

**签名**: `agent:install [agents...] [-t|--target TARGET...]`

**正常行为**:

- 无 agents 参数时使用 DEFAULT_AGENTS
- 从 `abilities/agents/<name>.<target>.md` 复制到目标平台 agents 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| agent 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## skill:uninstall

**签名**: `skill:uninstall [skills...] [-t|--target TARGET...] [-f|--force]`

**正常行为**:

1. 通过 CheckService 检查 drift 状态
2. 有 drift 且无 --force → 中止并输出 drift 详情
3. 无 drift 或有 --force → 删除目标目录

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 检测到 modified 且无 --force | 输出 drift 行 + "Detected modified items"，exit FAILURE |
| skill 目标目录不存在 | 输出 `[miss]`，继续执行 |

---

## rule:uninstall

**签名**: `rule:uninstall [rules...] [-t|--target TARGET...] [-f|--force]`

**正常行为**: 同 skill:uninstall 逻辑，删除目标文件而非目录。

**错误条件**: 同 skill:uninstall。

---

## agent:uninstall

**签名**: `agent:uninstall [agents...] [-t|--target TARGET...] [-f|--force]`

**正常行为**: 同 skill:uninstall 逻辑，删除目标文件。

**错误条件**: 同 skill:uninstall。

---

## preset:uninstall

**签名**: `preset:uninstall <preset> [-t|--target TARGET...] [-f|--force]`

**正常行为**:

1. 从 PresetRegistry 获取 preset spec
2. 通过 CheckService 检查所有 ability 的 drift 状态
3. 有 drift 且无 --force → 中止
4. 无 drift 或有 --force → 对 preset 中所有 ability 执行卸载

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| preset 不在 PresetRegistry 中 | 输出 "Unknown preset: X"，exit FAILURE |
| 检测到 modified 且无 --force | 输出 drift 行 + 错误信息，exit FAILURE |

---

## skill:check

**签名**: `skill:check [skills...] [-t|--target TARGET...]`

**正常行为**:

- 无 skills 参数时使用 DEFAULT_SKILLS
- 通过 CheckService 对比源文件与已安装文件
- 输出每个 ability 的状态行（ok/drift/miss/todo）
- Exit code: 存在 modified 或 missing → 2；否则 → 0

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |

---

## rule:check

**签名**: `rule:check [rules...] [-t|--target TARGET...]`

**正常行为**: 同 skill:check，针对 rule 类型。

**错误条件**: 同 skill:check。

---

## agent:check

**签名**: `agent:check [agents...] [-t|--target TARGET...]`

**正常行为**: 同 skill:check，针对 agent 类型。

**错误条件**: 同 skill:check。


---

## check

**签名**: `check <preset> [-t|--target TARGET...]`

**正常行为**:

1. 从 PresetRegistry 获取 preset spec
2. 通过 CheckService 检查所有 ability（含 hook）的安装状态
3. 输出状态行
4. Exit code: 存在 modified 或 missing → 2；否则 → 0

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| preset 不在 PresetRegistry 中 | 输出 "Unknown preset: X"，exit FAILURE |

---

## preset:create

**签名**: `preset:create <name> [--skill NAME...] [--rule NAME...] [--agent NAME...]`

**正常行为**:

- 在 `abilities/_presets.json` 中创建新 preset 条目
- 初始 spec 包含传入的 skill/rule/agent 列表（去重）

**错误条件**:

| 条件 | 响应 |
|------|------|
| preset 名称已存在 | 输出 "Preset already exists: X"，exit FAILURE |

---

## preset:add-ability

**签名**: `preset:add-ability <preset> <ability> (--skill|--rule|--agent)`

**正常行为**:

- 向指定 preset 的对应类型列表中添加 ability 引用
- 若 ability 已存在于列表中，输出 `[ok] Ability already in preset` 并成功退出

**错误条件**:

| 条件 | 响应 |
|------|------|
| 未指定恰好一个类型 flag | 输出 "Specify exactly one of --skill, --rule, or --agent"，exit FAILURE |
| preset 不存在 | 输出 "Unknown preset: X"，exit FAILURE |

---

## preset:remove-ability

**签名**: `preset:remove-ability <preset> <ability> (--skill|--rule|--agent)`

**正常行为**:

- 从指定 preset 的对应类型列表中移除 ability 引用
- ability 不存在时静默成功

**错误条件**:

| 条件 | 响应 |
|------|------|
| 未指定恰好一个类型 flag | 输出 "Specify exactly one of --skill, --rule, or --agent"，exit FAILURE |
| preset 不存在 | 输出 "Unknown preset: X"，exit FAILURE |

---

## preset:delete

**签名**: `preset:delete <name>`

**正常行为**:

- 从 `abilities/_presets.json` 中删除指定 preset 条目

**错误条件**:

| 条件 | 响应 |
|------|------|
| preset 不存在 | 输出 "Unknown preset: X"，exit FAILURE |

---

## update

**签名**: `update`

**正常行为**:

- 将 AppConfig 中的 DEFAULT_SKILLS/RULES/AGENTS、KNOWN_PRESETS、KNOWN_TARGETS 写入 `~/.config/apm/knowledge-base.json`
- 输出写入路径

**错误条件**: 无特定错误条件（目录不存在时自动创建）。

---

## 通用行为

### target 参数

- 所有支持 `-t|--target` 的命令：无参数时默认 `['cursor', 'kiro']`
- 多次传入时去重
- 不在 KNOWN_TARGETS 中的值触发错误

### 默认值

- skill 类命令无参数时使用 `AppConfig::DEFAULT_SKILLS`
- rule 类命令无参数时使用 `AppConfig::DEFAULT_RULES`
- agent 类命令无参数时使用 `AppConfig::DEFAULT_AGENTS`
