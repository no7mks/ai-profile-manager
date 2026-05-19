# Implementation Plan: Abilities Relocation

## Overview

按 Design CR-1 确定的执行顺序实施：capture/ingest 移除（先清理）→ Phase 1 文件迁移验证 → Phase 2 代码适配（AbilitiesRegistry → Installer → CheckService → GitIgnore → ProjectInitializer → Preset 命令）→ State 文档补写 → 属性测试（最终质量加固）。

AbilitiesRegistry 采用渐进式集成（Design CR-2）：先在 Installer 中引入，验证通过后扩展到 CheckService 和其他 Service。

## Tasks

- [ ] 1. Capture/Ingest 移除
  - [ ] 1.1 检查 TypedCaptureCheckCommandsTest.php 内容并决定处理方式
    - 读取文件内容，确认是否全部为 capture 相关测试
    - 若全部为 capture 相关或依赖旧架构（suffix-based 路径）→ 整文件删除
    - 若包含非 capture 且不依赖旧架构的测试 → 仅删除 capture 方法
    - _Requirements: 7.5_
  - [ ] 1.2 删除 src/Capture/ 目录及 CaptureService
    - 删除 `src/Capture/CaptureChangeIngestor.php`、`CaptureChangeSchema.php`、`CaptureChangeSigner.php`、`CaptureWriteBackService.php`
    - 删除 `src/Service/CaptureService.php`
    - _Requirements: 7.1, 7.3_
  - [ ] 1.3 删除 Capture/Ingest 命令文件
    - 删除 `src/Command/AgentCaptureCommand.php`、`CaptureCommand.php`、`IngestCaptureChangeCommand.php`、`RuleCaptureCommand.php`、`SkillCaptureCommand.php`
    - _Requirements: 7.2, 7.6_
  - [ ] 1.4 删除 Capture/Ingest 测试文件
    - 删除 `tests/CaptureServiceUnitTest.php`、`CaptureChangeIngestorTest.php`、`CaptureChangeSignerTest.php`、`CaptureChangeSchemaTest.php`、`CaptureWriteBackServiceTest.php`、`CommandCheckCaptureTest.php`、`CaptureCommandBranchesTest.php`
    - 按 1.1 的决定处理 `TypedCaptureCheckCommandsTest.php`
    - _Requirements: 7.5_
  - [ ] 1.5 重构 ConsoleRegistration 移除 Capture 依赖
    - 移除 `CaptureService` 和 `CaptureChangeIngestor` 构造参数
    - 移除所有 capture/ingest 命令注册（SkillCaptureCommand、RuleCaptureCommand、AgentCaptureCommand、CaptureCommand、IngestCaptureChangeCommand）
    - 更新 `Application.php` 中调用 `ConsoleRegistration::register()` 的代码
    - _Requirements: 7.4, 7.6_
  - [ ] 1.6 重构 Preset 命令移除 CaptureService 依赖
    - `PresetCreateCommand`、`PresetAddAbilityCommand`、`PresetRemoveAbilityCommand`、`PresetDeleteCommand` 改为接收 `PresetRegistry` 参数
    - 移除 capture change 写入逻辑，保留 preset registry 读写功能
    - 更新 ConsoleRegistration 中 Preset 命令的实例化代码
    - _Requirements: 7.7_
  - [ ] 1.7 验证编译通过且测试全部通过
    - 运行 `composer install` 确认无 autoload 错误
    - 运行 `./vendor/bin/phpunit` 确认所有剩余测试通过
    - _Requirements: 7.8_

- [ ] 2. Checkpoint — Capture 移除验证
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 3. Phase 1 文件迁移验证与补齐
  - [ ] 3.1 验证 abilities.yaml 中所有 targets 路径对应的文件/目录存在
    - 遍历 abilities.yaml 中 rules、agents、skills 的 targets 值
    - 检查每个路径在项目根目录下是否存在
    - 缺失文件从对应源位置补齐（去除 target-suffix）
    - Skill 验证 cursor 和 kiro 两个 target 下内容 byte-for-byte 一致
    - _Requirements: 1.1, 1.5, 1.7_
  - [ ] 3.2 确认并删除旧 abilities/ 和 scaffold/ 目录
    - 确认所有内容已迁移到新位置
    - 删除 `abilities/` 目录（如仍存在）
    - 删除 `scaffold/` 目录（如仍存在）
    - _Requirements: 1.3, 1.4_
  - [ ] 3.3 更新 abilities.yaml gitignore section 添加 content 字段
    - 为每个 gitignore 条目（php、graphify、python、kotlin）添加 `content` 多行 YAML 字段
    - 内容从旧 gitignore 模板文件中提取（如存在），否则根据 marker 类型编写合理的 ignore patterns
    - _Requirements: 5.1_

