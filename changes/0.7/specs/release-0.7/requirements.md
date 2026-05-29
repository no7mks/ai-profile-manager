# Requirements Document

## Introduction

本文档定义 0.7.0 release 打包流程的需求。范围覆盖：测试验证、代码审查、文档审查、版本号更新、CHANGELOG 整理、归档操作、Notes 状态标注。

本次 release 仅打包已完成的 unreleased 变更，不引入新功能或代码逻辑修改。

### Non-scope

- 不引入新功能或修改已有代码逻辑
- 不做跨项目分发
- 不执行 git tag 或 merge 操作（由 gitflow finish 流程负责）

---

## Glossary

- **Release_Process**: 将已完成变更打包为正式版本的完整流程，包含验证、审查、版本更新、归档等步骤
- **Test_Suite**: 项目的自动化测试集合，包含 Unit 测试套件和 E2E 测试套件
- **Static_Analysis**: 使用 PHPStan level 8 对代码进行的静态类型检查
- **Version_Identifier**: 标识软件版本的字符串，遵循语义化版本规范，本次目标为 `0.7.0`
- **Version_Location**: 存储版本号的文件位置，包括 `composer.json` 的 version 字段和 `src/Application.php` 的构造参数
- **Root_CHANGELOG**: 项目根目录的 `CHANGELOG.md`，记录面向用户的版本摘要，格式遵循 Keep a Changelog
- **Unreleased_CHANGELOG**: `changes/unreleased/CHANGELOG.md`，记录面向开发者的详细变更日志
- **Archive_Directory**: `changes/0.7/` 目录，归档后存放本版本的变更记录
- **Unreleased_Directory**: `changes/unreleased/` 目录，存放尚未发布的变更记录，含 `notes/`、`proposals/`、`specs/` 子目录及 `CHANGELOG.md`
- **Notes_File**: `changes/unreleased/notes/` 下的 markdown 文件，记录功能设计笔记
- **Status_Annotation**: Notes_File 头部的 `**状态**：已实现` 标注，表明该笔记对应功能已落地
- **SSOT_Document**: `docs/state/` 和 `docs/manual/` 下的文档，作为系统唯一事实来源

---

## Requirements

### Requirement 1: 测试验证

**User Story:** As a 发布负责人, I want 确认所有自动化测试通过且静态分析无错误, so that 发布的版本质量有保障。

#### Acceptance Criteria

1. WHEN Release_Process 启动时, THE Test_Suite SHALL 执行 Unit 测试套件（`./vendor/bin/phpunit`）并报告结果为 0 failures 且 0 errors
2. WHEN Release_Process 启动时, THE Test_Suite SHALL 执行 E2E 测试套件（`./vendor/bin/phpunit --testsuite e2e`）并报告结果为 0 failures 且 0 errors
3. WHEN Release_Process 启动时, THE Static_Analysis SHALL 对 `src/` 目录以 level 8 执行 PHPStan 并报告 0 errors
4. IF Unit 测试、E2E 测试或 Static_Analysis 中任一报告存在 failure 或 error, THEN THE Release_Process SHALL 中止并输出失败项的名称与错误数量

### Requirement 2: 代码审查

**User Story:** As a 发布负责人, I want 对 release 分支的变更做完整代码审查, so that 发布前能发现并修复潜在问题。

#### Acceptance Criteria

1. WHEN Test_Suite 验证通过后, THE Release_Process SHALL 对 release 分支与 develop 分支的 diff 中所有变更的代码文件逐文件执行代码审查，审查项包括：代码风格与命名、错误处理完整性、性能问题、过大文件或函数、残留 TODO/FIXME/调试代码、code smell、compiler warning、suppress warning 使用、未使用的 import 或变量、可见性合理性、安全漏洞、测试质量、与 design.md 的一致性
2. IF 代码审查发现违反审查项的问题（含 diff 上下文中非本次变更行的无注释 bad smell）, THEN THE Release_Process SHALL 直接修复该问题，修复后重新对受影响文件执行审查，循环直至所有审查项通过
3. WHEN 代码审查所有文件的全部审查项均通过, THE Release_Process SHALL 输出包含各审查项通过状态的审查报告，并标记代码审查为完成

