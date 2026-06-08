# Implementation Plan: release-0.10.0

## Overview

本计划将 design.md 拆解为 4 个实现 task + E2E 测试 + 文档收敛 + Code Review。

关键编排决策（来自 AD）：
- AD-2: 两个 Cursor Skill 合并为一个 task（内容有共性）
- AD-3: abilities.yaml 修改与旧 rule 删除在同一 task 中完成
- AD-4: 版本号 + CHANGELOG + tag 指令合并为一个 release 收口 task

本次变更均为声明式文件（YAML/Markdown），无新 PHP 逻辑，因此 Test First 仅适用于 E2E 验证场景。

## Tasks

- [ ] 1. 创建 Cursor quick-plan 和 build-plan Skill 文件
  - [ ] 1.1 创建 `.cursor/skills/quick-plan/SKILL.md`
    - 基于 Kiro 侧 `.kiro/skills/quick-plan/SKILL.md` 适配 Cursor 平台
    - frontmatter 含 `name: quick-plan` 和 `description`
    - 开头声明 Cursor Plan mode 要求，非 Plan mode 时拒绝执行
    - 路径约定使用 `.cursor/specs/<slug>-plan.md`
    - Plan 模板包含：Goal、Scope、Assumptions、Files、Plan（checkboxes）、Validation、Risks
    - 禁止创建/修改/删除 plan.md 以外的项目文件
    - _Ref: Requirement 5, AC 1-5_
  - [ ] 1.2 创建 `.cursor/skills/build-plan/SKILL.md`
    - 基于 Kiro 侧 `.kiro/skills/build-plan/SKILL.md` 适配 Cursor 平台
    - frontmatter 含 `name: build-plan` 和 `description`
    - 开头声明 Cursor Agent mode 要求，非 Agent mode 时拒绝执行
    - Plan 查找路径：`.cursor/specs/<slug>-plan.md`
    - 计划不存在时告知用户并建议使用 quick-plan
    - Step 4 执行方式为 Agent 直接执行（无 sub-agent）
    - 计划归档路径：`changes/unreleased/specs/<slug>-plan.md`
    - _Ref: Requirement 6, AC 1-4_
  - [ ] 1.3 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`（确认无回归）
    - 确认两个 SKILL.md 文件存在且行数在合理范围（< 100 行各自）
    - commit: `feat(skills): add Cursor quick-plan and build-plan Skills`

- [ ] 2. 更新 abilities.yaml 与删除旧 rule
  - [ ] 2.1 修改 `abilities.yaml` skills section：为 `quick-plan` 和 `build-plan` 增加 `cursor` target
    - `quick-plan` targets 增加 `cursor: .cursor/skills/quick-plan/`
    - `build-plan` targets 增加 `cursor: .cursor/skills/build-plan/`
    - _Ref: Requirement 1, AC 1-3; Requirement 2, AC 1-3_
  - [ ] 2.2 修改 `abilities.yaml` bootstrap.includes：删除 `rule:plan:quick-plan-conventions`
    - _Ref: Requirement 3, AC 2; Requirement 4, AC 1-2_
  - [ ] 2.3 删除 `abilities.yaml` rules section 中 `path: plan:quick-plan-conventions` 条目
    - _Ref: Requirement 3, AC 1_
  - [ ] 2.4 删除源文件 `.cursor/rules/plan/quick-plan-conventions.mdc`
    - _Ref: Requirement 3, AC 3_
  - [ ] 2.5 检查 presets section 中无旧 rule 引用
    - 确认 presets 中无 `rule:plan:quick-plan-conventions` 引用
    - _Ref: Requirement 3, AC 4_
  - [ ] 2.6 修改 `.cursor/rules/cursor-scope.mdc`：移除对旧 rule 的路径引用和加载指令
    - "Plan mode / 快速计划" section 改为引导至新 Skill
    - 项目目录结构表格中 `/.cursor/specs/` 行移除对 `plan/quick-plan-conventions.mdc` 的引用
    - _Ref: Requirement 3, AC 5_
  - [ ] 2.7 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - 运行 `./vendor/bin/phpunit --testsuite e2e` 验证 registry 解析无报错
    - 确认 `grep -r "quick-plan-conventions" abilities.yaml .cursor/rules/cursor-scope.mdc` 无匹配
    - commit: `refactor(abilities): replace quick-plan-conventions rule with Cursor Skills`

- [ ] 3. E2E 测试验证
  - [ ] 3.1 运行 `apm bootstrap` E2E 验证新 Skill 安装
    - 执行 `./vendor/bin/phpunit --testsuite e2e` 
    - 验证 bootstrap 场景正确安装 `quick-plan` 和 `build-plan` 的 cursor target
    - 验证 bootstrap 不再尝试安装已删除的旧 rule
    - _Ref: Requirement 4, AC 3-4_
  - [ ] 3.2 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit --testsuite e2e`
    - commit（如有修正）: `test(e2e): verify bootstrap installs new Cursor Skills`

