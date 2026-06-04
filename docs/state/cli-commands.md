# CLI Commands

apm 所有已注册命令的签名、行为与错误条件。

---

## install

**签名**: `install <preset> [--scope project|user] [-t|--target TARGET...]`

**别名**: `add`（同命令；无 type 前缀的 `apm add <name>` 仅当 `<name>` 为已知 preset 时成功，否则输出 typed add 指引）

**正常行为**:

1. 解析 `--scope`（省略则 `project`）
2. `PresetRegistry::validatePresetInstall` 校验 preset 内各 ability bundle 对所选 target 存在
3. `ScopeGuard::assertBatchAllowed` 校验 user scope 下无 project-only 项
4. 从 PresetRegistry 获取 preset spec，对其中所有 ability 执行 `Installer::installTyped(..., $scope)`

**错误条件**:

| 条件 | 响应 |
|------|------|
| 未提供 preset（裸 `apm install` / `apm add`） | 输出迁移指引（`global-setup` / `bootstrap` / typed `add`），exit FAILURE |
| preset 为 `default` | 输出三步迁移文案，exit FAILURE |
| 非法 `--scope` | `Invalid deploy scope: ...`（含 Valid scopes 提示），exit FAILURE |
| user scope 含 project-only ability | `Project-only ability "..." cannot be deployed to user scope.`，exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 参数名不在已知 preset 列表 | 输出 typed add 指引（`apm add skill|rule|agent|preset <name>`），exit FAILURE |
| validatePresetInstall 失败（如缺失 bundle） | 输出校验错误，exit FAILURE（Guard 前，无磁盘写入） |
| 安装过程 ability 失败 | 输出 `[fail]` 行，最终 exit FAILURE |

---

## bootstrap

**签名**: `bootstrap [-t|--target TARGET...] [-f|--force]`

**正常行为**:

- 调用 `ProjectInitializer` 仅复制项目 scaffold（`docs/`、`issues/`、`AGENTS.md`）；不安装 preset 或 conventional ability
- 成功结束时提示在 agent chat 中执行 `/apm init`

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 目标已存在 scaffold 文件且无 `--force` | 输出错误信息（提示 `--force`），exit FAILURE |

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

**签名**: `skill:install [skills...] [--scope project|user] [-t|--target TARGET...]`

**别名**: `skill:add`

**正常行为**:

- 解析 `--scope`；user scope 下 project-only 项由 ScopeGuard 拒绝
- 无 skills 参数时使用 DEFAULT_SKILLS
- 对每个 skill 执行目录递归复制到目标平台 skills 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| 非法 `--scope` | 输出 Invalid deploy scope 消息，exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| skill 源目录不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## rule:install

**签名**: `rule:install [rules...] [--scope project|user] [-t|--target TARGET...]`

**别名**: `rule:add`

**正常行为**:

- 解析 `--scope`；user scope 下 project-only 项由 ScopeGuard 拒绝
- 无 rules 参数时使用 DEFAULT_RULES
- 按 `<name>.<target>.(mdc|md)` 查找源文件，复制到目标平台 rules 路径
- 保留源文件的子目录结构

**错误条件**:

| 条件 | 响应 |
|------|------|
| 非法 `--scope` | 输出 Invalid deploy scope 消息，exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| rule 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## agent:install

**签名**: `agent:install [agents...] [--scope project|user] [-t|--target TARGET...]`

**别名**: `agent:add`

**正常行为**:

- 解析 `--scope`；user scope 下 project-only 项由 ScopeGuard 拒绝
- 无 agents 参数时使用 DEFAULT_AGENTS
- 从 `abilities/agents/<name>.<target>.md` 复制到目标平台 agents 路径

**错误条件**:

| 条件 | 响应 |
|------|------|
| 非法 `--scope` | 输出 Invalid deploy scope 消息，exit FAILURE |
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| agent 源文件不存在 | 输出 `[fail] Missing ability bundle`，exit FAILURE |

---

## skill:uninstall

**签名**: `skill:uninstall [skills...] [--scope project|user] [-t|--target TARGET...] [-f|--force]`

**别名**: `skill:remove`

**正常行为**:

- 解析 `--scope`（省略 `project`）；`Installer::uninstallTyped(..., $scope)`



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

**签名**: `rule:uninstall [rules...] [--scope project|user] [-t|--target TARGET...] [-f|--force]`

**别名**: `rule:remove`

**正常行为**:

- 解析 `--scope`（省略 `project`）；`Installer::uninstallTyped(..., $scope)`

 同 skill:uninstall 逻辑，删除目标文件而非目录。

**错误条件**: 同 skill:uninstall。

---

## agent:uninstall

**签名**: `agent:uninstall [agents...] [--scope project|user] [-t|--target TARGET...] [-f|--force]`

**别名**: `agent:remove`

**正常行为**:

- 解析 `--scope`（省略 `project`）；`Installer::uninstallTyped(..., $scope)`

 同 skill:uninstall 逻辑，删除目标文件。

**错误条件**: 同 skill:uninstall。

---

## preset:uninstall

**签名**: `preset:uninstall <preset> [--scope project|user] [-t|--target TARGET...] [-f|--force]`

**别名**: `preset:remove`

**正常行为**:

1. 从 PresetRegistry 获取 preset spec
2. 通过 CheckService 检查所有 ability 的 drift 状态
3. 有 drift 且无 --force → 中止
4. 解析 `--scope`（省略 `project`）；无 drift 或有 --force → `Installer::uninstallTyped(..., $scope)`

**错误条件**:

| 条件 | 响应 |
|------|------|
| 非法 `--scope` | 输出 Invalid deploy scope 消息，exit FAILURE |
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
3. 输出卸载过程行；全部成功时提示可通过 `/apm init` 重装 project ability（user-scope `global-setup` 不变）

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

**签名**: `update`

**正常行为**:

- 将 AppConfig 中的 DEFAULT_SKILLS/RULES/AGENTS、KNOWN_PRESETS、KNOWN_TARGETS 写入 `~/.config/apm/knowledge-base.json`
- 输出写入路径

**错误条件**: 无特定错误条件（目录不存在时自动创建）。

---

## global-setup

**签名**: `global-setup [-t|--target TARGET...] [-f|--force]`

**正常行为**:

- 从 AbilityRegistry 的 Global Setup List 读取条目，仅安装到 **User Scope**（用户主目录下的 deploy root）
- 不接受 preset 或 `--scope` 参数；可在任意工作目录执行
- 无 `-t` 时默认 `['cursor', 'kiro']`；某条目在指定 target 无对应源时输出 `[skip]` 及原因，不因此失败
- 已安装且未传 `--force` 时幂等跳过；传 `--force` 时从包内 baseline 覆盖用户 scope 已安装文件
- 成功结束时提示在业务仓库中执行 `/apm init`

**错误条件**:

| 条件 | 响应 |
|------|------|
| target 不在 KNOWN_TARGETS 中 | 输出错误信息，exit FAILURE |
| 安装过程中出现不可恢复错误 | 输出 `[fail]` 等行，exit FAILURE |

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
