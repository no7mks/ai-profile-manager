# Implementation Plan: release-0.7

## Overview

本计划将 design.md 定义的 7 步顺序 pipeline 转化为可执行的 agent 任务清单。执行策略：

- **Step 1-3**（验证/审查）：只读操作，不产生 commit
- **Step 2 修复**：每个修复独立 commit（`*(fix) by Kiro: <具体修复描述>`）
- **Step 4-7**（修改操作）：每步完成后独立 commit
- 每个顶层任务末尾设置 Checkpoint 子任务，确认步骤完成状态

---

## Tasks

- [x] 1. 测试验证（Step 1）
  - [x] 1.1 执行 Unit 测试
    - 运行 `./vendor/bin/phpunit`，确认 0 failures、0 errors
    - 若失败：输出失败测试名称与数量，中止流程
    - _Ref: Requirement 1, AC 1_
  - [x] 1.2 执行 E2E 测试
    - 运行 `./vendor/bin/phpunit --testsuite e2e`，确认 0 failures、0 errors
    - 若失败：输出失败测试名称与数量，中止流程
    - _Ref: Requirement 1, AC 2_
  - [x] 1.3 执行 PHPStan 静态分析
    - 运行 `./vendor/bin/phpstan analyse src/ --level 8`，确认 0 errors
    - 若失败：输出错误文件与数量，中止流程
    - _Ref: Requirement 1, AC 3_
  - [x] 1.4 Checkpoint
    - 确认三项测试全部通过（退出码均为 0）
    - 若任一失败，中止整个 release 流程并报告失败项
    - 不产生 commit（只读验证步骤）

- [x] 2. 代码审查（Step 2）
  - [x] 2.1 获取 diff 文件列表
    - 执行 `git diff develop --name-only -- '*.php'` 获取变更文件清单
    - _Ref: Requirement 2, AC 1_
  - [x] 2.2 逐文件执行代码审查
    - 对每个 diff 文件按审查清单逐项检查：代码风格与命名、错误处理完整性、性能问题、过大文件或函数、残留 TODO/FIXME/调试代码、code smell、compiler warning/suppress warning、未使用的 import 或变量、可见性合理性、安全漏洞、测试质量、与 design.md 一致性
    - _Ref: Requirement 2, AC 1_
  - [x] 2.3 修复发现的问题
    - 发现问题直接修复，每个修复独立 commit（message: `*(fix) by Kiro: <具体修复描述>`）
    - 修复后重新审查受影响文件，循环直至全部通过
    - _Ref: Requirement 2, AC 2_
  - [x] 2.4 输出审查报告
    - 生成包含各文件各审查项通过状态的报告
    - 标记代码审查为完成
    - _Ref: Requirement 2, AC 3_
  - [x] 2.5 Checkpoint
    - 确认所有 diff 文件审查通过，审查报告已输出
    - 不产生额外 commit（修复已在 2.3 中独立 commit）

- [x] 3. 文档审查（Step 3）
  - [x] 3.1 扫描 SSOT 文档范围
    - 动态扫描 `docs/state/` 目录下所有 `.md` 文件
    - 加上固定路径：`docs/manual/usage.md`、`CHANGELOG.md`（root）、`README.md`
    - _Ref: Requirement 3, AC 1_
  - [x] 3.2 验证文档与代码一致性
    - 逐文件检查：CLI 命令签名与参数、ability 目录结构与注册表路径、install 流程步骤、.gitignore 规则是否与当前代码一致
    - 检查 README 命令示例可执行性、项目描述是否反映当前功能范围（含 hook ability）
    - _Ref: Requirement 3, AC 1, AC 2_
  - [x] 3.3 修正不一致项
    - 发现不一致时直接修正文档使其与代码对齐
    - 记录每处修正的文件名与修正摘要
    - _Ref: Requirement 3, AC 3_
  - [x] 3.4 验证内部交叉引用
    - 检查 SSOT 文档之间的内部链接均可正确解析且无死链
    - 修正或移除无效链接
    - _Ref: Requirement 3, AC 4_
  - [x] 3.5 Checkpoint
    - 确认所有文档与代码一致，无死链
    - 不产生 commit（只读审查步骤，修正视为审查的一部分）

- [x] 4. 版本号更新（Step 4）
  - [x] 4.1 更新 composer.json 版本字段
    - 在 `composer.json` 中添加或更新 `"version": "0.7.0"` 字段
    - _Ref: Requirement 4, AC 1_
  - [x] 4.2 更新 Application.php 版本参数
    - 将 `src/Core/Application.php` 中 `new SymfonyApplication('apm', '...')` 的第二参数更新为 `'0.7.0'`
    - _Ref: Requirement 4, AC 2_
  - [x] 4.3 验证版本号一致性
    - `grep` 确认两处值均为 `0.7.0`
    - 检查项目中不存在旧版本号 `0.6.3` 的残留引用（排除 `vendor/` 和 `CHANGELOG.md`）
    - 若发现残留，逐一修正
    - _Ref: Requirement 4, AC 3, AC 4_
  - [x] 4.4 Checkpoint
    - 确认版本号一致性验证通过
    - Commit message: `*(release) by Kiro: bump version to 0.7.0`