### Requirement 3: 文档审查

**User Story:** As a 发布负责人, I want 审查文档的准确性与一致性, so that SSOT_Document 反映 0.7 最终状态。

#### Acceptance Criteria

1. WHEN 代码审查完成后, THE Release_Process SHALL 逐一审查 SSOT_Document（docs/state/ 下 5 个文档 + docs/manual/usage.md）与代码实现的一致性，验证项包括：CLI 命令签名与参数、ability 目录结构与注册表路径、install 流程步骤、.gitignore 规则均与当前代码行为一致
2. WHEN 代码审查完成后, THE Release_Process SHALL 审查 Root_CHANGELOG 和 README 的准确性，验证项包括：README 中的命令示例可正常执行、项目描述反映当前功能范围（含 hook ability 支持）、Root_CHANGELOG 的 0.7.0 条目覆盖所有用户可见变更
3. IF 文档与代码实现存在不一致, THEN THE Release_Process SHALL 修正文档使其与代码对齐，并在 PR 描述中列出每处修正的文件名与修正摘要
4. WHEN 文档审查完成后, THE Release_Process SHALL 验证 SSOT_Document 之间的内部交叉引用链接均可正确解析且无死链

### Requirement 4: 版本号更新

**User Story:** As a 发布负责人, I want 将版本号统一更新为 0.7.0, so that 所有 Version_Location 反映正确的发布版本。

#### Acceptance Criteria

1. WHEN 文档审查完成后, THE Release_Process SHALL 在 `composer.json` 中添加或更新 `"version"` 字段，使其值为符合语义化版本格式（MAJOR.MINOR.PATCH）的 Version_Identifier `"0.7.0"`
2. WHEN 文档审查完成后, THE Release_Process SHALL 将 `src/Core/Application.php` 中 `createSymfonyApplication` 方法内 `new SymfonyApplication('apm', ...)` 的第二参数更新为 Version_Identifier `'0.7.0'`
3. THE Release_Process SHALL 确保两个 Version_Location 的 Version_Identifier 值完全相同（字符串精确匹配，均为 `0.7.0`）
4. WHEN 版本号更新完成后, THE Release_Process SHALL 验证 `composer.json` 的 `version` 字段值与 `src/Core/Application.php` 中 `SymfonyApplication` 构造的第二参数值均为 `0.7.0`，且不存在对旧版本号的残留引用（`vendor/` 和 `CHANGELOG.md` 除外）

### Requirement 5: CHANGELOG 整理

**User Story:** As a 用户, I want 在 Root_CHANGELOG 中看到 0.7.0 的变更摘要, so that 能快速了解本版本的用户可见变更。

#### Acceptance Criteria

1. WHEN 版本号更新完成后, THE Release_Process SHALL 从 Unreleased_CHANGELOG 中提取 Breaking、Removed、Added、Changed、Fixed 分类的条目作为用户可见变更，排除 Specs 和 Proposals 分类
2. WHEN 变更提取完成后, THE Release_Process SHALL 将提取内容以 `## [0.7.0] - <当日日期>` 条目写入 Root_CHANGELOG，位于文件头部说明段落之后、第一个已有版本条目之前，日期格式为 ISO 8601（YYYY-MM-DD）
3. THE Root_CHANGELOG 的新版本条目 SHALL 遵循 Keep a Changelog 格式，仅包含存在条目的分类作为三级标题（Breaking / Removed / Added / Changed / Fixed），省略无条目的分类
4. IF Unreleased_CHANGELOG 中 Breaking、Removed、Added、Changed、Fixed 分类均无条目, THEN THE Release_Process SHALL 中止 CHANGELOG 整理并报告无用户可见变更

### Requirement 6: Notes 状态标注

**User Story:** As a 发布负责人, I want 为已实现的 Notes_File 补充状态标注, so that 归档后的笔记能明确反映其落地状态。

#### Acceptance Criteria

