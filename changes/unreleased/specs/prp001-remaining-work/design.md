# Design Document: PRP-001 Remaining Work

## Overview

本设计将 apm CLI 的路径解析逻辑从已废弃的 `abilities/` 目录布局迁移到"源即目标"（Source-is-Target）模式。核心变更：

1. **Installer** — 从 `abilities.yaml` 的 `targets` 字段直接读取源路径，消除后缀发现、category 推导、preferred source 排序
2. **AbilityDiffService** — baseline 路径同样从 `targets` 字段解析，区分 `no-baseline` 与 `new` 状态
3. **PresetRegistry** — 从 `abilities.yaml` 的 `presets` section 读写，废弃 `abilities/_presets.json`
4. **GitIgnoreTemplateService** — 模板来源改为 package root 的 `.gitignore` 文件本身
5. **死代码清理** — 移除 capture/ingest 残留
6. **测试修复** — fixture 适配新布局，全套件零失败
7. **SSOT 文档同步** — `docs/state/` 与 `docs/design.md` 反映新路径逻辑
8. **apm init reference** — 补充详细流程文档

设计原则：`abilities.yaml` 的 `targets` 字段声明了每个 ability 在 package root 中的真实路径。安装逻辑简化为：

```
parse abilities.yaml → read $packageRoot/<targets[target]> → copy to $workspace/<targets[target]>
```

---

## Architecture

### 变更后数据流

```mermaid
graph TD
    A[abilities.yaml] -->|parse| B[AbilityRegistry]
    B -->|AbilityEntry with targets| C[Installer]
    B -->|AbilityEntry with targets| D[AbilityDiffService]
    B -->|presets section| E[PresetRegistry]
    
    C -->|source: packageRoot + targets[target]| F[DirectoryMirrorService]
    F -->|dest: workspace + targets[target]| G[Target Workspace]
    
    C -->|template: packageRoot/.gitignore| H[GitIgnoreTemplateService]
    H --> I[workspace/.gitignore]
    
    D -->|baseline: baselineRoot + targets[target]| J[AbilityDirectoryDiff]
    J --> K[Status Report]
    
    E -->|read/write presets section| A
```

### 路径解析模型（变更后）

| 操作 | 源路径 | 目标路径 |
|------|--------|----------|
| Install skill | `$packageRoot/<targets[target]>` (目录) | `$workspace/<targets[target]>` (目录) |
| Install agent | `$packageRoot/<targets[target]>` (文件) | `$workspace/<targets[target]>` (文件) |
| Install rule | `$packageRoot/<targets[target]>` (文件) | `$workspace/<targets[target]>` (文件) |
| Diff baseline | `$baselineRoot/<targets[target]>` | `$workspace/<targets[target]>` |
| Gitignore template | `$packageRoot/.gitignore` | `$workspace/.gitignore` |

### 消除的逻辑

- `findRuleSourceFiles()` — 后缀递归搜索
- `pickPreferredRuleSource()` / `pickPreferredRuleSourcePath()` — 后缀优先级排序
- `resolveInstallTargetRuleFile()` — category 推导
- `resolveRuleRelativePath()` — abilities/rules/ 前缀计算
- `diffForCapture()` — capture 残留方法
- `PRESETS_RELATIVE_PATH = 'abilities/_presets.json'` — JSON preset 存储

---

## Components and Interfaces

### Installer（重构）

```php
final class Installer
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly HookInstaller $hookInstaller,
        private readonly HookChecker $hookChecker,
        private readonly GitIgnoreTemplateService $gitIgnore,
        private readonly DirectoryMirrorService $mirror,
        private readonly ?string $packageRoot = null,
    );

    /**
     * 安装指定 ability 到目标平台。
     * 路径解析：从 AbilityRegistry 获取 AbilityEntry，
     * 源 = $packageRoot/<targets[target]>，目标 = $workspace/<targets[target]>
     */
    public function installTyped(array $items, array $targets, ?string $presetName = null): array;
}
```

