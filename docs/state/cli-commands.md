# CLI Commands

apm 所有已注册命令的签名、行为与错误条件。

---

## install

**签名**: `install <preset> [-t|--target TARGET...]`

**别名**: `add`（同命令；无 type 前缀的 `apm add <name>` 仅当 `<name>` 为已知 preset 时成功，否则输出 typed add 指引）

**正常行为**:

1. `PresetRegistry::validatePresetInstall` 校验 preset 内各 ability bundle 对所选 target 存在
2. 从 PresetRegistry 获取 preset spec，对其中所有 ability 执行 `Installer::installTyped(...)`

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| 未提供 preset（裸 `apm install` / `apm add`） | 输出迁移指引（`bootstrap` / typed `add`），exit FAILURE |
| preset 为 `default` | 输出两步迁移文案（bootstrap + add preset），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 参数名不在已知 preset 列表 | 输出 typed add 指引（`apm add skill|rule|agent|preset <name>`），exit FAILURE |
| validatePresetInstall 失败（如缺失 bundle） | 输出校验错误，exit FAILURE（无磁盘写入） |
| 安装过程 ability 失败 | 输出 `[fail]` 行，最终 exit FAILURE |

---

## bootstrap

**签名**: `bootstrap [-t|--target TARGET...] [-f|--force]`

**正常行为**:

1. 调用 `ProjectInitializer::init()` 执行 scaffold 搭建（`docs/`、`issues/`、`AGENTS.md`）
2. 读取 `bootstrap.includes` 列表，对每个 `type:path` 条目逐项安装到 project scope 的各 target 目录
3. 已安装且无 `--force` → 输出 `[skip]`；已安装且有 `--force` → 覆盖安装
4. 单项安装失败 → 输出 `[fail]`，继续剩余项
5. `bootstrap.includes` 为空或未定义时仅完成 scaffold
6. 全部成功（含 skip）→ exit 0；存在 fail → exit 1
7. 成功结束时提示在 agent chat 中执行 `/apm init`

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 目标已存在 scaffold 文件且无 `--force` | 输出错误信息（提示 `--force`），exit FAILURE |
| `bootstrap.includes` 引用不存在的 ability | 输出校验错误，exit FAILURE（前置校验，不执行安装） |

---

## show

**签名**: `show [-t|--target TARGET...] [--type TYPE]`

**正常行为**:

1. `ShowStatusPresenter` 枚举 registry 中的 **Conventional Ability**（skill / rule / agent / hook / gitignore / prompt）；**不**输出 preset 名或 scaffold 行
2. 对每个 ability 输出 **一行**，格式：`{type}:{name}  {status}   {targets}`；`targets` 为可读文本（如 `cursor, kiro`）
3. 状态相对 baseline / 磁盘探测：`not installed`、`installed`、`installed with local change`（**不**使用 update 的 `changed` / `up to date` 措辞）
4. 仅评估 project scope 下每个 ability 的安装状态
5. Check 内态 `unknown` 时由 `InstallationProbe` fallback 到 installed / not installed

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| --type 值不在 `[rule, agent, skill, hook, gitignore, prompt]` 中 | 输出 "Unknown type: X"，exit FAILURE |

---

## skill:install

**签名**: `skill:install [skills...] [-t|--target TARGET...]`

**别名**: `skill:add`

**正常行为**:

- 无 skills 参数时使用 DEFAULT_SKILLS
- 对每个 skill 执行目录递归复制到目标平台 skills 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| skill 源目录不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## rule:install

**签名**: `rule:install [rules...] [-t|--target TARGET...]`

**别名**: `rule:add`

**正常行为**:

- 无 rules 参数时使用 DEFAULT_RULES
- 按 `<name>.<target>.(mdc|md)` 查找源文件，复制到目标平台 rules 路径
- 保留源文件的子目录结构

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| rule 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## agent:install

**签名**: `agent:install [agents...] [-t|--target TARGET...]`

**别名**: `agent:add`

**正常行为**:

- 无 agents 参数时使用 DEFAULT_AGENTS
- 从 `abilities/agents/<name>.<target>.md` 复制到目标平台 agents 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| agent 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## skill:uninstall

**签名**: `skill:uninstall [skills...] [-t|--target TARGET...] [-f|--force]`

