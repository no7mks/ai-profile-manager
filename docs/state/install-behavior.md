# Install Behavior

每种 ability 类型的安装、检查、卸载逻辑。

---

## Skill

### 安装

- 源路径: `<packageRoot>/<targets[target]>`（Source-is-Target 模式，abilities.yaml targets 字段定义）
- 目标路径: `<workspace>/<targets[target]>`
  - Cursor: `.cursor/skills/<name>/`
  - Kiro: `.kiro/skills/<name>/`
- 操作: DirectoryMirrorService 递归复制整个目录（含子目录与文件），已存在则覆盖

### 检查

- 通过 AbilityDiffService 对比 baseline 目录与已安装目录
- 状态模型:
  - `unchanged`: 所有文件内容一致
  - `modified`: 存在内容差异
  - `missing`: 目标目录不存在

### 卸载

- 操作: 递归删除整个目标目录
- 目标不存在时输出 `[miss]`，不视为错误
- drift 检查: 卸载前通过 CheckService 检查状态，有 modified 且无 --force 则中止

---

## Rule

### 安装

- 源路径: `<packageRoot>/<targets[target]>`（Source-is-Target 模式，abilities.yaml targets 字段定义）
- 目标路径: `<workspace>/<targets[target]>`
  - Cursor: `.cursor/rules/[category/]<name>.mdc`
  - Kiro: `.kiro/steering/[category/]<name>.md`
- 操作: 单文件复制（覆盖）

### 检查

- 通过 AbilityDiffService 对比 baseline 文件与已安装文件
- 状态模型: 同 Skill

### 卸载

- 操作: 在目标 rules/steering 目录下递归搜索匹配文件并删除
- 目标不存在时输出 `[miss]`
- drift 检查: 同 Skill

---

## Agent

### 安装

- 源路径: `<packageRoot>/<targets[target]>`（Source-is-Target 模式，abilities.yaml targets 字段定义）
- 目标路径: `<workspace>/<targets[target]>`
  - Cursor: `.cursor/agents/<name>.md`
  - Kiro: `.kiro/agents/<name>.md`
- 操作: 单文件复制（覆盖）

### 检查

- 通过 AbilityDiffService 对比 baseline 文件与已安装文件
- 状态模型: 同 Skill

### 卸载

- 操作: 删除目标文件
- 目标不存在时输出 `[miss]`
- drift 检查: 同 Skill

---

## Hook

### Kiro 平台

**安装**:
- 源文件: `<packageRoot>/<targets[target]>`（Source-is-Target 模式，abilities.yaml targets 字段定义）
- 目标文件: `<workspace>/<targets[target]>`（如 `.kiro/hooks/<name>.kiro.hook`）
- 操作: 文件复制；目标目录不存在时自动创建
- 源文件不存在时返回 fail

**检查**:
- 逐字节对比源文件与目标文件内容
- 状态:
  - `ok`: 内容完全一致
  - `drift`: 内容有差异
  - `missing`: 目标文件不存在

**卸载**:
- drift 检查: 对比源文件与目标文件，有 drift 且无 --force 则中止
- missing 时跳过（输出 `[skip]`）
- 操作: 删除目标文件


### Cursor 平台

**安装**（两步操作）:
1. 递归复制源目录到目标目录
   - 源目录: `<packageRoot>/<targets[target]>`（Source-is-Target 模式，abilities.yaml targets 字段定义，如 `.cursor/hooks/<name>/`）
   - 目标目录: `<workspace>/<targets[target]>`（如 `.cursor/hooks/<name>/`）
2. 读取 `<name>.json` 并 merge 条目到 `.cursor/hooks.json`
   - hooks.json 格式: `{"version": 1, "hooks": {"<event_type>": [{"command": "...", ...}]}}`
   - 去重规则: 以 (event_type, command) 为唯一标识，已存在则跳过
   - hooks.json 不存在时自动创建初始结构

**检查**（三项全部通过才为 ok）:
1. 目标目录存在
2. 入口脚本 `<name>/<name>.sh` 存在
3. hooks.json 中包含 `<name>.json` 声明的所有条目
- 任一项不满足 → `missing`
- 全部满足 → `ok`
- Cursor 平台无 drift 概念

**卸载**（两步操作）:
1. 读取 `<name>.json`，从 hooks.json 中移除匹配条目（按 command 字段匹配）
2. 递归删除整个目标目录
- 目标目录不存在时跳过（输出 `[skip]`）

**异常**:
- hooks.json 存在但内容为无效 JSON → 抛出 HookRegistryException

---

## Gitignore

### 安装

- 模板文件: `<packageRoot>/.gitignore`
- 操作:
  1. GitIgnoreTemplateService.renderManagedBlock() 从模板中按 ability key 和 target 匹配 `@apm:block`
  2. GitIgnoreTemplateService.mergeManagedSection() 将渲染结果插入/更新 `.gitignore` 的 managed section
- 无匹配 block 时输出 `[skip]`

### 检查

- gitignore 类型当前不参与 check 流程

### 卸载

- gitignore 类型当前不参与 uninstall 流程

---

## 通用行为

### Baseline 解析

ComposerBaselineResolver 按以下优先级定位 apm 包安装路径：
1. 环境变量 `APM_BASELINE_ROOT`（目录存在时使用）
2. 构造函数注入的 overrideInstallPath
3. 全局 Composer `~/.composer/vendor/composer/installed.json` 中查找包名

Baseline 不可用时，CheckService 返回所有 ability 状态为 `unknown`。

### Exit Code 规则

- CheckService.evaluateExitCode(): 结果中存在 `modified` 或 `missing` → exit 2；否则 → exit 0
- Installer.installTyped(): 任一 ability 安装失败 → exit 1；全部成功 → exit 0