**关键变更**：
- `installAbilityBundle()` 统一为：查找 AbilityEntry → 读取 `targets[$target]` → 构造源路径 → 复制
- 移除 `findRuleSourceFiles()`、`pickPreferredRuleSource()`、`resolveInstallTargetRuleFile()`
- 新增 `resolveSourcePath(AbilityEntry $entry, string $target): ?string` 内部方法
- 新增 `resolveDestPath(AbilityEntry $entry, string $target, string $workspace): string` 内部方法
- 当 `$entry->targets` 不包含请求的 target 时，跳过该 ability（不报错）

### AbilityDiffService（重构）

```php
final class AbilityDiffService
{
    public function __construct(
        private readonly AbilityDirectoryDiff $directoryDiff,
        private readonly AbilityRegistry $registry,
    );

    /**
     * 对比已安装 ability 与 baseline。
     * baseline 路径 = $baselineRoot/<targets[target]>
     * installed 路径 = $workspaceRoot/<targets[target]>
     */
    public function diffForInstalledTargets(
        array $items, 
        array $targets, 
        string $baselineRoot, 
        string $workspaceRoot
    ): array;
}
```

**关键变更**：
- 注入 `AbilityRegistry` 以获取 `targets` 映射
- `diffSkill/diffAgent/diffRule` 统一为：查找 AbilityEntry → `$baselineRoot/<targets[target]>` vs `$workspaceRoot/<targets[target]>`
- 移除 `diffForCapture()` 方法
- 移除 `resolveRuleRelativePath()`、`pickPreferredRuleSourcePath()`
- `resolveStatus()` 增加 `no-baseline`（baselineRoot 本身不存在）与 `new`（ability 在 baseline 中无对应路径）的区分

### PresetRegistry（重构）

```php
final class PresetRegistry
{
    public function __construct(
        private readonly AbilityRegistry $registry,
    );

    /**
     * 从 abilities.yaml presets section 读取所有 preset 定义。
     */
    public function allPresets(): array;

    /**
     * 获取单个 preset 的 ability 列表。
     * 解析 includes 中的 type:path 格式。
     */
    public function getPreset(string $name): ?array;

    /**
     * 创建新 preset 并写入 abilities.yaml。
     * 使用 symfony/yaml Yaml::dump() 整体重写文件。
     */
    public function createPreset(string $name, string $description, array $includes): void;

    /**
     * 删除 preset 并写入 abilities.yaml。
     */
    public function deletePreset(string $name): void;

    /**
     * 向 preset 添加 ability 引用。
     */
    public function addAbility(string $presetName, string $type, string $abilityPath): void;

    /**
     * 从 preset 移除 ability 引用。
     */
    public function removeAbility(string $presetName, string $type, string $abilityPath): void;
}
```

**关键变更**：
- 构造函数接收 `AbilityRegistry` 而非 `workspaceRoot`
- 移除 `PRESETS_RELATIVE_PATH` 常量
- 移除 `loadFromWorkspace()` / `saveToWorkspace()` JSON 读写
- 新增 YAML 读写：使用 `Yaml::parse()` 读取 + `Yaml::dump()` 整体重写（CR1 决策）
- `includes` 解析：`<type>:<path>` → 分离 type 和 path（第一个 `:` 为分隔符）
- 引用验证：preset 中引用的 ability 必须存在于 AbilityRegistry 对应 section 中

### GitIgnoreTemplateService（路径变更）

```php
// Installer 中的调用变更
private function installGitIgnore(array $items, array $targets, ?string $presetName): ?string
{
    // 变更前: $templatePath = getcwd() . '/abilities/gitignore/template.gitignore'
    // 变更后: $templatePath = $this->packageRoot . '/.gitignore'
    $templatePath = $this->packageRoot . '/.gitignore';
    // ... 其余逻辑不变
}
```

**设计决策（CR2）**：package root 的 `.gitignore` 文件本身即为模板来源。该文件同时包含：
- apm 自身使用的 gitignore 规则（文件顶部）
- `@apm:block` 条件渲染块（文件底部）
- 已渲染的 managed section（中间部分，安装到其他项目时不会被复制）

`renderManagedBlock()` 只提取 `@apm:block` 标记的内容，忽略文件其余部分，因此复用 `.gitignore` 作为模板是安全的。

---

