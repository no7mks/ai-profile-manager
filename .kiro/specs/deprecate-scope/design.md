# Design: Deprecate Scope

## Overview

本设计将 apm 从 dual-scope（project + user）模型简化为 single-scope（project-only）模型。核心变更分四层：

1. **CLI 入口层**：`HandlesDeployScopeOption` trait 改为 scope 废弃检测；`GlobalSetupCommand` 退化为 deprecated 桩
2. **Registry 解析层**：`AbilityRegistry` 新增遗留字段检测（`scopes`、`global-setup` key）；`globalSetupIncludes()` 更名为 `bootstrapIncludes()` 读取新 `bootstrap` section
3. **Service 层**：`Installer`/`InstallationProbe`/`CheckService`/`AbilityUpdateService` 移除 `$scope` 参数，硬编码 project；`ScopeGuard`、`UserHomeResolver`、`DefaultGlobalSetupService`/`GlobalSetupService` 删除
4. **Bootstrap 扩展**：`BootstrapCommand` 追加 ability install 阶段（scaffold → includes install）

---

## Architecture

### 模块依赖（变更后）

```
BootstrapCommand
  └── ProjectInitializer (scaffold)
  └── Installer.installTyped (ability install, project-only)
        └── AbilityRegistry.bootstrapIncludes()
        └── InstallationProbe (project-only)
        └── DeployRootResolver (project root only)

HandlesDeployScopeOption (trait)
  └── DeprecatedScopeException.scopeRemoved()   [新工厂方法]

AbilityRegistry.parse()
  └── 遗留字段检测 (scopes, global-setup) → fail-fast
```

### 删除的模块

| 模块 | 理由 |
|------|------|
| `ScopeGuard` | 无 user scope → 无需 project-only 校验 |
| `UserHomeResolver` | 无 user scope → 无需解析 user root |
| `DefaultGlobalSetupService` | `global-setup` 命令废弃 |
| `GlobalSetupService` (interface) | 同上 |
| `ShowStatusPresenter::mergeStatuses()` | 无 scope 合并 |

---

## Components and Interfaces

### 1. `InvalidScopeException` 精简

```php
final class InvalidScopeException extends \InvalidArgumentException
{
    /**
     * 统一 deprecated 工厂方法（CR-1 决策：统一模板）
     * @param string $feature 被废弃的功能名（如 "--scope option"、"global-setup command"）
     * @param string $replacement 替代方案描述
     */
    public static function deprecated(string $feature, string $replacement): self;
}
```

- 删除 `unknownScope()`、`projectOnlyInUserScope()`
- 保留类名不变（避免下游破坏 catch 逻辑）

### 2. `HandlesDeployScopeOption` trait

```php
trait HandlesDeployScopeOption
{
    protected function configureDeployScopeOption(): void
    {
        // 仍注册 --scope option（保持 CLI 签名可解析，否则 Symfony Console 会 throw）
        $this->addOption('scope', null, InputOption::VALUE_OPTIONAL, '[REMOVED]');
    }

    /**
     * 检测 --scope 是否被传入。若传入（任何值或无值）→ 写 error 并返回 false。
     * @return bool true = 正常继续执行; false = 已输出 deprecated error，调用方应 return FAILURE
     */
    protected function rejectIfScopeProvided(InputInterface $input, SymfonyStyle $io): bool;
}
```

- 移除 `resolveDeployScopeOption()`、`guardInstallBatch()`、`guardPresetInstall()`
- 所有使用该 trait 的命令在 `execute()` 开头调用 `rejectIfScopeProvided()`

### 3. `DeployScope` 枚举

```php
enum DeployScope: string
{
    case Project = 'project';
    // User case 删除
}
```

保留枚举仅含 `Project` 单值（类型安全，goal 决策）。

### 4. `DeployRootResolver` 简化

