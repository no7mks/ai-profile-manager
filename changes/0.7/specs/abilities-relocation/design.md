# Design: PRP-001 Phase 2 — CLI 适配、Hook 支持与 State 文档补写

---

## Overview

本设计将 APM CLI 从旧的后缀解析 + Capture 架构迁移到完全基于 `abilities.yaml` 注册表驱动的新架构。核心变更：

1. **Installer / CheckService / ShowCommand** 统一从 `abilities.yaml` 顶层 section 名称判定 ability 类型，支持 hooks 新类型
2. **新增 HookInstaller** 处理 hook 类型的平台差异安装逻辑（Kiro = 文件复制，Cursor = JSON merge）
3. **移除 Capture/Ingest** 模块及其命令注册
4. **State 文档** 在代码改动完成后统一补写

---

## Architecture

### 模块结构（改动后）

| 模块 | 职责 | 变更类型 |
|------|------|----------|
| AbilityRegistry | 解析 abilities.yaml，按 section 分类返回 ability 列表 | **新增** |
| Installer | 分发安装/卸载请求到具体 type handler | 修改 |
| HookInstaller | hook 类型的安装/卸载逻辑（Kiro 文件复制 + Cursor JSON merge） | **新增** |
| CheckService | 对比已安装 ability 与源文件的 diff 状态 | 修改 |
| HookChecker | hook 类型的 check 逻辑（Kiro 文件对比 + Cursor 条目存在性） | **新增** |
| GitignoreManager | 操作 `.gitignore` 中的 Marker_Block | 不变 |
| ShowCommand | 展示所有 ability 类型（含 hooks） | 修改 |
| ConsoleRegistration | 命令注册（移除 Capture/Ingest） | 修改 |
| ~~Capture~~（移除） | ~~变更捕获~~ | 删除 |
| ~~CaptureService~~（移除） | ~~Capture 业务逻辑~~ | 删除 |

### 数据流

```
abilities.yaml
    │
    ▼
AbilityRegistry.parse()
    │
    ├─ rules[]   ─┐
    ├─ agents[]   │
    ├─ skills[]   ├──▶ Installer.installTyped() ──▶ 目标项目
    ├─ hooks[]   ─┤         │
    ├─ gitignore[] ┘         ├─ rule/agent: 文件复制
    └─ presets[]             ├─ skill: 目录递归复制
                             ├─ hook/kiro: HookInstaller.installKiro() → 文件复制
                             ├─ hook/cursor: HookInstaller.installCursor() → JSON merge
                             └─ gitignore: GitignoreManager

abilities.yaml
    │
    ▼
AbilityRegistry.parse()
    │
    ▼
CheckService.checkTyped()
    ├─ rule/agent/skill: AbilityDiffService（逐字节对比）
    ├─ hook/kiro: HookChecker.checkKiro()（文件对比）
    └─ hook/cursor: HookChecker.checkCursor()（条目存在性）
```

---

## Components and Interfaces

### AbilityRegistry

新增类，负责解析 `abilities.yaml` 并返回结构化 ability 列表。

```php
<?php
namespace AiProfileManager\Service;

final class AbilityRegistry
{
    /**
     * @param string $registryPath abilities.yaml 的绝对路径
     * @throws AbilityRegistryException 文件不存在、YAML 无效、或条目格式错误时抛出
     */
    public function __construct(private readonly string $registryPath) {}

    /**
     * 解析 abilities.yaml，返回按类型分组的 ability 列表。
     *
     * @return array{
     *     rules: list<AbilityEntry>,
     *     agents: list<AbilityEntry>,
     *     skills: list<AbilityEntry>,
     *     hooks: list<AbilityEntry>,
     *     gitignore: list<GitignoreEntry>,
     *     presets: list<PresetEntry>,
     * }
     * @throws AbilityRegistryException
     */
    public function parse(): array {}

    /**
     * 返回所有已知 section 名称。
     * @return list<string>
     */
    public static function knownSections(): array
    {
        return ['rules', 'agents', 'skills', 'hooks', 'gitignore', 'presets'];
    }
}
```