- [ ] 4. Phase 2 — AbilitiesRegistry 核心模块
  - [ ] 4.1 创建 AbilityEntry Value Object
    - 创建 `src/Config/AbilityEntry.php`
    - 实现 `type`、`path`、`description`、`targets` 属性
    - 实现 `isDirectoryLevel()` 和 `targetPlatforms()` 方法
    - _Requirements: 2.6, 2.7, 4.1_
  - [ ] 4.2 创建 GitignoreEntry Value Object
    - 创建 `src/Config/GitignoreEntry.php`
    - 实现 `marker`、`description`、`content` 属性
    - _Requirements: 5.1_
  - [ ] 4.3 创建 PresetEntry Value Object
    - 创建 `src/Config/PresetEntry.php`
    - 实现 `name`、`description`、`includes` 属性
    - _Requirements: 9.4_
  - [ ] 4.4 创建 CheckResult Value Object
    - 创建 `src/Config/CheckResult.php`
    - 实现 `type`、`path`、`target`、`status`、`extraFiles` 属性
    - status 枚举：unchanged、modified、missing、unknown
    - _Requirements: 2.4, 4.4, 4.5_
  - [ ] 4.5 实现 AbilitiesRegistry 类
    - 创建 `src/Config/AbilitiesRegistry.php`
    - 实现 YAML 解析逻辑（使用 Symfony YAML 或 php-yaml）
    - 实现 `fromPackageRoot()` 静态工厂方法（通过 `PackagePaths::packageRoot()` 定位）
    - 实现 `rules()`、`agents()`、`skills()`、`gitignoreEntries()`、`presets()` 查询方法
    - 实现 `findAbility()`、`findGitignore()`、`findPreset()` 查找方法
    - 实现 `resolvePresetAbilities()` preset includes 解析
    - 实现错误处理：YAML 不存在、语法错误、缺少 version 字段、未知 version
    - _Requirements: 2.1, 2.8, 2.9, 5.7_
  - [ ]* 4.6 编写 AbilitiesRegistry 单元测试
    - 测试 YAML 解析正确性（各 section 解析为对应 Value Object）
    - 测试 findAbility/findGitignore/findPreset 查询
    - 测试 resolvePresetAbilities 解析 includes
    - 测试错误场景：文件不存在、语法错误、缺少 version
    - _Requirements: 2.1, 2.8, 2.9_

- [ ] 5. Checkpoint — AbilitiesRegistry 验证
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 6. Phase 2 — Installer 重构
  - [ ] 6.1 重构 Installer 注入 AbilitiesRegistry
    - 修改 Installer 构造函数，新增 `AbilitiesRegistry` 参数
    - 移除旧的目录扫描逻辑
    - 实现从 AbilitiesRegistry 获取 ability 定义的安装流程
    - 实现路径前缀验证（`.cursor/` 或 `.kiro/`），无效前缀报错跳过
    - 实现目标目录自动创建（mkdir -p）并输出 notice
    - 源路径解析：`packageRoot + targetPath`
    - 目标路径解析：`projectRoot + targetPath`
    - _Requirements: 2.1, 2.2, 2.3, 2.6, 2.7, 2.8, 2.10, 3.1, 3.3, 3.4, 3.5_
  - [ ] 6.2 实现 Installer 目录级 skill 安装
    - 当 target path 以 `/` 结尾时，使用 `DirectoryMirrorService::mirrorDirectory()` 递归复制
    - 源目录不存在时报错跳过，不中断其余安装
    - 双 target skill 确保两个 target 下内容一致
    - _Requirements: 4.1, 4.2, 4.3, 4.6_
  - [ ] 6.3 实现 Installer gitignore 安装
    - 从 AbilitiesRegistry 获取 GitignoreEntry
    - 调用 GitIgnoreTemplateService 插入 marker block
    - marker 未定义时报错不修改文件
    - _Requirements: 5.1, 5.2, 5.3, 5.7_
  - [ ]* 6.4 编写 Installer 单元测试
    - 测试安装成功路径（rule、agent、skill、gitignore）
    - 测试错误路径：ability 未找到、源文件缺失、无效平台前缀
    - 测试 mkdir-p 行为和 notice 输出
    - 测试 preset 安装（解析 includes 后批量安装）
    - _Requirements: 2.1, 2.2, 2.8, 2.10, 4.2, 4.3_