```php
final class DeployRootResolver
{
    // 构造函数：移除 UserHomeResolver 依赖
    public function __construct() {}

    public function resolve(): string;  // 仅返回 project root (getcwd)
    public function absoluteTargetPath(string $relativeTarget): string;  // 移除 scope 参数
}
```

- 删除 `parseScopeOption()` 方法
- `resolve()` 签名移除 `DeployScope` 参数（始终 project）
- `absoluteTargetPath()` 签名移除 `DeployScope` 参数

### 5. `AbilityRegistry` 变更

```php
final class AbilityRegistry
{
    /**
     * parse() 新增遗留字段检测（CR-2 决策：IO 层立即验证）
     * - 发现 entry 含 `scopes` key → 收入 validationErrors（首个违规 fail-fast）
     * - 发现顶层 `global-setup` key → 收入 validationErrors
     */
    public function parse(): array;

    /**
     * 原 globalSetupIncludes() 更名，读取 `bootstrap.includes` section
     * @return list<array{type: string, path: string}>
     */
    public function bootstrapIncludes(): array;
}
```

- `parse()` 中 `resolveScopes()` 逻辑改为：若 entry 含 `scopes` key → 直接加入 errors（不再尝试解析合法 scope 值）
- `AbilityEntry::$scopes` 属性删除（构造函数移除该参数）
- `projectOnlyPaths()` 方法删除

### 6. `AbilityEntry` 变更

```php
final readonly class AbilityEntry
{
    public function __construct(
        public string $path,
        public string $description,
        public array $targets,   // array<string, string>
        public string $type,
        // $scopes 参数删除
    ) {}
}
```

### 7. `InstallationProbe` 简化

```php
final class InstallationProbe
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly DeployRootResolver $rootResolver,
    ) {}

    // 移除 $scope 参数（硬编码 project）
    public function isPresent(string $type, string $name, string $target): bool;
}
```

### 8. `Installer` 简化

```php
final class Installer
{
    // installTyped(): 移除 $scope 参数（硬编码 project）
    public function installTyped(array $items, array $targets, ?string $presetName = null): array;

    // uninstallTyped(): 移除 $scope 参数
    public function uninstallTyped(array $items, array $targets, bool $force = false): array;

    // uninstallProjectScope(): 不变（已经是 project-only）
    public function uninstallProjectScope(): array;
}
```

- 移除 `Scope: project|user` 输出行（不再打印 scope）
- 内部 helper 方法（`installAbilityBundle`, `installHook`, `uninstallSkill` 等）移除 scope 参数

### 9. `BootstrapCommand` 扩展

```php
final class BootstrapCommand extends Command
{
    public function __construct(
        private readonly ?ProjectInitializer $initializer = null,
        private readonly ?Installer $installer = null,         // 新增
        private readonly ?AbilityRegistry $registry = null,   // 新增
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. scaffold phase (现有逻辑)
        // 2. ability install phase (新增)
        //    - $includes = $this->registry->bootstrapIncludes()
        //    - 若为空 → 成功返回
        //    - 遍历 includes：对每项做 hash 比对判定幂等 (CR-3)
        //    - 已安装且 hash 一致且无 --force → skip
        //    - 已安装且 hash 不一致 → 视为未安装，执行 install（或 --force 覆盖）
        //    - 安装失败 → 记 [fail]，继续剩余项，最终 exit 1
    }
}
```

**幂等判定**（CR-3 决策：内容 hash 比对）：
- 使用 `AbilityDiffService` 对比 baseline 源与目标文件内容
- hash 一致 → 已安装（skip）
- hash 不一致（含目标不存在）→ 未安装/需更新 → install

### 10. `CheckService` 简化

```php
final class CheckService
{
    // 移除 checkTypedForScope(): 不再需要 scope 参数
    // checkTyped() 直接使用 project root
    public function checkTyped(array $items, array $targets): array;
}
```

### 11. `AbilityUpdateService` 简化

```php
class AbilityUpdateService
{
    public function reportChanges(bool $force): array
    {
        // 仅遍历 project scope，移除 DeployScope::User 迭代
        // 输出格式中移除 ({scope}) 部分
    }
}
```

