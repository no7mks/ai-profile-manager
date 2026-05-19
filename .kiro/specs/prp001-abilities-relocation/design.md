# Design Document: Abilities Relocation

## Overview

本设计覆盖 PRP-001 的完整实现：Phase 1 文件迁移验证、Phase 2 代码适配（Installer/CheckService/GitignoreManager 重构）、capture/ingest 移除、以及 state 文档补写。

核心设计思路：
1. **abilities.yaml 成为唯一注册表** — 所有 ability 的路径映射、gitignore 内容均从此文件读取
2. **路径前缀替代后缀解析** — target 判定完全依赖 abilities.yaml 中 `targets` 字段的路径前缀（`.cursor/` / `.kiro/`）
3. **PackagePaths 作为 source resolution 锚点** — abilities.yaml 和 ability 源文件均从 apm package root 读取（通过 `PackagePaths::packageRoot()` 定位）
4. **直接删除 capture/ingest** — 无兼容层，Preset 命令重构为依赖 PresetRegistry 而非 CaptureService

---

## Architecture

### 模块职责（改造后）

```
┌─────────────────────────────────────────────────────────────┐
│                        CLI Commands                          │
│  install, check, show, update, rule:*, agent:*, skill:*,    │
│  preset:*, uninstall                                        │
└────────────────────────────┬────────────────────────────────┘
                             │
              ┌──────────────┼──────────────────┐
              ▼              ▼                   ▼
┌──────────────────┐ ┌──────────────┐ ┌──────────────────────┐
│ AbilitiesRegistry│ │   Installer  │ │    CheckService      │
│ (new: YAML parse)│ │              │ │                      │
└────────┬─────────┘ └──────┬───────┘ └──────────┬───────────┘
         │                   │                    │
         ▼                   ▼                    ▼
┌──────────────────────────────────────────────────────────────┐
│                    Service Layer                              │
│  DirectoryMirrorService, GitIgnoreTemplateService,           │
│  AbilityDiffService, PresetRegistry, ProjectInitializer,     │
│  ComposerBaselineResolver, KnowledgeBaseUpdater              │
└──────────────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────────┐
│                    Config Layer                               │
│  AppConfig, PackagePaths                                     │
└──────────────────────────────────────────────────────────────┘
```

### 关键变更

| 变更 | 说明 |
|------|------|
| 新增 `AbilitiesRegistry` | 解析 abilities.yaml，提供 ability 查询接口 |
| 重构 `Installer` | 从 AbilitiesRegistry 获取 ability 定义，不再扫描 `abilities/` 目录 |
| 重构 `CheckService` / `AbilityDiffService` | 从 AbilitiesRegistry 获取 expected paths，不再使用后缀匹配 |
| 重构 `GitIgnoreTemplateService` | 从 AbilitiesRegistry 获取 gitignore content，不再读取模板文件 |
| 重构 `ProjectInitializer` | 从 package root 硬编码路径读取 scaffold，不再依赖 `scaffold/` 目录 |
| 重构 Preset 命令 | 依赖 PresetRegistry 替代 CaptureService |
| 删除 `src/Capture/` | 整个目录及 CaptureService 移除 |
| 删除 capture/ingest 命令 | 6 个命令文件移除 |
| 重构 `ConsoleRegistration` | 移除 CaptureService/CaptureChangeIngestor 参数 |

---

## Components and Interfaces

### AbilitiesRegistry（新增）

负责解析 abilities.yaml 并提供结构化查询接口。

```php
namespace AiProfileManager\Config;

final class AbilitiesRegistry
{
    public function __construct(string $yamlPath);
    public static function fromPackageRoot(): self;

    /** @return array<int, AbilityEntry> */
    public function rules(): array;
    /** @return array<int, AbilityEntry> */
    public function agents(): array;
    /** @return array<int, AbilityEntry> */
    public function skills(): array;
    /** @return array<int, GitignoreEntry> */
    public function gitignoreEntries(): array;
    /** @return array<int, PresetEntry> */
    public function presets(): array;

    public function findAbility(string $type, string $path): ?AbilityEntry;
    public function findGitignore(string $marker): ?GitignoreEntry;
    public function findPreset(string $name): ?PresetEntry;

    /**
     * 解析 preset includes 为具体 ability 列表
     * @return array{rules: list<string>, agents: list<string>, skills: list<string>, gitignore: list<string>}
     */
    public function resolvePresetAbilities(string $presetName): array;
}
```