- [ ] 7. Phase 2 — CheckService 重构
  - [ ] 7.1 重构 CheckService 注入 AbilitiesRegistry
    - 修改 CheckService 构造函数，新增 `AbilitiesRegistry` 参数
    - 移除旧的后缀匹配逻辑
    - 实现从 AbilitiesRegistry 获取 expected paths 的检查流程
    - ability 未找到时返回 status=unknown
    - _Requirements: 2.4, 2.5, 2.9_
  - [ ] 7.2 重构 AbilityDiffService 基于 AbilityEntry
    - 修改 `diffAbility()` 方法接收 AbilityEntry 参数
    - 移除后缀匹配逻辑，使用 targets 路径直接比较
    - _Requirements: 2.4, 2.5, 3.1_
  - [ ] 7.3 实现 Skill 目录级 diff 与 extra files 报告
    - 实现 `diffSkillDirectory()` 方法
    - 比较源目录与目标目录中每个文件的 byte-for-byte 内容
    - 目标目录中存在但源中不存在的文件报告为 `extra_files`
    - extra files 不影响 modified/unchanged 判定
    - _Requirements: 4.4, 4.5_
  - [ ]* 7.4 编写 CheckService 单元测试
    - 测试各状态判定：unchanged、modified、missing、unknown
    - 测试 skill 目录级 diff
    - 测试 extra files 报告
    - _Requirements: 2.4, 2.9, 4.4, 4.5_

- [ ] 8. Phase 2 — GitIgnoreTemplateService 重构
  - [ ] 8.1 重构 GitIgnoreTemplateService 从 AbilitiesRegistry 读取 content
    - 修改 `renderManagedBlock()` 方法从 `GitignoreEntry.content` 读取 pattern
    - 移除旧的模板文件读取逻辑
    - 实现 marker block 插入：`## @apm:block ability=<marker> target=*` / `## @apm:end` 格式
    - 实现已存在 block 的内容替换（保留位置）
    - 实现 block 移除（包含两行 delimiter 和中间内容）
    - .gitignore 不存在时自动创建
    - 未闭合 block 抛出 RuntimeException
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  - [ ]* 8.2 编写 GitIgnoreTemplateService 单元测试
    - 测试 block 插入、替换、移除
    - 测试 .gitignore 文件创建
    - 测试未闭合 block 异常
    - 测试 marker 未定义时的错误处理
    - _Requirements: 5.2, 5.3, 5.4, 5.6_

- [ ] 9. Phase 2 — ProjectInitializer 重构
  - [ ] 9.1 重构 ProjectInitializer 使用硬编码路径
    - 定义 `SCAFFOLD_PATHS` 常量列表
    - 从 `packageRoot` 对应路径读取 scaffold 源文件
    - 移除对 `scaffold/` 目录的依赖
    - 已存在文件跳过不覆盖，输出 `[skip]` 提示
    - 已存在目录跳过创建，继续处理文件
    - 源文件缺失时抛出 RuntimeException
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_
  - [ ]* 9.2 编写 ProjectInitializer 单元测试
    - 测试 scaffold 安装成功路径
    - 测试 skip-on-exist 行为
    - 测试源文件缺失异常
    - _Requirements: 6.1, 6.3, 6.5_

- [ ] 10. Checkpoint — Phase 2 核心模块验证
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 11. Phase 2 — 命令层适配与集成
  - [ ] 11.1 更新所有 Check 命令使用重构后的 CheckService
    - 更新 `RuleCheckCommand`、`AgentCheckCommand`、`SkillCheckCommand`、`CheckCommand`
    - 确保命令正确传递 AbilitiesRegistry 依赖
    - _Requirements: 2.4, 2.5_
  - [ ] 11.2 更新所有 Install/Uninstall 命令使用重构后的 Installer
    - 更新 `RuleInstallCommand`、`AgentInstallCommand`、`SkillInstallCommand`、`InstallCommand`
    - 更新 `RuleUninstallCommand`、`AgentUninstallCommand`、`SkillUninstallCommand`、`PresetUninstallCommand`
    - _Requirements: 2.1, 2.2_
  - [ ] 11.3 更新 ConsoleRegistration 使用新依赖签名
    - 新签名：`register(SymfonyApplication, Installer, CheckService, KnowledgeBaseUpdater, PresetRegistry)`
    - 更新 Application.php 中的调用代码
    - 确认无 capture/ingest 命令残留
    - _Requirements: 7.4_
  - [ ] 11.4 更新 Application.php 中 Service 实例化
    - 创建 AbilitiesRegistry 实例（`AbilitiesRegistry::fromPackageRoot()`）
    - 将 AbilitiesRegistry 注入 Installer 和 CheckService
    - 移除所有 CaptureService/CaptureChangeIngestor 实例化代码
    - _Requirements: 2.1_