### 12. `ShowStatusPresenter` 简化

```php
final class ShowStatusPresenter
{
    // rows() / lines(): 移除 $scopeFilter 参数
    public function rows(array $targets, ?string $typeFilter): array;
    public function lines(array $targets, ?string $typeFilter): array;

    // 移除 mergeStatuses()
    // 移除 scope label 生成
    // 状态判定 (CR-4): baseline 源文件 diff（沿用 AbilityDiffService）
}
```

### 13. `GlobalSetupCommand` 退化

```php
final class GlobalSetupCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->error(InvalidScopeException::deprecated(
            'global-setup command',
            'Use "apm bootstrap" in your project directory instead.'
        )->getMessage());
        return Command::FAILURE;
    }
}
```

- 构造函数移除 `GlobalSetupService` 依赖
- `configure()` 保留命令名和选项定义（确保 `bin/apm global-setup --force` 等不报 unknown option）

### 14. `CleanupCommand` 文案

```php
// 替换输出文案
$output->writeln('To reinstall project abilities, run /apm init in agent chat.');
// 移除 "user-scope global-setup is unchanged" 措辞
```

---

## Data Models

### `abilities.yaml` 新格式

```yaml
version: "1"
bootstrap:                    # 原 global-setup，改名
  includes:
    - skill:apm
    - rule:git:git-conventions
rules:
  - path: git:git-conventions
    description: Git conventions
    targets:
      cursor: .cursor/rules/git/git-conventions.mdc
      kiro: .kiro/steering/git/git-conventions.md
    # scopes 字段删除
```

### 验证规则变更

| 条件 | 行为 |
|------|------|
| entry 含 `scopes` key | `validationErrors` → fail-fast |
| 顶层含 `global-setup` key | `validationErrors` → fail-fast |
| `bootstrap.includes` 缺失/为空 | 返回空列表，不报错 |

---

## Correctness Properties

### Property 1: Scope 参数一律拒绝

- **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7**
- **条件**: 任何已注册命令接收到 `--scope` option（任何值或无值）
- **保证**: CLI 输出统一 deprecated 消息并 exit 1，不执行任何业务逻辑
- **违反后果**: 用户误以为 `--scope project` 仍有效，导致行为混淆

### Property 2: Registry 遗留字段 fail-fast

- **Validates: Requirements 4.1, 4.2, 4.3, 4.4**
- **条件**: `abilities.yaml` 含 `scopes` key 或 `global-setup` 顶层 key
- **保证**: 首个违规即抛异常终止，不执行任何 install/show/update 逻辑
- **违反后果**: 旧配置文件被静默接受，行为不可预期

### Property 3: Bootstrap 两阶段原子性

- **Validates: Requirements 3.1, 3.5, 3.6, 3.8**
- **条件**: `apm bootstrap` 执行
- **保证**: scaffold 写盘优先完成；ability install 失败不回滚 scaffold；逐项记录失败
- **违反后果**: 部分 scaffold 丢失或 ability 安装静默跳过错误

### Property 4: Bootstrap 幂等性（hash 比对）

- **Validates: Requirements 3.3, 3.4**
- **条件**: bootstrap includes 中的 ability 已安装
- **保证**: 内容 hash 一致 → skip（无 --force）或 overwrite（有 --force）；hash 不一致 → 视为未安装，执行 install
- **违反后果**: 重复安装覆盖用户定制，或陈旧文件未被更新

### Property 5: Show 无 scope 标签

- **Validates: Requirements 6.1, 6.2, 6.3**
- **条件**: `apm show` 输出
- **保证**: 输出不含 `(user)`、`(project)`、`(user+project)`、`[warn] installed in both` 等字符串
- **违反后果**: 用户看到已废弃概念，产生困惑

---

## Error Handling