### AbilityEntry（新增 Value Object）

```php
namespace AiProfileManager\Config;

final readonly class AbilityEntry
{
    public function __construct(
        public string $type,        // 'rule' | 'agent' | 'skill'
        public string $path,        // abilities.yaml 中的 path 标识符
        public string $description,
        public array $targets,      // ['cursor' => '.cursor/...', 'kiro' => '.kiro/...']
    );

    public function isDirectoryLevel(): bool; // targets 值以 '/' 结尾
    public function targetPlatforms(): array; // ['cursor', 'kiro'] 或子集
}
```

### GitignoreEntry（新增 Value Object）

```php
namespace AiProfileManager\Config;

final readonly class GitignoreEntry
{
    public function __construct(
        public string $marker,
        public string $description,
        public string $content,     // 多行 gitignore pattern（CR-1 决策：内联存储）
    );
}
```

### PresetEntry（新增 Value Object）

```php
namespace AiProfileManager\Config;

final readonly class PresetEntry
{
    public function __construct(
        public string $name,
        public string $description,
        public array $includes,     // 'type:path' 格式引用列表
    );
}
```

### Installer（重构）

```php
final class Installer
{
    public function __construct(
        private readonly AbilitiesRegistry $registry,
        private readonly string $packageRoot,
        private readonly DirectoryMirrorService $mirror,
        private readonly GitIgnoreTemplateService $gitignoreService,
    );

    /**
     * 安装指定 abilities 到目标项目
     * @param array<int, string> $abilityPaths path 标识符列表
     * @param array<int, string> $targets ['cursor', 'kiro']
     */
    public function installAbilities(array $abilityPaths, array $targets): array;

    /**
     * 安装 gitignore marker blocks
     * @param array<int, string> $markers
     */
    public function installGitignore(array $markers): array;

    /** 安装 preset（解析 includes 后批量安装） */
    public function installPreset(string $presetName, array $targets): array;

    /** 卸载指定 abilities */
    public function uninstallAbilities(array $abilityPaths, array $targets): array;
}
```

**安装流程**：
1. 从 AbilitiesRegistry 查找 ability entry
2. 若未找到 → 报错并跳过
3. 遍历 entry.targets，过滤出用户请求的 targets
4. 对每个 target path：
   - 验证路径前缀（`.cursor/` 或 `.kiro/`），不合法则报错跳过
   - 计算源路径：`packageRoot + targetPath`
   - 计算目标路径：`projectRoot + targetPath`
   - 若目标目录不存在 → 自动创建并输出 notice（CR-4 决策）
   - 若为 directory-level（skill）→ `DirectoryMirrorService::mirrorDirectory()`
   - 若为 file-level（rule/agent）→ `DirectoryMirrorService::copyFile()`

### CheckService（重构）

```php
final class CheckService
{
    public function __construct(
        private readonly AbilitiesRegistry $registry,
        private readonly string $packageRoot,
        private readonly AbilityDiffService $diffService,
    );

    /** @return array<int, CheckResult> */
    public function checkAbilities(array $abilityPaths, array $targets): array;
    /** @return array<int, CheckResult> */
    public function checkAll(array $targets): array;
}
```

**检查流程**：
1. 从 AbilitiesRegistry 查找 ability entry
2. 若未找到 → 返回 status=unknown
3. 遍历 entry.targets，过滤出用户请求的 targets
4. 对每个 target path：
   - 源路径：`packageRoot + targetPath`
   - 目标路径：`projectRoot + targetPath`
   - 若为 directory-level → `diffSkillDirectory()` 返回 status + extra_files
   - 若为 file-level → byte-for-byte 内容比较

### CheckResult（新增 Value Object）

```php
final readonly class CheckResult
{
    public function __construct(
        public string $type,        // 'rule' | 'agent' | 'skill' | 'gitignore'
        public string $path,        // ability path 标识符
        public string $target,      // 'cursor' | 'kiro' | '*'
        public string $status,      // 'unchanged' | 'modified' | 'missing' | 'unknown'
        public array $extraFiles,   // skill 目录中的额外文件（CR-3 决策）
    );
}
```

### GitIgnoreTemplateService（重构）