## Data Models

### AbilityEntry（不变）

```php
final readonly class AbilityEntry
{
    public function __construct(
        public string $path,        // ability 标识名
        public string $description, // 人类可读描述
        public array $targets,      // platform => relative_path 映射
        public string $type,        // rule|agent|skill|hook
    ) {}
}
```

### Diff 状态模型（扩展）

| 状态 | 含义 | 触发条件 |
|------|------|----------|
| `unchanged` | 内容一致 | baseline 与 installed 文件完全相同 |
| `modified` | 内容有差异 | baseline 存在且与 installed 不同 |
| `missing` | 未安装 | baseline 存在但 installed 路径不存在 |
| `no-baseline` | 包未安装 | ComposerBaselineResolver 返回 null（baselineRoot 不可用） |
| `new` | 新增 ability | baselineRoot 存在但该 ability 的 targets[target] 路径在 baseline 中不存在 |
| `unknown` | 无法判断 | 保留用于其他异常情况 |

**CR3 决策**：区分 `no-baseline`（包未全局安装）和 `new`（ability 在 baseline 版本中不存在）。

### Preset YAML 结构（abilities.yaml 中）

```yaml
presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - rule:git:branch-overview
      - rule:git:git-conventions
      - skill:gitflow
```

`includes` 条目格式：`<type>:<path>`，其中 type 为 `skill`/`rule`/`agent`/`hook`，path 为 AbilityEntry.path。

### Includes 解析规则

分隔符为第一个 `:`（因为 path 本身可能包含 `:`，如 `git:branch-overview`）：
- `rule:git:branch-overview` → type=`rule`, path=`git:branch-overview`
- `skill:gitflow` → type=`skill`, path=`gitflow`

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Source-is-Target path symmetry

- **条件**: AbilityEntry 存在且其 `targets` 映射包含请求的 platform key
- **保证**: Installer 解析的源路径为 `$packageRoot/<targets[target]>`，目标路径为 `$workspace/<targets[target]>`，两侧相对路径完全相同
- **违反后果**: 安装后文件位置与 abilities.yaml 声明不一致，导致后续 diff/check 无法定位

**Validates: Requirements 1.1, 1.2, 1.3, 1.8**

### Property 2: Baseline path resolution from targets

- **条件**: AbilityEntry 存在且其 `targets` 映射包含请求的 platform key，baseline root 可用
- **保证**: AbilityDiffService 解析的 baseline 路径为 `$baselineRoot/<targets[target]>`，与 install target 使用相同相对路径
- **违反后果**: diff 对比路径错误，报告虚假的 modified/missing 状态

**Validates: Requirements 2.1, 2.2, 2.3**

### Property 3: Missing source produces [fail] with path

- **条件**: AbilityEntry 的 `targets[target]` 路径在 `$packageRoot` 下不存在于磁盘
- **保证**: Installer 产出包含 `[fail]` 和完整预期源路径字符串的结果
- **违反后果**: 静默跳过缺失 ability，用户无法发现配置错误

**Validates: Requirements 1.6**

### Property 4: Single-platform target graceful skip

- **条件**: AbilityEntry 的 `targets` 映射不包含请求的 platform key
- **保证**: Installer 跳过该 ability，不产出 error 或 [fail] 状态
- **违反后果**: 单平台 ability 在另一平台安装时报错，阻塞正常流程

**Validates: Requirements 1.7**

### Property 5: Baseline status distinction (no-baseline vs new)

- **条件**: 执行 ability check 操作
- **保证**: baseline root 不可用时状态为 `no-baseline`；baseline root 存在但该 ability 的 `targets[target]` 路径在 baseline 中不存在时状态为 `new`
- **违反后果**: 用户无法区分"包未安装"与"ability 是新增的"，影响问题诊断

**Validates: Requirements 2.6**

### Property 6: Modified detection on content difference

- **条件**: baseline 文件/目录存在，installed 文件/目录存在，且内容不同
- **保证**: AbilityDiffService 报告状态为 `modified`
- **违反后果**: 内容漂移未被检测，用户误以为 workspace 与 baseline 一致

**Validates: Requirements 2.7**