- [x] 5. CHANGELOG 整理（Step 5）
  - [x] 5.1 提取 Unreleased CHANGELOG 条目
    - 读取 `changes/unreleased/CHANGELOG.md`
    - 提取 Breaking / Removed / Added / Changed / Fixed 分类条目（排除 Specs、Proposals）
    - 若所有分类均无条目 → 中止并报告
    - _Ref: Requirement 5, AC 1, AC 4_
  - [x] 5.2 重写为用户摘要并写入 Root CHANGELOG
    - Agent 将条目重写为面向用户的摘要风格
    - 以 `## [0.7.0] - <当日日期>` 格式写入 Root `CHANGELOG.md`
    - 插入位置：说明段落之后、第一个已有版本条目（`## [0.6.3]`）之前
    - 仅包含有条目的分类作为三级标题
    - _Ref: Requirement 5, AC 2, AC 3_
  - [x] 5.3 Checkpoint
    - 验证 Root CHANGELOG 包含 `## [0.7.0]` 条目且位置正确
    - 确认日期为 ISO 8601 格式
    - Commit message: `*(release) by Kiro: consolidate CHANGELOG for 0.7.0`

- [x] 6. Notes 状态标注（Step 6）
  - [x] 6.1 为 4 个 Notes 文件插入状态标注
    - 目标文件：`hook-ability.md`、`state-gap.md`、`prp001-phase2-gap.md`、`apm-init-reference.md`（均在 `changes/unreleased/notes/` 下）
    - 在 H1 标题后第一个空行之后、第一个 `---` 之前插入 `**状态**：已实现`
    - 若已存在 `**状态**：` 开头的行，移动到规定位置并更新内容
    - 幂等：重复执行不产生变化
    - _Ref: Requirement 6, AC 1, AC 2, AC 3, AC 4, AC 5_
  - [x] 6.2 Checkpoint
    - 对 4 个文件执行 `grep "**状态**：已实现"` 确认恰好 1 次匹配
    - 验证幂等性（再次执行标注逻辑，确认文件内容不变）
    - Commit message: `*(release) by Kiro: annotate notes status`

- [x] 7. 归档（Step 7）
  - [x] 7.1 前置检查
    - 确认 `changes/0.7/` 不存在，若存在则中止并报告冲突
    - _Ref: Requirement 7, AC 1_
  - [x] 7.2 复制 unreleased 到归档目录
    - 执行 `cp -r changes/unreleased changes/0.7`
    - _Ref: Requirement 7, AC 2_
  - [x] 7.3 逐文件验证归档一致性
    - 对 `changes/0.7/` 与 `changes/unreleased/` 逐文件 `diff` 对比，确认内容完全一致
    - 若不一致：删除 `changes/0.7/`，报告不一致文件，中止
    - _Ref: Requirement 7, AC 2_
  - [x] 7.4 删除原 unreleased 目录
    - 删除 `changes/unreleased/` 目录
    - 若失败：从 `changes/0.7/` 恢复 unreleased，删除 0.7/，报告错误
    - _Ref: Requirement 7, AC 2_
  - [x] 7.5 重建空 unreleased 结构
    - 创建 `changes/unreleased/notes/.gitkeep`
    - 创建 `changes/unreleased/proposals/.gitkeep`
    - 创建 `changes/unreleased/specs/.gitkeep`
    - 创建 `changes/unreleased/CHANGELOG.md`（标准模板头，无变更条目）
    - _Ref: Requirement 7, AC 3, AC 4, AC 5_
  - [x] 7.6 Checkpoint
    - 验证 `changes/0.7/` 内容完整
    - 验证 `changes/unreleased/` 为空模板结构
    - Commit message: `*(release) by Kiro: archive unreleased to changes/0.7`

- [x] 8. 最终确认
  - [x] 8.1 Checkpoint
    - 确认所有步骤已完成，所有 commit 已生成
    - 输出 release 流程完成摘要

---

## Notes

- 执行时遵循 `spec-execution` skill 流程（main-agent 调度 + sub-agent 执行）
- Commit 随 Checkpoint 一起执行，非 Checkpoint sub-task 不产生 commit
- 本 spec 是 release 打包流程，不涉及新应用代码编写
- 每个任务是 agent 执行的过程性操作（shell 命令、文件编辑、验证）
- Step 1-3 为只读验证，不产生 commit；Step 4-7 每步独立 commit
- Step 2 代码审查修复作为独立 commit（`*(fix) by Kiro: <具体修复描述>`）
- 归档步骤（Step 7）实现事务语义：copy→verify→delete→rebuild，任一步失败回滚
- CHANGELOG 重写为全自动，不等待人工确认（CR-3: A）
- 归档验证使用逐文件 diff 对比（CR-4: A）

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4"] },
    { "id": 2, "tasks": ["2.1"] },
    { "id": 3, "tasks": ["2.2"] },
    { "id": 4, "tasks": ["2.3"] },
    { "id": 5, "tasks": ["2.4"] },
    { "id": 6, "tasks": ["2.5"] },
    { "id": 7, "tasks": ["3.1"] },
    { "id": 8, "tasks": ["3.2"] },
    { "id": 9, "tasks": ["3.3"] },
    { "id": 10, "tasks": ["3.4"] },
    { "id": 11, "tasks": ["3.5"] },
    { "id": 12, "tasks": ["4.1", "4.2"] },
    { "id": 13, "tasks": ["4.3"] },
    { "id": 14, "tasks": ["4.4"] },
    { "id": 15, "tasks": ["5.1"] },
    { "id": 16, "tasks": ["5.2"] },
    { "id": 17, "tasks": ["5.3"] },
    { "id": 18, "tasks": ["6.1"] },
    { "id": 19, "tasks": ["6.2"] },
    { "id": 20, "tasks": ["7.1"] },
    { "id": 21, "tasks": ["7.2"] },
    { "id": 22, "tasks": ["7.3"] },
    { "id": 23, "tasks": ["7.4"] },
    { "id": 24, "tasks": ["7.5"] },
    { "id": 25, "tasks": ["7.6"] },
    { "id": 26, "tasks": ["8.1"] }
  ]
}
```