```php
final class GitIgnoreTemplateService
{
    /**
     * 从 GitignoreEntry 列表渲染 managed block 内容
     * @param array<int, GitignoreEntry> $entries
     */
    public function renderManagedBlock(array $entries): string;

    /** 将 managed section 合并到 .gitignore 文件 */
    public function mergeManagedSection(string $gitignorePath, string $managedBody): void;

    /** 移除指定 marker 的 block */
    public function removeMarkerBlock(string $gitignorePath, string $marker): void;
}
```

**渲染逻辑变更**：
- 旧：从模板文件解析 `@apm:block` 标记
- 新：直接从 `GitignoreEntry.content` 读取 pattern 内容，按 marker 生成 block

### ConsoleRegistration（重构）

```php
final class ConsoleRegistration
{
    public static function register(
        SymfonyApplication $app,
        Installer $installer,
        CheckService $checker,
        KnowledgeBaseUpdater $updater,
        PresetRegistry $presetRegistry,
    ): void;
}
```

移除 `CaptureService` 和 `CaptureChangeIngestor` 参数。

### ProjectInitializer（重构）

```php
final class ProjectInitializer
{
    private const SCAFFOLD_PATHS = [
        'AGENTS.md', 'CHANGELOG.md', 'docs/README.md', 'issues/README.md',
        'docs/state/.gitkeep', 'docs/manual/.gitkeep', 'docs/notes/.gitkeep',
        'docs/proposals/.gitkeep', 'docs/changes/.gitkeep',
    ];

    public function __construct(
        private readonly string $packageRoot,
        private readonly DirectoryMirrorService $mirror,
    );

    /**
     * 从 package root 硬编码路径安装 scaffold
     * 已存在的文件/目录跳过不覆盖
     */
    public function init(string $targetDir, array $targets): array;
}
```

**变更**：不再依赖 `scaffold/` 子目录，直接从 package root 对应路径读取。scope rule 安装改为从 AbilitiesRegistry 获取路径。

### AbilityDiffService（重构）

```php
final class AbilityDiffService
{
    /**
     * 基于 AbilityEntry 的 targets 路径进行 diff
     * 不再使用后缀匹配逻辑
     */
    public function diffAbility(
        AbilityEntry $entry,
        string $sourceRoot,
        string $workspaceRoot,
        string $target,
    ): CheckResult;

    /**
     * Skill 目录级 diff，返回状态和额外文件列表
     */
    public function diffSkillDirectory(
        string $sourceDir,
        string $targetDir,
    ): array; // ['status' => string, 'extra_files' => list<string>]
}
```

---

## Data Models

### abilities.yaml Schema（v1 完整格式）

```yaml
version: "1"

rules:
  - path: <colon-separated-identifier>  # e.g. "git:branch-overview"
    description: <string>
    targets:
      cursor: <relative-path>           # e.g. ".cursor/rules/git/branch-overview.mdc"
      kiro: <relative-path>             # e.g. ".kiro/steering/git/branch-overview.md"

agents:
  - path: <identifier>
    description: <string>
    targets:
      cursor: <relative-path>           # e.g. ".cursor/agents/code-reviewer.md"
      kiro: <relative-path>

skills:
  - path: <identifier>
    description: <string>
    targets:
      cursor: <directory-path>/         # 必须以 '/' 结尾
      kiro: <directory-path>/

gitignore:
  - marker: <string>                    # block 标识符
    description: <string>
    content: |                          # 多行 YAML 字符串（CR-1 决策）
      pattern1
      pattern2

presets:
  - name: <string>
    description: <string>
    includes:                           # type:path 格式
      - rule:<path>
      - skill:<path>
      - agent:<path>
```

### 路径解析规则

| 场景 | 基准 | 说明 |
|------|------|------|
| abilities.yaml 位置 | `PackagePaths::packageRoot()` | apm package root（CR-2 决策） |
| ability 源文件 | packageRoot + targets 值 | 源文件在 package root 下的 targets 路径 |
| ability 安装目标 | `getcwd()`（目标项目 root）+ targets 值 | targets 路径相对于目标项目 root |
| scaffold 源文件 | packageRoot + 硬编码路径 | 直接从 package root 读取 |

### 平台前缀判定

| 前缀 | 平台 |
|------|------|
| `.cursor/` | cursor |
| `.kiro/` | kiro |
| 其他 | 报错：unrecognized platform prefix |