- [ ] 12. Checkpoint — 全量测试验证
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 13. State 文档补写
  - [ ] 13.1 编写 docs/state/cli-commands.md
    - 记录最终注册的命令集（排除 capture/ingest）
    - 每个命令：名称、参数（类型、必选/可选）、选项（类型、默认值）
    - 每个命令：成功行为（文件系统副作用、用户输出）
    - 每个命令：错误场景（无效参数、ability 未找到、文件冲突）
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_
  - [ ] 13.2 编写 docs/state/abilities-model.md
    - 记录 abilities.yaml 完整格式（version、各 section）
    - 记录字段约束：必填字段、path 标识符格式、target 平台标识符、target 值格式
    - 记录 preset reference 格式（`type:path` 语法）
    - 记录 gitignore section 结构（marker + description + content）
    - 记录 ability 类型结构差异（file path vs directory path）
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_
  - [ ] 13.3 编写 docs/state/install-behavior.md
    - 记录各 ability 类型的安装逻辑（源解析、复制机制、输出）
    - 记录 check 逻辑（四种 diff 状态、内容比较方法、baseline 解析）
    - 记录 uninstall 逻辑（文件/目录/marker block 移除）
    - 记录边界条件（源缺失、目标目录不存在、权限错误、部分安装）
    - 记录 force flag 语义
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
  - [ ] 13.4 编写 docs/state/gitignore.md
    - 记录 template block delimiter 语法
    - 记录 managed section delimiters（BEGIN/END）
    - 记录 block 渲染规则（匹配、收集、拼接）
    - 记录多 block 行为
    - 记录未闭合 block 异常行为
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6_
  - [ ] 13.5 更新 docs/state/architecture.md
    - 记录活跃模块及单句职责（Command/Config/Core/Service）
    - 记录数据流（abilities.yaml → Services → target paths）
    - 记录 scaffold 初始化流程（独立于 ability 安装流程）
    - 移除 capture/ingest/suffix-based 引用
    - 保留现有 target platform mapping table
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ] 14. Checkpoint — State 文档完整性验证
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 15. 属性测试 — 最终质量加固
  - [ ] 15.1 引入 PhpQuickCheck 依赖
    - 执行 `composer require --dev steos/quickcheck`
    - 创建 `tests/Property/` 目录
    - 配置 PHPUnit 识别 Property 测试目录
    - _Requirements: (testing infrastructure)_
  - [ ]* 15.2 编写 Property 1: Path resolution from targets mapping
    - 创建 `tests/Property/PathResolutionTest.php`
    - Generator：随机生成 AbilityEntry（随机 path、随机 targets）
    - 断言：resolved source = packageRoot + targetPath，resolved dest = projectRoot + targetPath
    - **Property 1: Path resolution from targets mapping**
    - **Validates: Requirements 2.2, 2.4**
  - [ ]* 15.3 编写 Property 2: Platform installation scope matches targets keys
    - 创建 `tests/Property/PlatformScopeTest.php`
    - Generator：随机生成 targets 子集和请求 targets
    - 断言：实际安装平台 = entry.targets.keys ∩ requested targets
    - **Property 2: Platform installation scope matches targets keys**
    - **Validates: Requirements 2.6, 2.7**
  - [ ]* 15.4 编写 Property 3: Platform classification by path prefix
    - 创建 `tests/Property/PlatformClassificationTest.php`
    - Generator：随机生成 `.cursor/` 或 `.kiro/` 前缀路径
    - 断言：分类结果与前缀一致
    - **Property 3: Platform classification by path prefix**
    - **Validates: Requirements 3.1, 3.3, 3.4**
  - [ ]* 15.5 编写 Property 4: Invalid prefix detection
    - 创建 `tests/Property/InvalidPrefixTest.php`
    - Generator：随机生成不以 `.cursor/` 或 `.kiro/` 开头的路径
    - 断言：抛出 unrecognized platform prefix 错误
    - **Property 4: Invalid prefix detection**
    - **Validates: Requirements 3.5**
  - [ ]* 15.6 编写 Property 5: Directory-level classification
    - 创建 `tests/Property/DirectoryLevelTest.php`
    - Generator：随机生成以 `/` 结尾和不以 `/` 结尾的路径
    - 断言：`isDirectoryLevel()` 返回值与路径尾部一致
    - **Property 5: Directory-level classification**
    - **Validates: Requirements 4.1**
  - [ ]* 15.7 编写 Property 6: Directory mirror preserves structure and content
    - 创建 `tests/Property/DirectoryMirrorTest.php`
    - Generator：随机生成目录树结构（文件名、内容）
    - 断言：mirror 后目标中每个文件与源 byte-for-byte 一致
    - **Property 6: Directory mirror preserves structure and content**
    - **Validates: Requirements 4.2**
  - [ ]* 15.8 编写 Property 7: Skill directory diff detects modifications
    - 创建 `tests/Property/DiffModifiedTest.php`
    - Generator：随机生成目录对，至少一个文件内容不同
    - 断言：diffSkillDirectory 返回 status=modified
    - **Property 7: Skill directory diff detects modifications**
    - **Validates: Requirements 4.4**
  - [ ]* 15.9 编写 Property 8: Extra files reported without affecting status
    - 创建 `tests/Property/ExtraFilesTest.php`
    - Generator：随机生成目录对，target 有额外文件但源文件内容一致
    - 断言：status=unchanged 且 extra_files 非空
    - **Property 8: Extra files reported without affecting status**
    - **Validates: Requirements 4.5**
  - [ ]* 15.10 编写 Property 9: Dual-target skill identity
    - 创建 `tests/Property/DualTargetTest.php`
    - Generator：随机生成 skill 内容
    - 断言：安装后 cursor 和 kiro target 下文件树 byte-for-byte 一致
    - **Property 9: Dual-target skill identity**
    - **Validates: Requirements 4.6**
  - [ ]* 15.11 编写 Property 10: Gitignore block formatting and idempotent insertion
    - 创建 `tests/Property/GitignoreBlockTest.php`
    - Generator：随机生成 marker 名和 content 字符串
    - 断言：插入后文件包含正确格式的 block；重复插入结果不变（幂等）
    - **Property 10: Gitignore block formatting and idempotent insertion**
    - **Validates: Requirements 5.2, 5.3**
  - [ ]* 15.12 编写 Property 11: Gitignore block removal round-trip
    - 创建 `tests/Property/GitignoreRoundTripTest.php`
    - Generator：随机生成 .gitignore 内容 + marker
    - 断言：插入后移除恢复原始内容（modulo trailing newline）
    - **Property 11: Gitignore block removal round-trip**
    - **Validates: Requirements 5.4**
  - [ ]* 15.13 编写 Property 12: Scaffold skip-on-exist preserves content
    - 创建 `tests/Property/ScaffoldSkipTest.php`
    - Generator：随机生成已存在文件内容
    - 断言：init 后文件内容 byte-for-byte 不变
    - **Property 12: Scaffold skip-on-exist preserves content**
    - **Validates: Requirements 6.3, 1.6**