```php
<?php
namespace AiProfileManager\Service;

/**
 * 单个 ability 条目的值对象。
 */
final readonly class AbilityEntry
{
    /**
     * @param string $path 源文件相对路径（相对于 baseline root）
     * @param string $description 描述
     * @param array<string, string> $targets platform => target_path 映射
     * @param string $type 类型（rule|agent|skill|hook）
     */
    public function __construct(
        public string $path,
        public string $description,
        public array $targets,
        public string $type,
    ) {}
}
```

```php
<?php
namespace AiProfileManager\Service;

final class AbilityRegistryException extends \RuntimeException
{
    /** @param list<string> $errors */
    public static function validationErrors(array $errors): self
    {
        return new self("Ability registry validation failed:\n" . implode("\n", $errors));
    }

    public static function fileNotFound(string $path): self
    {
        return new self("Ability registry not found: {$path}");
    }

    public static function invalidYaml(string $path, string $reason): self
    {
        return new self("Invalid YAML in {$path}: {$reason}");
    }
}
```

### HookInstaller

新增类，处理 hook 类型 ability 的安装/卸载。

```php
<?php
namespace AiProfileManager\Service;

final class HookInstaller
{
    /**
     * 安装 hook 到 Kiro 平台（文件复制）。
     *
     * @param string $sourcePath hook 源文件绝对路径（.kiro.hook 格式）
     * @param string $targetPath 目标文件绝对路径（如 .kiro/hooks/xxx.kiro.hook）
     * @return array{status: string, message: string}
     */
    public function installKiro(string $sourcePath, string $targetPath): array {}

    /**
     * 安装 hook 到 Cursor 平台。
     * 两步操作：1) 递归复制 hook 目录；2) 读取 <name>.json 并 merge 条目到 hooks.json。
     *
     * @param string $sourceDir hook 源目录绝对路径（baseline/hooks/<name>/）
     * @param string $targetDir hook 目标目录绝对路径（workspace/.cursor/hooks/<name>/）
     * @param string $hookRegistryPath hooks.json 绝对路径
     * @return array{status: string, message: string}
     * @throws HookRegistryException hooks.json 存在但 JSON 无效时抛出
     */
    public function installCursor(
        string $sourceDir,
        string $targetDir,
        string $hookRegistryPath,
    ): array {}

    /**
     * 从 Kiro 平台卸载 hook（删除文件）。
     *
     * @param string $targetPath 目标文件绝对路径
     * @return array{status: string, message: string}
     */
    public function uninstallKiro(string $targetPath): array {}

    /**
     * 从 Cursor 平台卸载 hook。
     * 两步操作：1) 读取 <name>.json 并从 hooks.json 移除匹配条目；2) 删除整个 hook 目录。
     *
     * @param string $targetDir hook 目标目录绝对路径（workspace/.cursor/hooks/<name>/）
     * @param string $hookRegistryPath hooks.json 绝对路径
     * @return array{status: string, message: string}
     * @throws HookRegistryException hooks.json 存在但 JSON 无效时抛出
     */
    public function uninstallCursor(
        string $targetDir,
        string $hookRegistryPath,
    ): array {}
}
```

```php
<?php
namespace AiProfileManager\Service;

final class HookRegistryException extends \RuntimeException
{
    public static function invalidJson(string $path): self
    {
        return new self("Invalid JSON in hook registry: {$path}");
    }
}
```

### HookChecker

新增类，处理 hook 类型 ability 的 check 逻辑。