### CheckResult 状态枚举

| 状态 | 含义 |
|------|------|
| `unchanged` | 目标文件与源文件内容一致 |
| `modified` | 目标文件存在但内容与源不同 |
| `missing` | 目标文件不存在 |
| `unknown` | 无法确定（如 abilities.yaml 中无此条目） |

### Extra Files 报告（CR-3 决策）

Skill check 时，目标目录中存在但源中不存在的文件单独报告为 `extra_files`，不影响 `modified`/`unchanged` 判定。CheckResult 的 `extraFiles` 字段为相对路径数组。

### Capture/Ingest 移除清单

**删除文件**：
- `src/Capture/CaptureChangeIngestor.php`
- `src/Capture/CaptureChangeSchema.php`
- `src/Capture/CaptureChangeSigner.php`
- `src/Capture/CaptureWriteBackService.php`
- `src/Service/CaptureService.php`
- `src/Command/AgentCaptureCommand.php`
- `src/Command/CaptureCommand.php`
- `src/Command/IngestCaptureChangeCommand.php`
- `src/Command/RuleCaptureCommand.php`
- `src/Command/SkillCaptureCommand.php`

**删除测试**：
- `tests/CaptureServiceUnitTest.php`
- `tests/CaptureChangeIngestorTest.php`
- `tests/CaptureChangeSignerTest.php`
- `tests/CaptureChangeSchemaTest.php`
- `tests/CaptureWriteBackServiceTest.php`
- `tests/CommandCheckCaptureTest.php`
- `tests/CaptureCommandBranchesTest.php`
- `tests/TypedCaptureCheckCommandsTest.php`

**重构依赖**：
- `ConsoleRegistration` — 移除 CaptureService/CaptureChangeIngestor 参数和 capture 命令注册
- `PresetCreateCommand` — 改为接收 PresetRegistry，移除 capture change 写入逻辑
- `PresetAddAbilityCommand` — 同上
- `PresetRemoveAbilityCommand` — 同上
- `PresetDeleteCommand` — 同上


---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Path resolution from targets mapping

*For any* valid AbilityEntry with a `targets` mapping, the Installer's resolved source path SHALL equal `packageRoot + targetPath` and the resolved destination path SHALL equal `projectRoot + targetPath`, for each target key present in the entry.

**Validates: Requirements 2.2, 2.4**

### Property 2: Platform installation scope matches targets keys

*For any* AbilityEntry and any user-requested target set, the set of platforms that actually receive installation SHALL equal the intersection of the entry's `targets` keys and the user-requested targets. No platform outside this intersection shall be affected.

**Validates: Requirements 2.6, 2.7**

### Property 3: Platform classification by path prefix

*For any* target path string starting with `.cursor/`, the platform classifier SHALL return `cursor`. *For any* target path string starting with `.kiro/`, the platform classifier SHALL return `kiro`.

**Validates: Requirements 3.1, 3.3, 3.4**

### Property 4: Invalid prefix detection

*For any* target path string that does not start with `.cursor/` or `.kiro/`, the platform classifier SHALL raise an error indicating an unrecognized platform prefix.

**Validates: Requirements 3.5**

### Property 5: Directory-level classification

*For any* AbilityEntry whose target path values end with `/`, `isDirectoryLevel()` SHALL return true. *For any* entry whose target paths do not end with `/`, `isDirectoryLevel()` SHALL return false.

**Validates: Requirements 4.1**

### Property 6: Directory mirror preserves structure and content

*For any* source directory tree, after `DirectoryMirrorService::mirrorDirectory()` copies it to a target path, every file in the source SHALL exist at the corresponding relative path in the target with byte-for-byte identical content, and the relative directory structure SHALL be preserved.

**Validates: Requirements 4.2**

### Property 7: Skill directory diff detects modifications

*For any* source directory and target directory pair where at least one file present in both has different content, `diffSkillDirectory()` SHALL return status `modified`.

**Validates: Requirements 4.4**

### Property 8: Extra files reported without affecting status

*For any* source directory and target directory pair where all source files have byte-for-byte identical copies in the target, but the target contains additional files not in the source, `diffSkillDirectory()` SHALL return status `unchanged` and SHALL list the additional files in `extra_files`.

**Validates: Requirements 4.5**