1. WHEN CHANGELOG 整理完成后, THE Release_Process SHALL 为 4 个 Notes_File 在 H1 标题后的第一个空行之后、第一个 `---` 分隔线之前插入 Status_Annotation
2. THE Status_Annotation SHALL 使用格式 `**状态**：已实现`，独占一行，与前后内容各隔一个空行
3. THE Release_Process SHALL 为以下 4 个 Notes_File 添加 Status_Annotation：`hook-ability.md`、`state-gap.md`、`prp001-phase2-gap.md`、`apm-init-reference.md`
4. IF Notes_File 中已存在匹配 `**状态**：` 开头的行, THEN THE Release_Process SHALL 将该行移动至规定的头部位置并更新内容为 `**状态**：已实现`，而非新增重复行
5. IF Release_Process 对同一 Notes_File 重复执行, THEN THE Release_Process SHALL 保持文件内容不变（幂等）

### Requirement 7: 归档

**User Story:** As a 发布负责人, I want 将 unreleased 变更归档到版本目录, so that 变更历史按版本组织且 unreleased 目录可接收下一轮变更。

#### Acceptance Criteria

1. IF Archive_Directory（`changes/0.7/`）已存在, THEN THE Release_Process SHALL 中止归档操作并报告冲突错误
2. WHEN Notes 状态标注完成后, THE Release_Process SHALL 将 Unreleased_Directory 整体重命名为 Archive_Directory（`changes/0.7/`），保留其中所有文件与子目录内容不变
3. WHEN 归档完成后, THE Release_Process SHALL 重建空的 Unreleased_Directory 结构
4. THE 重建的 Unreleased_Directory SHALL 包含 `notes/`、`proposals/`、`specs/` 三个子目录，每个子目录内含一个 `.gitkeep` 占位文件
5. THE 重建的 Unreleased_Directory SHALL 包含一个 `CHANGELOG.md` 文件，内容仅为标准模板头（标题与用途说明），无变更条目

---

## Clarification Round

> 以下问题用于在进入 design 阶段前确认实现路径。请逐题回答。

### CR-1: 代码审查的执行方式

Requirement 2 要求对 release 与 develop 的 diff 做完整代码审查。Design 需要确定审查的执行模式：

- **A)** 由 agent 自动逐文件审查，发现问题自动修复，无需人工介入
- **B)** 由 agent 逐文件审查并生成审查报告，人工确认后再由 agent 修复
- **C)** 由 agent 生成 diff 文件列表和审查清单，人工逐文件审查并标记问题，agent 辅助修复

**A:** A — 由 agent 自动逐文件审查，发现问题自动修复，无需人工介入

### CR-2: 文档审查的范围确认策略

Requirement 3 AC1 提到 "docs/state/ 下 5 个文档"。Design 需要确定如何定位这 5 个文档：

- **A)** 硬编码当前已知的 5 个文件路径（基于 goal.md 中提到的 state 文档）
- **B)** 动态扫描 `docs/state/` 目录下所有 `.md` 文件作为审查范围
- **C)** 读取某个索引文件（如 `docs/README.md`）确定 state 文档清单

**A:** B — 动态扫描 `docs/state/` 目录下所有 `.md` 文件作为审查范围

### CR-3: CHANGELOG 提取的粒度

Requirement 5 要求从 Unreleased_CHANGELOG 提取用户可见变更写入 Root_CHANGELOG。Design 需要确定提取策略：

- **A)** 逐条原样复制（保留 Unreleased_CHANGELOG 中的原始条目文本）
- **B)** 合并同类条目后精简（如多个相关的 Added 条目合并为一条概括性描述）
- **C)** 由 agent 重写为面向用户的摘要风格（可能改变原始措辞）

**A:** C — 由 agent 重写为面向用户的摘要风格

### CR-4: 归档操作的原子性保障

Requirement 7 涉及重命名 + 重建两步操作。Design 需要确定失败恢复策略：

- **A)** 不做特殊处理——如果中途失败，由人工恢复（release 流程本身在独立分支上，可 reset）
- **B)** 先验证前置条件（目标目录不存在、源目录存在），通过后一次性执行，失败则报告但不回滚
- **C)** 实现完整的事务语义——先复制再删除，任一步失败则回滚到初始状态

**A:** C — 实现完整的事务语义（先复制再删除，任一步失败则回滚到初始状态）