- [ ] 4. Release 收口
  - [ ] 4.1 更新 `composer.json` version 字段为 `0.10.0`
    - _Ref: Requirement 7, AC 1_
  - [ ] 4.2 更新 `src/Core/Application.php` 版本参数为 `0.10.0`
    - _Ref: Requirement 7, AC 2-3_
  - [ ] 4.3 编写 CHANGELOG 0.10.0 section
    - 在最近 unreleased 位置前新增 `## [0.10.0] - <release-date>`
    - Breaking: 废弃 `--scope` 参数；废弃 `global-setup` 命令
    - Changed: `bootstrap` 统一 scaffold + ability 安装；三阶段首装缩减为两阶段
    - Removed: user scope 概念移除；`quick-plan-conventions` rule 删除
    - Added: `quick-plan` Cursor Skill；`build-plan` Cursor Skill
    - _Ref: Requirement 8, AC 1-4_
  - [ ] 4.4 Checkpoint
    - 运行验证：`./vendor/bin/phpstan analyse && ./vendor/bin/phpunit`
    - 确认 `composer.json` 和 `Application.php` 版本字符串一致
    - commit: `chore(release): prepare v0.10.0`
    - 注：annotated tag `v0.10.0` 在 master merge 后创建（由 gitflow finish 流程执行）
    - _Ref: Requirement 9, AC 1-3_

- [ ] 5. 文档收敛
  - [ ] 5.1 确认 `docs/state/` 无需更新
    - 本次变更不改变 abilities.yaml 格式、不新增模块、不改变安装行为逻辑
    - 若发现有遗漏则同步更新
  - [ ] 5.2 更新知识图谱
    - 执行 `/graphify update` 增量更新变更文件
  - [ ] 5.3 Checkpoint
    - commit: `docs: convergence for release-0.10.0`

- [ ] 6. Code Review
  - 委托给 code-reviewer sub-agent 执行

## Notes

- 遵循 `spec-execution` 流程
- commit 随 checkpoint 一起执行
- Tag 创建属于 gitflow finish 流程，不在本 tasks 中直接执行
- 本次无新 PHP 逻辑，Test First 不适用于 Skill 文件撰写（声明式 Markdown）

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1", "1.2"] },
  { "id": 1, "tasks": ["1.3"] },
  { "id": 2, "tasks": ["2.1"] },
  { "id": 3, "tasks": ["2.2"] },
  { "id": 4, "tasks": ["2.3"] },
  { "id": 5, "tasks": ["2.4", "2.5"] },
  { "id": 6, "tasks": ["2.6"] },
  { "id": 7, "tasks": ["2.7"] },
  { "id": 8, "tasks": ["3.1"] },
  { "id": 9, "tasks": ["3.2"] },
  { "id": 10, "tasks": ["4.1", "4.2"] },
  { "id": 11, "tasks": ["4.3"] },
  { "id": 12, "tasks": ["4.4"] },
  { "id": 13, "tasks": ["5.1", "5.2"] },
  { "id": 14, "tasks": ["5.3"] },
  { "id": 15, "tasks": ["6"] }
]}
```