### Property 9: Dual-target skill identity

*For any* skill AbilityEntry with both `cursor` and `kiro` targets, after installation completes, the file tree under the cursor target path SHALL be byte-for-byte identical to the file tree under the kiro target path.

**Validates: Requirements 4.6**

### Property 10: Gitignore block formatting and idempotent insertion

*For any* marker name and content string, inserting the marker block into a `.gitignore` file SHALL produce output containing exactly one block delimited by `## @apm:block ability=<marker> target=*` and `## @apm:end` with the content lines between them. Inserting the same marker a second time SHALL produce the same final file content (idempotence).

**Validates: Requirements 5.2, 5.3**

### Property 11: Gitignore block removal round-trip

*For any* `.gitignore` content, inserting a marker block and then removing that same marker block SHALL restore the original content (modulo trailing newline normalization).

**Validates: Requirements 5.4**

### Property 12: Scaffold skip-on-exist preserves content

*For any* pre-existing file at a scaffold target path with non-empty content, running `ProjectInitializer::init()` SHALL leave that file's content byte-for-byte unchanged.

**Validates: Requirements 6.3, 1.6**

---

## Error Handling

### AbilitiesRegistry 错误

| 场景 | 行为 |
|------|------|
| abilities.yaml 不存在 | 抛出 RuntimeException，CLI 输出 "abilities.yaml not found at {path}" |
| YAML 语法错误 | 抛出 RuntimeException，包含 YAML parser 错误信息 |
| 缺少 `version` 字段 | 抛出 RuntimeException，"Missing required field: version" |
| 未知 version 值 | 抛出 RuntimeException，"Unsupported abilities.yaml version: {v}" |

### Installer 错误

| 场景 | 行为 |
|------|------|
| ability path 未在 abilities.yaml 中找到 | 输出 `[fail] Unknown ability: {path}`，跳过该 ability，继续处理其余 |
| 源文件不存在 | 输出 `[fail] Source file missing: {sourcePath}`，跳过 |
| 源目录不存在（skill） | 输出 `[fail] Source directory missing: {sourceDir}`，跳过 |
| 目标目录不存在 | 自动创建（mkdir -p），输出 `[notice] Created directory: {dir}` |
| 复制失败（权限等） | 输出 `[fail] Copy failed: {reason}`，跳过该文件 |
| 无效平台前缀 | 输出 `[fail] Unrecognized platform prefix in target: {path}`，跳过 |

### CheckService 错误

| 场景 | 行为 |
|------|------|
| ability path 未找到 | 返回 CheckResult(status=unknown) |
| 源文件不存在 | 返回 CheckResult(status=unknown) |
| 目标文件不存在 | 返回 CheckResult(status=missing) |

### GitIgnoreTemplateService 错误

| 场景 | 行为 |
|------|------|
| marker 未在 abilities.yaml 中定义 | 输出错误信息，不修改文件 |
| .gitignore 不存在 | 创建文件后插入 block |
| 未闭合的 block（遗留格式） | 抛出 RuntimeException |

### ProjectInitializer 错误

| 场景 | 行为 |
|------|------|
| 硬编码源文件缺失 | 抛出 RuntimeException，指明缺失路径 |
| 目标文件已存在 | 跳过，输出 `[skip] {path} already exists` |
| 目标目录已存在 | 跳过目录创建，继续处理文件 |

---

## Testing Strategy

### 测试框架

- **单元测试**: PHPUnit 13
- **属性测试**: PhpQuickCheck（`steos/quickcheck`）— PHP 属性测试库
- 每个属性测试最少 100 次迭代

### 属性测试覆盖

每个 Correctness Property 对应一个属性测试，使用 PhpQuickCheck 的 generator 生成随机输入：