### Property 7: Preset includes round-trip parsing

- **条件**: preset includes 条目格式为 `<type>:<path>`（type 为 skill/rule/agent/hook，path 可能包含冒号）
- **保证**: PresetRegistry 正确解析为 type 和 path 组件，重新序列化后产出原始字符串
- **违反后果**: 包含冒号的 ability path（如 `git:branch-overview`）被错误截断

**Validates: Requirements 3.3, 3.5**

### Property 8: Invalid preset ability reference produces error

- **条件**: preset 的 `includes` 列表引用了 AbilityRegistry 对应 section 中不存在的 ability path
- **保证**: PresetRegistry 报告错误并标识具体的无效引用
- **违反后果**: 无效引用静默通过，安装时才发现 ability 不存在

**Validates: Requirements 3.6**

### Property 9: Non-matching gitignore keys produce [skip]

- **条件**: 提供的 ability keys 中没有任何一个匹配 gitignore 模板中的 `@apm:block` 标记
- **保证**: Installer 输出 `[skip]`，不报错
- **违反后果**: 无匹配 block 时报错中断安装流程

**Validates: Requirements 5.2, 5.4**

---

## Error Handling

### Installer 错误场景

| 场景 | 行为 | Exit Code |
|------|------|-----------|
| AbilityEntry 的 targets 不含请求的 target | 静默跳过，不输出 | 0（不影响） |
| 源路径不存在 | 输出 `[fail] Missing ability: <type> <name> (expected <path>)` | 1 |
| 文件复制失败（权限等） | 输出 `[fail] Install copy failed: <detail>` | 1 |
| Gitignore 模板不存在 | 输出 `[skip]`，继续 | 0 |
| AbilityRegistry 中找不到请求的 ability name | 输出 `[fail] Unknown ability: <type> <name>` | 1 |

### AbilityDiffService 错误场景

| 场景 | 行为 | Exit Code |
|------|------|-----------|
| Baseline root 不可用（ComposerBaselineResolver 返回 null） | 所有 ability 状态为 `no-baseline` | 2（环境错误，需修复） |
| Baseline root 存在但 ability 路径不存在 | 该 ability 状态为 `new` | 0（用户新增，不算 drift） |
| Installed 路径不存在 | 该 ability 状态为 `missing` | 2 |

### PresetRegistry 错误场景

| 场景 | 行为 |
|------|------|
| Preset name 已存在（create） | 抛出异常 / 输出错误 |
| Preset name 不存在（delete/get） | 返回 null 或抛出异常 |
| includes 引用不存在的 ability | 报告错误（含具体引用） |
| YAML dump 写入失败 | 抛出 RuntimeException |
| includes 格式无效（无 `:` 分隔符） | 报告解析错误 |

---

## Testing Strategy

### 测试分层

| 层级 | 覆盖范围 | 工具 |
|------|----------|------|
| Property-based tests | 路径解析对称性、状态判定、includes 解析 | PHPUnit + 自定义 data provider（模拟 PBT） |
| Unit tests | 各组件独立行为、边界条件 | PHPUnit |
| Integration tests | Installer + AbilityRegistry 协作、PresetRegistry YAML 读写 | PHPUnit + vfsStream |
| E2E tests | 完整 CLI 命令执行 | PHPUnit Process |

### Property-based testing 配置

本项目使用 PHP，PBT 库选择 **PhpQuickCheck**（或使用 PHPUnit DataProvider 模拟 100+ 随机输入）。

每个 property test 配置：
- 最少 100 次迭代
- 标签格式：`Feature: prp001-remaining-work, Property N: <property_text>`

### 测试 fixture 迁移

所有测试 fixture 必须从旧布局迁移到新布局：

| 旧路径 | 新路径 |
|--------|--------|
| `$packageRoot/abilities/skills/<name>/` | `$packageRoot/.cursor/skills/<name>/` 或 `$packageRoot/.kiro/skills/<name>/` |
| `$packageRoot/abilities/agents/<name>.<target>.md` | `$packageRoot/.cursor/agents/<name>.md` 或 `$packageRoot/.kiro/agents/<name>.md` |
| `$packageRoot/abilities/rules/<cat>/<name>.<target>.mdc` | `$packageRoot/.cursor/rules/<cat>/<name>.mdc` |
| `$baseline/abilities/skills/<name>/` | `$baseline/.cursor/skills/<name>/` 或 `$baseline/.kiro/skills/<name>/` |
| `abilities/_presets.json` | abilities.yaml presets section |
| `abilities/gitignore/template.gitignore` | `$packageRoot/.gitignore` |