| 错误场景 | 处理方式 |
|----------|----------|
| `--scope` 传入 | `InvalidScopeException::deprecated()` → stderr error → exit 1 |
| `global-setup` 执行 | `InvalidScopeException::deprecated()` → stderr error → exit 1 |
| `abilities.yaml` 含 `scopes` / `global-setup` | `AbilityRegistryException::validationErrors()` → fail-fast exit |
| bootstrap includes 条目无法解析 | 输出 error 行，skip 该条目，继续执行 |
| bootstrap ability install 失败 | 输出 `[fail]`，继续剩余，最终 exit 1 |
| scaffold 失败（文件已存在无 --force） | 抛异常 → exit 1，不进入 ability install |

---

## Testing Strategy

### 单元测试

| 测试目标 | 覆盖内容 |
|----------|----------|
| `InvalidScopeException::deprecated()` | 消息格式、参数化 |
| `HandlesDeployScopeOption::rejectIfScopeProvided()` | 各值（project/user/空/null）→ reject；未传 → pass |
| `AbilityRegistry::parse()` | scopes field → error；global-setup key → error；正常 yaml → success |
| `AbilityRegistry::bootstrapIncludes()` | 正常 list、空 list、缺失 section、非字符串条目、无冒号条目 |
| `BootstrapCommand` | scaffold + includes 安装；幂等 skip；force 覆盖；install 失败；scaffold 失败 |
| `ShowStatusPresenter` | 输出无 scope 标签；三种状态正确 |
| `AbilityUpdateService` | 仅 project scope 遍历；不遍历 user |
| `CleanupCommand` | 输出无 user-scope/global-setup 文案 |

### E2E 测试

| 场景 | 验证 |
|------|------|
| `bin/apm global-setup` | exit 1 + deprecated 消息 |
| `bin/apm install gitflow --scope user` | exit 1 + deprecated 消息 |
| `bin/apm show --scope project` | exit 1 + deprecated 消息 |
| `bin/apm bootstrap -t cursor -t kiro` | scaffold + includes 安装成功 |

---

## Impact Analysis

| 检查项 | 影响 |
|--------|------|
| **受影响的 state 文档** | `deploy-scope.md`（重写为 project-only）、`cli-commands.md`（移除 --scope 签名，global-setup 标记 removed，更新 bootstrap）、`abilities-model.md`（移除 scopes 字段、global-setup section、Project-Only 概念）、`install-behavior.md`（移除 user scope 路径解析） |
| **现有模块行为变化** | `Installer`: 不再接受 scope 参数；`ShowStatusPresenter`: 不再合并双 scope；`AbilityUpdateService`: 仅遍历 project；`BootstrapCommand`: 新增 ability install 阶段 |
| **数据模型变更** | `abilities.yaml`: 删除 `scopes` 字段、`global-setup` section 改名 `bootstrap`；`AbilityEntry`: 删除 `$scopes` 属性 |
| **外部系统交互变化** | 无（CLI 工具无网络交互） |
| **配置项变更** | `abilities.yaml` 顶层 key 新增 `bootstrap`（替代 `global-setup`）；entry 级别删除 `scopes`（遗留则报错） |

---

## Alternatives Considered

### A1: 完全删除 DeployScope 枚举

- **方案**：直接删除 `DeployScope` 枚举和所有引用，在需要 root 的地方直接调用 `getcwd()`
- **落选理由**：goal 决策明确"保留仅含 Project 单值"以维持类型安全；未来若需扩展（如 workspace scope）不必从零引入

### A2: `--scope project` 静默通过

- **方案**：传入 `--scope project` 时视为无操作（静默成功），仅对 `--scope user` 报错
- **落选理由**：goal 和 CR 明确决策"一律 hard fail"，避免用户脚本依赖已废弃参数

### A3: bootstrap includes 使用文件存在判定幂等

- **方案**：文件/目录存在即视为已安装（跳过），不做内容比对
- **落选理由**：CR-3 决策为 B) 内容 hash 比对。仅检查存在会导致更新后的 baseline 无法推送到已安装项目