**别名**: `skill:remove`

**正常行为**:

- `Installer::uninstallTyped(...)`

1. 通过 CheckService 检查 drift 状态
2. 有 drift 且无 --force → 中止并输出 drift 详情
3. 无 drift 或有 --force → 删除目标目录

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 检测到 modified 且无 --force | 输出 drift 行 + "Detected modified items"，exit FAILURE |
| skill 目标目录不存在 | 输出 `[miss]`，继续执行 |

---

## rule:uninstall

**签名**: `rule:uninstall [rules...] [-t|--target TARGET...] [-f|--force]`

**别名**: `rule:remove`

**正常行为**:

- `Installer::uninstallTyped(...)`

 同 skill:uninstall 逻辑，删除目标文件而非目录。

**错误条件**: 同 skill:uninstall。

---

## agent:uninstall

**签名**: `agent:uninstall [agents...] [-t|--target TARGET...] [-f|--force]`

**别名**: `agent:remove`

**正常行为**:

- `Installer::uninstallTyped(...)`

 同 skill:uninstall 逻辑，删除目标文件。

**错误条件**: 同 skill:uninstall。

---

## preset:uninstall

**签名**: `preset:uninstall <preset> [-t|--target TARGET...] [-f|--force]`

**别名**: `preset:remove`

**正常行为**:

1. 从 PresetRegistry 获取 preset spec
2. 通过 CheckService 检查所有 ability 的 drift 状态
3. 有 drift 且无 --force → 中止
4. 无 drift 或有 --force → `Installer::uninstallTyped(...)`

**错误条件**:

| 条件 | 响应 |
|------|------|
| 传入 `--scope` 参数 | 输出废弃错误消息（`--scope` 已移除，仅 project scope），exit FAILURE |
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

## cleanup

**签名**: `cleanup`

**正常行为**:

1. 调用 `Installer::uninstallProjectScope()`：遍历 AbilityRegistry 中全部 skill、rule、agent、hook
2. 对每个 target 用 InstallationProbe 探测 project scope 是否已安装；已安装则执行对应卸载（hook 走 `uninstallHook`）
3. 输出卸载过程行；全部成功时提示可通过 `/apm init` 重装 project ability

**错误条件**:

| 条件 | 响应 |
|------|------|
| 任一卸载步骤失败 | 输出 `[fail]` 行，exit FAILURE |

---

## preset:create

**签名**: `preset:create <name> [--skill NAME...] [--rule NAME...] [--agent NAME...]`

**正常行为**:

- 在 abilities.yaml 的 presets section 中创建新 preset 条目
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

- 从 abilities.yaml 的 presets section 中删除指定 preset 条目

**错误条件**:

| 条件 | 响应 |
|------|------|
| preset 不存在 | 输出 "Unknown preset: X"，exit FAILURE |

---

## update

**签名**: `update [-f|--force]`

**正常行为**:

1. `GlobalInstallDetector` 要求从 **Global APM Installation**（Composer global 的 `vendor/bin/apm`）执行；否则报错并退出
2. `AbilityUpdateService::reportChanges()` 枚举 **Project Scope** 中已安装的 conventional ability（skill / rule / agent / hook），与 global baseline diff
3. 无 `--force`：仅输出 `changed: {type}:{name} {target}` 行，或 `All installed abilities are up to date.`
4. 有 `--force`：对每个 changed 项从 baseline 覆盖已安装文件
5. **不**写入 `knowledge-base.json`；**不**提供 preset 级覆盖

**错误条件**:

| 条件 | 响应 |
|------|------|
| 非 global 安装调用 | 提示使用 global `vendor/bin/apm`，exit FAILURE |
| baseline 不可解析 | `[fail] Baseline not found...`，exit FAILURE |

---

## global-setup

> ⚠️ DEPRECATED — 此命令将在下一主版本移除

**签名**: `global-setup [-t|--target TARGET...] [-f|--force]`

**正常行为**:

- 输出废弃错误消息（含已移除命令名 `global-setup` 与替代命令名 `apm bootstrap`），以非零退出码退出
- 不写入任何文件、不创建任何目录、不发起任何网络请求

**错误条件**:

| 条件 | 响应 |
|------|------|
| 任何调用 | 输出废弃消息，exit FAILURE |

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