- [ ] 16. Final checkpoint — 全量验证
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (final hardening per Design CR-3)
- Unit tests validate specific examples and edge cases
- Execution order follows Design CR-1: capture removal → Phase 1 → Phase 2 → state docs → property tests
- AbilitiesRegistry integration is gradual per Design CR-2: Installer first, then CheckService and others
- TypedCaptureCheckCommandsTest.php handling per Design CR-4: check content first, then decide

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "1.4"] },
    { "id": 2, "tasks": ["1.5", "1.6"] },
    { "id": 3, "tasks": ["1.7"] },
    { "id": 4, "tasks": ["3.1", "3.3"] },
    { "id": 5, "tasks": ["3.2"] },
    { "id": 6, "tasks": ["4.1", "4.2", "4.3", "4.4"] },
    { "id": 7, "tasks": ["4.5"] },
    { "id": 8, "tasks": ["4.6"] },
    { "id": 9, "tasks": ["6.1"] },
    { "id": 10, "tasks": ["6.2", "6.3"] },
    { "id": 11, "tasks": ["6.4", "7.1", "7.2"] },
    { "id": 12, "tasks": ["7.3"] },
    { "id": 13, "tasks": ["7.4", "8.1"] },
    { "id": 14, "tasks": ["8.2", "9.1"] },
    { "id": 15, "tasks": ["9.2", "11.1", "11.2"] },
    { "id": 16, "tasks": ["11.3", "11.4"] },
    { "id": 17, "tasks": ["13.1", "13.2", "13.3", "13.4", "13.5"] },
    { "id": 18, "tasks": ["15.1"] },
    { "id": 19, "tasks": ["15.2", "15.3", "15.4", "15.5", "15.6"] },
    { "id": 20, "tasks": ["15.7", "15.8", "15.9", "15.10", "15.11", "15.12", "15.13"] }
  ]
}
```