```php
<?php
namespace AiProfileManager\Service;

final class HookChecker
{
    /**
     * 检查 Kiro 平台 hook 状态（文件内容对比）。
     *
     * @param string $sourcePath 源文件绝对路径
     * @param string $targetPath 已安装文件绝对路径
     * @return string "ok"|"drift"|"missing"
     */
    public function checkKiro(string $sourcePath, string $targetPath): string {}

    /**
     * 检查 Cursor 平台 hook 状态。
     * 三项检查：1) 目录存在；2) 入口脚本存在；3) hooks.json 中条目存在。
     *
     * @param string $targetDir hook 目标目录绝对路径（workspace/.cursor/hooks/<name>/）
     * @param string $hookRegistryPath hooks.json 绝对路径
     * @return string "ok"|"missing"
     */
    public function checkCursor(string $targetDir, string $hookRegistryPath): string {}
}
```

### Installer（修改）

现有 `Installer` 类需要以下变更：

1. 构造函数注入 `AbilityRegistry` 和 `HookInstaller`
2. `installTyped()` 的 `$items` 参数扩展为包含 `hooks` 键
3. `installTyped()` 内部对 hook 类型分发到 `HookInstaller`
4. `uninstallTyped()` 同理
5. `listAvailableItems()` 返回值增加 `hooks` 键
6. 移除所有后缀解析逻辑（`findRuleSourceFiles` 中的后缀匹配）
7. 新增 `--force` 选项传递到 uninstall 流程

```php
// 修改后的签名
public function __construct(
    private readonly AbilityRegistry $registry,
    private readonly HookInstaller $hookInstaller,
    private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
    // ...existing deps
) {}

/**
 * @param array{
 *     skills: list<string>,
 *     rules: list<string>,
 *     agents: list<string>,
 *     hooks: list<string>,
 * } $items
 * @param list<string> $targets
 * @return array{lines: list<string>, exit_code: int}
 */
public function installTyped(array $items, array $targets, ?string $presetName = null): array {}
```

### CheckService（修改）

1. 构造函数注入 `HookChecker`
2. `checkTyped()` 的 `$items` 参数扩展为包含 `hooks` 键
3. hook 类型的 check 分发到 `HookChecker`
4. 状态映射统一：`unchanged` → `ok`，`modified` → `drift`，`missing` → `missing`

```php
public function __construct(
    private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
    private readonly AbilityDiffService $diffService = new AbilityDiffService(),
    private readonly HookChecker $hookChecker = new HookChecker(),
) {}

/**
 * @param array{
 *     skills: list<string>,
 *     rules: list<string>,
 *     agents: list<string>,
 *     hooks: list<string>,
 * } $items
 */
public function checkTyped(array $items, array $targets): array {}
```

### ShowCommand（修改）

1. `execute()` 中增加 `renderTypeSection('Hooks', 'hook', ...)` 调用
2. 新增 `--type` 选项支持类型过滤
3. 未知类型过滤值返回错误并列出已知类型

```php
protected function configure(): void
{
    $this->setName('show');
    $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
    $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by ability type (rule, agent, skill, hook, gitignore, preset).');
}
```

### ConsoleRegistration（修改）

移除 Capture/Ingest 相关命令注册和依赖注入：

```php
public static function register(
    SymfonyApplication $app,
    Installer $installer,
    CheckService $checker,
    KnowledgeBaseUpdater $updater,
): void {
    // 保留: InstallCommand, ShowCommand, Check*, Install*, Uninstall*, Preset*, UpdateCommand
    // 移除: CaptureCommand, SkillCaptureCommand, RuleCaptureCommand,
    //       AgentCaptureCommand, IngestCaptureChangeCommand
    // 移除参数: CaptureService $capture, CaptureChangeIngestor $ingestor
}
```

---

## Data Models

### abilities.yaml 格式（hooks 段）

```yaml
hooks:
  - path: check-write-length        # 基础名称标识
    description: 写入长度检查 hook
    targets:
      kiro: .kiro/hooks/check-write-length.kiro.hook
      cursor: .cursor/hooks/check-write-length/     # Cursor 侧为目录（含脚本+条目声明）
```

**源文件路径约定**（CR-1 决策）：

- Kiro 源文件：`hooks/<path>.kiro.hook`（相对于 baseline root），安装时整文件复制到 target 路径
- Cursor 源目录：`hooks/<path>/`（相对于 baseline root），目录结构如下：