| Property | 测试文件 | Generator 策略 |
|----------|----------|---------------|
| 1: Path resolution | `tests/Property/PathResolutionTest.php` | 随机生成 AbilityEntry（随机 path、随机 targets） |
| 2: Platform scope | `tests/Property/PlatformScopeTest.php` | 随机生成 targets 子集和请求 targets |
| 3: Platform classification | `tests/Property/PlatformClassificationTest.php` | 随机生成 `.cursor/` 或 `.kiro/` 前缀路径 |
| 4: Invalid prefix | `tests/Property/InvalidPrefixTest.php` | 随机生成不以 `.cursor/` 或 `.kiro/` 开头的路径 |
| 5: Directory-level | `tests/Property/DirectoryLevelTest.php` | 随机生成以 `/` 结尾和不以 `/` 结尾的路径 |
| 6: Directory mirror | `tests/Property/DirectoryMirrorTest.php` | 随机生成目录树结构（文件名、内容） |
| 7: Diff detects modifications | `tests/Property/DiffModifiedTest.php` | 随机生成目录对，至少一个文件内容不同 |
| 8: Extra files | `tests/Property/ExtraFilesTest.php` | 随机生成目录对，target 有额外文件 |
| 9: Dual-target identity | `tests/Property/DualTargetTest.php` | 随机生成 skill 内容，安装后比较两个 target |
| 10: Gitignore formatting | `tests/Property/GitignoreBlockTest.php` | 随机生成 marker 名和 content 字符串 |
| 11: Gitignore round-trip | `tests/Property/GitignoreRoundTripTest.php` | 随机生成 .gitignore 内容 + marker |
| 12: Scaffold skip | `tests/Property/ScaffoldSkipTest.php` | 随机生成已存在文件内容 |

**Tag 格式**: `Feature: prp001-abilities-relocation, Property {N}: {title}`

### 单元测试覆盖

| 模块 | 测试重点 |
|------|----------|
| AbilitiesRegistry | YAML 解析、字段校验、findAbility/findGitignore/findPreset 查询、preset includes 解析 |
| Installer | 安装成功路径、错误路径（missing ability、missing source）、mkdir-p 行为 |
| CheckService | 各状态判定（unchanged/modified/missing/unknown）、extra files 报告 |
| GitIgnoreTemplateService | block 插入/替换/移除、文件创建、未闭合 block 异常 |
| ProjectInitializer | scaffold 安装、skip-on-exist、missing source 异常 |
| PresetCreateCommand | 无 CaptureService 依赖的 preset CRUD |
| ConsoleRegistration | 命令注册完整性（无 capture 命令） |

### 集成测试

- 端到端 install → check → uninstall 流程
- preset install 解析 includes 并批量安装
- gitignore install → check → uninstall 完整流程

---

## State 文档设计

Requirements 8–12 要求补写完整的 `docs/state/` 文档体系。这些文档的内容应反映本 design 中描述的最终实现行为，而非旧系统行为。

### 文档产出清单

| 文件 | 内容来源 | 对应 Requirement |
|------|----------|-----------------|
| `docs/state/cli-commands.md` | 从 ConsoleRegistration 最终注册的命令集导出，排除 capture/ingest | Req 8 |
| `docs/state/abilities-model.md` | 从 Data Models section 的 abilities.yaml schema 导出 | Req 9 |
| `docs/state/install-behavior.md` | 从 Installer/CheckService/GitIgnoreTemplateService 接口行为导出 | Req 10 |
| `docs/state/gitignore.md` | 从 GitIgnoreTemplateService 的 block 格式规则导出 | Req 11 |
| `docs/state/architecture.md`（更新） | 从 Architecture section 的模块职责图导出 | Req 12 |

### 设计原则

- State 文档在代码改造完成后编写，确保反映实际行为
- 文档格式遵循 `docs/README.md` 中的文档分层规范
- `architecture.md` 不引用 capture/ingest 或后缀解析作为活跃组件

---

## Phase 1 验证设计

Requirements 1 要求验证 PRP-001 迁移映射表中所有文件已就位。

### 验证流程

1. 读取 `docs/proposals/PRP-001-abilities-relocation.md` 中的迁移映射表
2. 逐条检查目标路径文件是否存在
3. 缺失文件从 `abilities/` 或 `scaffold/` 对应源复制到目标路径（去除 target-suffix）
4. 验证 abilities.yaml 中所有 `targets` 路径指向的文件均存在
5. 确认 `abilities/` 和 `scaffold/` 目录可安全删除（所有内容已迁移）

### 完成标准

- abilities.yaml 中每个 targets 值对应的文件/目录均存在
- `abilities/` 和 `scaffold/` 目录已删除
- Skill 在 cursor 和 kiro 两个 target 下内容 byte-for-byte 一致

---

## Impact Analysis

### 受影响的 State 文档