### CR4 决策

所有测试必须通过，无论失败是否由本次变更引起。发现 pre-existing failure 时一并修复。

---

## Impact Analysis

### 受影响的 State 文档

| 文档 | 变更内容 |
|------|----------|
| `docs/state/install-behavior.md` | 所有源路径描述改为 `<packageRoot>/<targets[target]>`；gitignore 模板路径改为 `<packageRoot>/.gitignore` |
| `docs/state/architecture.md` | PresetRegistry 描述改为读取 abilities.yaml；数据流图移除 `abilities/_presets.json` |
| `docs/state/cli-commands.md` | preset:create/delete 描述改为操作 abilities.yaml |
| `docs/state/abilities-model.md` | 移除 "Preset 运行时存储（_presets.json）" section，改为说明 presets section 即为唯一存储 |
| `docs/state/gitignore.md` | 模板文件路径改为 `<packageRoot>/.gitignore` |
| `docs/design.md` | 能力路径约定全部改为 Source-is-Target 模式 |

### 现有模块行为变更

| 模块 | 变更 |
|------|------|
| `Installer` | 路径解析逻辑完全重写；移除 5 个私有方法；新增 AbilityRegistry 查询 |
| `AbilityDiffService` | 路径解析重写；移除 `diffForCapture()`；新增 `no-baseline`/`new` 状态；注入 AbilityRegistry |
| `PresetRegistry` | 完全重写：JSON → YAML；构造函数签名变更 |
| `CheckService` | 适配 AbilityDiffService 新状态值；`buildUnknownResults` 改为 `no-baseline` |
| `PresetCreateCommand` | 调用 PresetRegistry 新接口写入 YAML |
| `PresetDeleteCommand` | 调用 PresetRegistry 新接口写入 YAML |
| `GitIgnoreTemplateService` | 无代码变更，仅 Installer 传入的 templatePath 变更 |

### Data Model 变更

| 变更 | 影响 |
|------|------|
| Diff 状态新增 `no-baseline`、`new` | CheckService.renderResults() 需要新的 prefix 映射；evaluateExitCode() 需要决定这些状态的 exit code |
| PresetRegistry 存储格式从 JSON 变为 YAML | 写入格式变化（注释丢失、排序可能变化）；读取逻辑完全不同 |

### 外部系统交互变更

| 系统 | 变更 |
|------|------|
| Composer baseline（全局安装包） | baseline 内的 ability 路径从 `abilities/` 变为 `.cursor/`/`.kiro/`（需要 baseline 包也完成 Phase 1 迁移） |
| 用户 workspace | 安装目标路径不变（仍为 `.cursor/`/`.kiro/`） |

### 配置变更

| 配置 | 变更 |
|------|------|
| `AppConfig::PRESET_ITEMS` | 移除或标记为 deprecated（不再作为 fallback） |
| `Installer` 构造函数 | 移除 `$templatePath` 参数（改为内部计算） |
| `PresetRegistry` 构造函数 | 从 `string $workspaceRoot` 改为 `AbilityRegistry $registry` |

---

## Alternatives Considered

### 路径解析方案

| 方案 | 优点 | 缺点 | 决策 |
|------|------|------|------|
| A) 从 targets 字段直接读取（选定） | 零转换逻辑、与 abilities.yaml 声明一致 | 依赖 abilities.yaml 正确性 | ✅ 选定 |
| B) 保留 abilities/ 目录但更新路径映射 | 向后兼容 | 违背 PRP-001 Phase 1 已完成的迁移 | ❌ |
| C) 引入路径映射配置文件 | 灵活 | 增加复杂度、多一个配置源 | ❌ |

### PresetRegistry 存储方案