```
hooks/<path>/
├── <path>.sh          # 入口脚本（约定入口）
├── <path>.json        # hooks.json 条目声明
└── ...                # 其他辅助文件（如 .py 脚本等）
```

### Cursor Hook 目录约定

每个 Cursor hook ability 对应一个子目录 `.cursor/hooks/<name>/`：

| 文件 | 用途 | 必须 |
|------|------|------|
| `<name>.sh` | 入口脚本，hooks.json 中 command 字段指向此文件 | 是 |
| `<name>.json` | hooks.json 条目声明（event + entry） | 是 |
| 其他文件 | 入口脚本引用的辅助脚本（如 .py、.js） | 否 |

### Cursor Hook 安装模型

Cursor hook 的安装涉及两个动作：

1. **目录递归复制**：将 baseline 中 `hooks/<name>/` 整个目录复制到 workspace 的 `.cursor/hooks/<name>/`
2. **hooks.json 条目注入**：读取 `.cursor/hooks/<name>/<name>.json`，将其声明的条目 merge 到 `.cursor/hooks.json`

### Cursor Hook 条目声明格式（`<name>.json`）

文件内容为 hooks.json 中 `hooks` 对象的一个片段——以事件类型为键，值为条目数组。安装时将此片段深度合并到 hooks.json 的 `hooks` 对象中。

```json
{
  "preToolUse": [
    {
      "command": ".cursor/hooks/check-write-length/check-write-length.sh",
      "matcher": "Write"
    }
  ]
}
```

- 顶层键为事件类型（如 `preToolUse`、`postToolUse` 等）
- 值为该事件类型下的条目数组（与 hooks.json 中 `hooks.<event_type>` 同级）
- 一个 hook ability 可声明多个事件类型下的条目（虽然通常只有一个）
- `command` 字段指向已安装的入口脚本路径（`<target_dir>/<name>.sh`）

### Kiro Hook 源文件格式（`hooks/<path>.kiro.hook`）

直接使用 Kiro 官方 JSON 格式（安装时整文件复制）：

```json
{
  "enabled": true,
  "name": "Check Write Length (Command)",
  "version": "1",
  "when": { "type": "preToolUse", "toolTypes": ["fs_write"] },
  "then": { "type": "runCommand", "command": "echo '...'" }
}
```

### hooks.json（Cursor Hook_Registry_File）格式

```json
{
  "version": 1,
  "hooks": {
    "<event_type>": [
      { "command": "<script_path>", ...other_fields }
    ]
  }
}
```

### Cursor Hook 安装流程

```
1. 递归复制目录: baseline/hooks/<name>/ → workspace/.cursor/hooks/<name>/
2. 读取条目声明: workspace/.cursor/hooks/<name>/<name>.json
3. Deep merge 到 hooks.json:
   - 若 hooks.json 不存在 → 创建 {"version":1,"hooks":{}}
   - 遍历 <name>.json 中每个事件类型键:
     - 在 hooks.json 的 hooks[event_type] 数组中查找 command 相同的条目
     - 不存在 → 追加该条目到数组末尾
     - 已存在 → 跳过（去重）
```

### Cursor Hook 卸载流程

```
1. 读取条目声明: workspace/.cursor/hooks/<name>/<name>.json
2. 遍历 <name>.json 中每个事件类型键:
   - 从 hooks.json 的 hooks[event_type] 数组中移除 command 匹配的条目
3. 删除整个目录: workspace/.cursor/hooks/<name>/
```

### Cursor Hook Check 逻辑

```
1. 检查目录是否存在: workspace/.cursor/hooks/<name>/
2. 检查入口脚本是否存在: workspace/.cursor/hooks/<name>/<name>.sh
3. 读取 <name>.json，遍历每个事件类型键:
   - 检查 hooks.json 中 hooks[event_type] 是否存在 command 匹配的条目
4. 全部满足 → "ok"；任一不满足 → "missing"
```