| 文件 | 影响 |
|------|------|
| `docs/state/architecture.md` | 需重写：新增 AbilitiesRegistry、移除 Capture 模块、更新数据流 |
| `docs/state/cli-commands.md` | 新建：记录最终命令集 |
| `docs/state/abilities-model.md` | 新建：记录 abilities.yaml 完整 schema |
| `docs/state/install-behavior.md` | 新建：记录安装/检查/卸载逻辑 |
| `docs/state/gitignore.md` | 新建：记录 marker block 格式与操作规则 |

### 现有模块行为变化

| 模块 | 变化 |
|------|------|
| `src/Service/Installer.php` | 数据源从目录扫描改为 AbilitiesRegistry；路径解析从后缀改为前缀 |
| `src/Service/CheckService.php` | 同上，expected paths 来源变更 |
| `src/Service/GitIgnoreTemplateService.php` | 内容来源从模板文件改为 abilities.yaml 内联 content |
| `src/Service/ProjectInitializer.php` | scaffold 源从 `scaffold/` 目录改为 package root 硬编码路径 |
| `src/Service/AbilityDiffService.php` | diff 基准从后缀匹配改为 AbilityEntry targets 路径 |
| `src/Core/ConsoleRegistration.php` | 移除 CaptureService/CaptureChangeIngestor 参数，移除 capture 命令注册 |
| `src/Command/Preset*Command.php` | 移除 CaptureService 依赖，改用 PresetRegistry |
| `src/Capture/`（整个目录） | 删除 |
| `src/Service/CaptureService.php` | 删除 |
| `src/Command/*Capture*.php` | 删除 |

### 数据模型变更

- **abilities.yaml**：`gitignore` section 新增 `content` 字段（多行 YAML 字符串）。当前 abilities.yaml 中 gitignore 条目仅有 `marker` + `description`，需补充 `content` 字段。
- **无旧数据兼容需求**：abilities.yaml 格式变更不需要迁移脚本（Non-Goal 已明确不兼容旧格式）。

### 配置项变更

- 无新增配置项
- 移除：任何与 capture/ingest 相关的配置（如有）

### 外部系统交互

- 无外部系统交互变化（apm 为本地 CLI 工具）

---

## Alternatives Considered

### 1. abilities.yaml 拆分为多文件

**方案**：每种 ability 类型一个 YAML 文件（rules.yaml, agents.yaml, skills.yaml）。

**落选理由**：增加文件管理复杂度，且当前 ability 数量有限（<30），单文件足够。abilities.yaml 作为唯一注册表更符合"单一事实来源"原则。

### 2. Gitignore content 存储在独立模板文件

**方案**：abilities.yaml 仅引用 marker 名，实际 pattern 存储在 `gitignore/<marker>.gitignore`。

**落选理由**：CR-1 决策已确定内联存储。独立文件增加了文件数量和路径解析复杂度，且 gitignore pattern 通常很短（3-5 行），内联更直观。

### 3. 保留 capture/ingest 兼容层

**方案**：标记为 deprecated 但保留代码，给用户过渡期。

**落选理由**：Goal 明确要求直接删除，不保留兼容层。capture/ingest 功能已无使用场景。

---

## Socratic Review

**design 是否完整覆盖了 requirements？**
Req 1–7 有明确的技术方案和接口定义。Req 8–12（state 文档）已补充设计说明，明确了文档产出清单和内容来源。覆盖完整。

**技术选型是否合理？**
AbilitiesRegistry 作为 YAML 解析层合理——将 YAML 结构映射为强类型 Value Object，避免在业务逻辑中直接操作数组。PhpQuickCheck 用于属性测试是 PHP 生态中的标准选择。

**接口签名和数据模型是否足够清晰？**
所有核心接口都有完整的 PHP 签名、参数类型和返回类型。Value Object 字段定义明确。足够支撑 task 拆分。

**模块间依赖是否会引入循环依赖或过度耦合？**
依赖方向单一：Commands → Services → AbilitiesRegistry → Config。无循环依赖风险。AbilitiesRegistry 作为共享依赖被多个 Service 引用，但这是合理的——它是数据源。

**是否有过度设计？**
Property-based testing 对于路径解析和文件操作这类组合爆炸场景是合理的，不算过度。Value Object 数量（3 个）与 abilities.yaml 的 section 数量匹配，不多余。