| 方案 | 优点 | 缺点 | 决策 |
|------|------|------|------|
| A) symfony/yaml dump 整体重写（选定） | 实现简单、单一数据源 | 注释丢失、格式可能变化 | ✅ CR1 决策 |
| B) 局部替换（正则/行定位） | 保留格式 | 实现复杂、易出错 | ❌ |
| C) 保留注释的 YAML 库 | 格式完全不变 | PHP 生态无成熟方案 | ❌ |

### Gitignore 模板来源

| 方案 | 优点 | 缺点 | 决策 |
|------|------|------|------|
| A) package root .gitignore 本身（选定） | 源即目标原则延伸、无额外文件 | 模板与实际 gitignore 混合 | ✅ CR2 决策 |
| B) abilities.yaml 中声明路径 | 与其他 ability 一致 | 需要 schema 变更 | ❌ |
| C) 固定路径 templates/gitignore.template | 清晰分离 | 需要新建目录和文件 | ❌ |

### Baseline 状态区分

| 方案 | 优点 | 缺点 | 决策 |
|------|------|------|------|
| A) 统一 unknown | 简单 | 用户无法区分原因 | ❌ |
| B) 区分 no-baseline / new（选定） | 信息更丰富 | 需要扩展状态模型 | ✅ CR3 决策 |
| C) verbose 模式下才区分 | 默认简洁 | 实现复杂度相同但收益低 | ❌ |


---

## Clarification Round

> **CR5**: Installer、AbilityDiffService、PresetRegistry 三个核心模块的重构实现顺序是否有偏好？它们之间存在依赖关系（PresetRegistry 依赖 AbilityRegistry，Installer 也依赖 AbilityRegistry），拆分 task 时需要确定顺序。
>
> - A) 按依赖链自底向上：先 PresetRegistry（最独立），再 AbilityDiffService，最后 Installer
> - B) 按影响面从大到小：先 Installer（涉及最多方法移除），再 AbilityDiffService，最后 PresetRegistry
> - C) 按测试可验证性：先完成所有三个模块的接口变更，再统一修复测试
> - D) 无偏好，由 task agent 根据代码依赖自行决定
>
> **A:** B) 按影响面从大到小：先 Installer，再 AbilityDiffService，最后 PresetRegistry。

> **CR6**: CheckService 需要适配 `no-baseline` 和 `new` 两个新状态。这两个状态的 exit code 策略是什么？
>
> - A) 两者均为 exit 0（信息性状态，不算失败）
> - B) `no-baseline` 为 exit 0，`new` 为 exit 1（新增 ability 未在 baseline 中出现可能表示配置问题）
> - C) 两者均为 exit 1（任何非 unchanged 状态都算 drift）
> - D) 可配置：默认 exit 0，通过 `--strict` flag 改为 exit 1
>
> **A:** `no-baseline` → exit 2（等同 modified/missing，baseline 包未安装属于环境错误，应报错让用户修复）；`new` → exit 0（ability 在 baseline 中不存在表示用户自行新增，不算 drift）。

> **CR7**: PresetRegistry 写入 abilities.yaml 时，是整体重写整个文件（包括 abilities section），还是仅重写 presets section？
>
> - A) 整体重写整个文件（Yaml::parse 读取全部 → 修改 presets → Yaml::dump 写回全部）
> - B) 仅解析和重写 presets section（通过定位 `presets:` 行，替换该 section 内容）
> - C) 使用 AbilityRegistry 提供的写入接口，让 AbilityRegistry 负责文件 I/O
>
> **A:** A) 整体重写整个文件（Yaml::parse 读取全部 → 修改 presets → Yaml::dump 写回全部）。

> **CR8**: 死代码清理（R4）和测试修复（R6）是否应作为独立 task，还是与对应模块重构合并？
>
> - A) 独立 task：先清理死代码（一个 task），再做模块重构，最后统一修复测试（一个 task）
> - B) 合并：每个模块重构 task 内同时清理该模块的死代码并修复对应测试
> - C) 混合：死代码清理独立（因为跨模块），测试修复与模块重构合并
>
> **A:** A) 独立 task：先清理死代码，再做模块重构，最后统一修复测试。