### AbilityRegistry 解析结果内部表示

```php
// 解析后的内部结构
[
    'rules' => [AbilityEntry(...), ...],
    'agents' => [AbilityEntry(...), ...],
    'skills' => [AbilityEntry(...), ...],
    'hooks' => [AbilityEntry(...), ...],
    'gitignore' => [GitignoreEntry(...), ...],
    'presets' => [PresetEntry(...), ...],
]
```

---

## Correctness Properties

### Property 1: Registry Completeness

- **Validates: Requirements 1.1, 1.3**
- **条件**: abilities.yaml 文件存在且 YAML 合法
- **保证**: `AbilityRegistry.parse()` 返回的 ability 集合与 abilities.yaml 中所有已知 section 的条目一一对应，无遗漏无多余
- **违反后果**: 部分 ability 无法被 install/check/uninstall 命令操作，或出现幽灵条目

### Property 2: Type Determinism

- **Validates: Requirements 1.1, 1.4**
- **条件**: ability 条目存在于 abilities.yaml 的某个顶层 section 中
- **保证**: ability 类型完全由其所在的顶层 section 名称决定，不依赖文件后缀
- **违反后果**: 同一 ability 在不同命令中被识别为不同类型，导致安装/检查行为不一致

### Property 3: Install Idempotence

- **Validates: Requirements 2.1, 10.4**
- **条件**: 源文件内容未变更
- **保证**: 对同一 ability 连续执行两次 install，第二次的结果与第一次相同（文件内容不变，hooks.json 无重复条目）
- **违反后果**: hooks.json 中出现重复条目，或文件被不必要地重写导致时间戳变化

### Property 4: Uninstall Precision

- **Validates: Requirements 4.1, 10.6**
- **条件**: 多个 hook ability 共存于同一 Hook_Registry_File
- **保证**: 卸载 hook A 不影响 hook B 在 hooks.json 中的条目（以 (event_type, command) 二元组精确定位）
- **违反后果**: 卸载一个 hook 意外移除了其他 hook 的条目，导致功能丢失

### Property 5: Check Consistency

- **Validates: Requirements 3.1, 3.4**
- **条件**: 源文件和目标文件均可读取
- **保证**: check 结果仅取决于源文件与目标文件的当前状态，不依赖安装历史
- **违反后果**: 相同文件状态在不同时间点产生不同 check 结果，误导用户

### Property 6: Capture Removal Completeness

- **Validates: Requirements 6.4, 7.4**
- **条件**: Capture/Ingest 相关源文件已删除
- **保证**: 移除后，任何代码路径不引用已删除的 Capture/Ingest 类
- **违反后果**: 运行时 class not found 错误，或 autoloader 报错

### Property 7: Validation Exhaustiveness

- **Validates: Requirements 1.3, 8.6**
- **条件**: abilities.yaml 中存在多个格式错误的条目
- **保证**: 解析器收集所有格式错误后一次性报告，不在第一个错误处短路
- **违反后果**: 用户需多次修正-运行循环才能修复所有错误，体验差

---

## Error Handling

| 场景 | 行为 | Exit Code |
|------|------|-----------|
| abilities.yaml 不存在 | 终止命令，输出含文件路径的错误 | 1 |
| abilities.yaml YAML 解析失败 | 终止命令，输出含路径和原因的错误 | 1 |
| 条目缺少必填字段 | 收集所有错误，一次性报告后终止 | 1 |
| 源文件/目录不存在 | 终止当前 ability 安装，输出含路径的错误 | 1 |
| 文件写入失败 | 终止当前操作，输出含原因的错误 | 1 |
| skill 目录已存在（Directory_Duplicate） | 报告冲突路径，中止安装 | 1 |
| hooks.json 存在但 JSON 无效 | 返回错误，不修改文件 | 1 |
| 卸载时目标不存在 | 跳过，输出提示，继续处理其余 | 0 |
| 卸载时检测到 drift 且无 --force | 中止卸载 | 1 |
| check 发现 drift 或 missing | 正常报告 | 2 |
| baseline 无法解析 | 报告所有 ability 为 unknown | 0 |
| 未知 section 名称 | 忽略该 section，不中断 | 0 |