**是否存在未经确认的重大技术选型？**
所有关键决策（CR-1 到 CR-4）已在 requirements 阶段确认。PhpQuickCheck 作为属性测试库是新引入的依赖，但属于 dev dependency，风险可控。

**Impact Analysis 是否充分？**
已覆盖所有受影响模块、state 文档、数据模型变更。无外部系统交互变化。配置项变更最小。



---

## Gatekeep Log

**校验时间**: 2025-01-27
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Impact Analysis` section（原文缺失，已补充受影响模块、state 文档、数据模型变更、配置项变更分析）
- [结构] 补充 `## Alternatives Considered` section（原文缺失，已补充 3 个备选方案及落选理由）
- [结构] 补充 `## Socratic Review` section（原文缺失，已补充 7 项自问自答）
- [内容] 补充 `## State 文档设计` section（Req 8–12 在原文中无对应设计，已补充文档产出清单和设计原则）
- [内容] 补充 `## Phase 1 验证设计` section（Req 1 在原文中仅 Overview 提及，无具体验证流程设计）

### 合规检查
- [x] 无 TBD / TODO / 占位符
- [x] 无空 section 或不完整列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在
- [x] 技术方案主体存在，承接 requirements
- [x] 接口签名 / 数据模型有明确定义
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应实现描述
- [x] design 中的方案不超出 requirements 范围
- [x] Impact Analysis 覆盖受影响模块和 state 文档
- [x] 技术选型有明确理由
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] Requirements CR 决策（CR-1 到 CR-4）在 design 中体现
- [x] 可 task 化：接口定义足够具体
- [○] `TypedCaptureCheckCommandsTest.php` 处理方式：design 列为整文件删除，requirements 说"capture-related test methods"——需 tasks 阶段确认该文件是否全部为 capture 相关

### Clarification Round

以下问题面向 tasks 阶段，需用户在进入 tasks 前做出决策：

**CR-1: 实现顺序偏好**

Phase 1 验证、Phase 2 代码适配、capture/ingest 移除、state 文档补写之间存在依赖关系。tasks 拆分时的执行顺序偏好？

- A) Phase 1 验证 → capture/ingest 移除 → Phase 2 代码适配（AbilitiesRegistry → Installer → CheckService → GitIgnore → ProjectInitializer → Preset） → state 文档补写
- B) capture/ingest 移除 → Phase 1 验证 → Phase 2 代码适配 → state 文档补写（先清理再建设）
- C) Phase 2 代码适配与 capture/ingest 移除交织进行（按模块逐个重构+清理），最后 Phase 1 验证 + state 文档

**A:** B — 先清理再建设：capture 移除 → Phase 1 验证 → Phase 2 代码适配 → state 文档补写

**CR-2: AbilitiesRegistry 与现有 Service 的集成方式**

AbilitiesRegistry 是新增模块，现有 Installer/CheckService 需要注入它。集成方式偏好？

- A) 构造函数注入：Installer/CheckService 的构造函数新增 AbilitiesRegistry 参数，一次性重构所有调用点
- B) 渐进式：先在 Installer 中引入 AbilitiesRegistry，验证通过后再扩展到 CheckService 和其他 Service
- C) Factory 模式：通过 ServiceFactory 统一创建 Service 实例，隐藏 AbilitiesRegistry 的注入细节

**A:** B — 渐进式，按模块逐步引入

**CR-3: 属性测试的引入时机**

PhpQuickCheck 是新 dev dependency。属性测试应在什么时机引入？

- A) 作为独立 task 在代码适配完成后统一编写所有属性测试
- B) 每个模块重构 task 完成后立即编写对应的属性测试（与功能代码同 task）
- C) 先完成所有代码适配和单元测试，属性测试作为最后的质量加固 task

**A:** C — 先完成所有代码适配和单元测试，属性测试作为最后的质量加固

**CR-4: TypedCaptureCheckCommandsTest.php 的处理**

Requirements 说"capture-related test methods in TypedCaptureCheckCommandsTest.php"，design 列为整文件删除。该文件的处理方式？

- A) 整文件删除（如果文件中所有测试都是 capture 相关的）
- B) 仅删除 capture 相关方法，保留其他测试方法（如果有非 capture 测试）
- C) 先检查文件内容再决定——如果全部是 capture 测试则删除，否则仅清理 capture 方法

**A:** C — 先检查文件内容再决定