---

## Testing Strategy

### 单元测试

- `AbilityRegistryTest`: 解析正常 YAML、未知 section 忽略、缺失字段收集错误、文件不存在、YAML 无效
- `HookInstallerTest`: Kiro 安装/卸载、Cursor JSON merge（新建/追加/去重/移除）、hooks.json 无效 JSON
- `HookCheckerTest`: Kiro ok/drift/missing、Cursor ok/missing
- `InstallerTest`: hook 类型分发、Directory_Duplicate 中止、--force 卸载

### 集成测试

- 完整 install → check → uninstall 流程（含 hook 类型）
- Capture/Ingest 命令不可达验证
- abilities.yaml 多种错误组合的一次性报告

### 回归测试

- 现有 rule/agent/skill 的 install/check/uninstall 行为不变
- 现有 gitignore 行为不变
- 现有 preset 行为不变

---

## Impact Analysis

| 检查项 | 影响 |
|--------|------|
| 受影响的 state 文档 | `docs/state/architecture.md`（模块表新增 AbilityRegistry/HookInstaller/HookChecker，移除 Capture）；需新建 `cli-commands.md`、`abilities-model.md`、`install-behavior.md`、`gitignore.md` |
| 现有模块行为变化 | Installer: 构造函数签名变更，installTyped/uninstallTyped 参数扩展；CheckService: 同理；ShowCommand: 新增 Hooks 分区和 --type 过滤 |
| 数据模型变更 | abilities.yaml 新增 `hooks` section（向后兼容，旧文件无 hooks 段时返回空列表）；无旧数据迁移需求 |
| 外部系统交互变化 | 新增对 `.cursor/hooks.json` 的读写操作；新增对 `.kiro/hooks/` 目录的文件操作 |
| 配置项变更 | `AppConfig::KNOWN_TARGETS` 不变；需新增 `KNOWN_ABILITY_TYPES` 常量包含 hook |

---

## Alternatives Considered

### Alt 1: Hook 安装统一为文件复制（不做 JSON merge）

**方案**: Cursor 平台也采用独立文件方式，不合并到 hooks.json。

**落选理由**: Cursor IDE 要求所有 hook 集中在 `.cursor/hooks.json` 中，不支持独立 hook 文件。必须做 JSON merge。

### Alt 2: 在 Installer 内部直接处理 hook 逻辑（不抽取 HookInstaller）

**方案**: 将 hook 安装逻辑作为 Installer 的 private 方法。

**落选理由**: hook 的 Cursor 平台逻辑（JSON merge/条目移除）与现有文件复制逻辑差异较大，混入 Installer 会增加类复杂度。独立类便于测试和维护。

### Alt 3: 使用 AbilityRegistry 替代现有 Installer.listAvailableItems()

**方案**: 完全用 AbilityRegistry 替代 Installer 中的列表解析逻辑。

**采纳**: 这是本设计的选择。AbilityRegistry 作为单一解析入口，Installer 不再自行解析 abilities.yaml。

---

## Clarification Round

以下问题已在 design review 中确认。

---

### CR-1: Cursor Hook 源文件的存放位置

abilities.yaml 中 hook 的 `path` 字段指向源文件。对于双平台 hook，需要两份不同格式的源文件。

**Q:** 双平台 hook 的源文件应如何组织？

**A:** 基于方案 A 扩展 — Kiro 源文件为单文件 `hooks/<path>.kiro.hook`（整文件复制）。Cursor 源为目录 `hooks/<path>/`，内含入口脚本 `<path>.sh`、条目声明 `<path>.json`、以及任意辅助脚本。安装时整目录递归复制到 `.cursor/hooks/<path>/`，再将 `<path>.json` 中的条目 merge 到 `.cursor/hooks.json`。abilities.yaml 中 `targets.cursor` 指向目录路径（如 `.cursor/hooks/check-write-length/`）。

---

### CR-2: --force 选项的作用范围

Req 4 AC5 提到 drift 时需 --force 才能卸载。

**Q:** --force 选项应加在哪个命令层级？

**A:** 选择 C — 统一命令和类型命令都支持 --force 选项。

---

以下问题面向 tasks 阶段，需要在拆分 task 前确认。

---

### CR-3: Task 实现顺序偏好

设计涉及多个新增模块（AbilityRegistry、HookInstaller、HookChecker）和多个修改模块（Installer、CheckService、ShowCommand、ConsoleRegistration），以及 Capture/Ingest 的移除和 State 文档补写。

**Q:** 实现顺序是否有偏好？

- **A)** 先新增后修改：AbilityRegistry → HookInstaller → HookChecker → Installer 修改 → CheckService 修改 → ShowCommand → ConsoleRegistration → Capture 移除 → State 文档
- **B)** 先移除后新增：Capture/Ingest 移除 → AbilityRegistry → HookInstaller/HookChecker → Installer/CheckService 修改 → ShowCommand → State 文档
- **C)** 按功能流程：AbilityRegistry → Installer + HookInstaller（安装流程）→ CheckService + HookChecker（检查流程）→ ShowCommand → Capture 移除 → State 文档
- **D)** 其他偏好（请说明）

**A:** 选择 B — 先移除后新增：Capture/Ingest 移除 → AbilityRegistry → HookInstaller/HookChecker → Installer/CheckService 修改 → ShowCommand → State 文档

---

### CR-4: Capture/Ingest 移除的测试策略

Capture/Ingest 移除涉及删除源文件、测试文件和命令注册。移除后需确保无引用残留。

**Q:** 移除后的验证方式偏好？

- **A)** 删除后立即运行全量测试 + static analysis（phpstan/psalm），确认无引用错误
- **B)** 删除后仅运行全量测试，依赖 autoloader 报错发现残留引用
- **C)** 删除后运行全量测试 + 手动 grep 搜索已删除类名确认无残留
- **D)** 删除后运行全量测试即可，不做额外检查

**A:** 选择 C — 删除后运行全量测试 + 手动 grep 搜索已删除类名确认无残留

---

### CR-5: AbilityRegistry 与现有 Installer 的集成方式

设计中 AbilityRegistry 替代了 Installer.listAvailableItems() 的解析逻辑。但现有 Installer 可能在多处直接读取 abilities.yaml。

**Q:** 集成时是否一步到位替换所有调用点，还是分步渐进？

- **A)** 一步到位：创建 AbilityRegistry 后立即替换 Installer 中所有 abilities.yaml 解析逻辑，一个 task 完成
- **B)** 分步渐进：先创建 AbilityRegistry 并让 Installer 内部委托给它（保留旧接口签名），后续 task 再清理旧代码
- **C)** 先创建 AbilityRegistry 作为独立模块（含测试），再在单独 task 中修改 Installer 构造函数注入 AbilityRegistry

**A:** 选择 A — 一步到位：创建 AbilityRegistry 后立即替换 Installer 中所有 abilities.yaml 解析逻辑，一个 task 完成

---

### CR-6: State 文档补写的时机与粒度

Req 12 要求"所有代码改动完成后"补写 State 文档。但 5 份文档（architecture.md、cli-commands.md、abilities-model.md、install-behavior.md、gitignore.md）内容量较大。

**Q:** State 文档应作为一个 task 还是拆分为多个 task？

- **A)** 单个 task：所有 5 份文档在最后一个 task 中统一补写
- **B)** 按文档拆分：每份文档一个 task（共 5 个 task）
- **C)** 按时机拆分：architecture.md 在模块变更完成后立即更新，其余 4 份在最后统一补写
- **D)** 两个 task：architecture.md 更新 + 其余 4 份新建

**A:** 选择 A — 单个 task：所有 5 份文档在最后一个 task 中统一补写
