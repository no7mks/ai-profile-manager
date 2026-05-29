# Spec Goal: Release 0.7.0

## 来源

- `changes/unreleased/proposals/PRP-001-abilities-relocation.md`（status: implemented）
- `changes/unreleased/notes/hook-ability.md`
- `changes/unreleased/notes/state-gap.md`
- `changes/unreleased/notes/prp001-phase2-gap.md`
- `changes/unreleased/notes/apm-init-reference.md`
- `changes/unreleased/CHANGELOG.md`

## 背景摘要

自 0.6.3 以来，develop 分支上完成了两个大型 spec（`abilities-relocation` + `prp001-remaining-work`），涵盖：

1. **PRP-001 Abilities Relocation**：将 `abilities/` 和 `scaffold/` 迁移为"源即目标"结构，`abilities.yaml` 作为唯一注册表，CLI 代码全面适配新路径
2. **功能精简**：移除 capture/ingest 相关代码与命令
3. **Hook Ability 支持**：新增 hook 类型 ability（Kiro 文件复制 + Cursor hooks.json merge）
4. **State 文档补写**：`docs/state/` 下 5 个 SSOT 文档完整建立
5. **apm init reference**：`init-workflow.md` 详细流程文档落地
6. **质量提升**：PHPStan level 8 零 baseline、测试全面修复

所有 unreleased notes 和 PRP-001 均已落地实现，现在需要打包为 0.7.0 正式发布。

## 目标

将 `changes/unreleased/` 中的全部已完成变更打包为 0.7.0 release：

1. **测试验证**：确保全部测试通过（Unit + E2E），覆盖率达标；PHPStan level 8 零 error
2. **Code Review**：对 release 分支与 develop 的 diff 做完整 code review，修复发现的问题
3. **Doc Review**：审查 `docs/state/`、`docs/manual/`、`CHANGELOG.md`、`README.md` 等文档的准确性与一致性，确保 SSOT 反映 0.7 最终状态
4. **版本号 bump**：`composer.json` + `src/Application.php` 更新为 `0.7.0`
5. **CHANGELOG 整理**：从 `changes/unreleased/CHANGELOG.md` 提炼用户可见摘要写入根 `CHANGELOG.md`
6. **归档**：`changes/unreleased/` 重命名为 `changes/0.7/`，重建空的 `changes/unreleased/` 结构
7. **Notes 状态修正**：归档前为 4 个 notes 文件头部补充 `状态：已实现` 标注

## 不做的事情（Non-Goals）

- 不引入新功能或代码变更
- 不修改已有代码逻辑
- 不做跨项目分发或版本管理
- 不做 git tag 或 merge 操作（由 gitflow finish 流程负责）

## Clarification 记录

### Q1: release 0.7 的范围？
**A**: 仅打包已有 unreleased 变更（PRP-001 + 4 个 notes 全部已落地），版本号 bump + CHANGELOG 整理 + 归档即可，不加新内容。

### Q2: 版本号？
**A**: 0.7.0 正确——Breaking changes 在 0.x 阶段用 minor bump 表达。

### Q3: notes 状态标注？
**A**: 归档前先修正状态标注（在 note 文件头部加 `状态：已实现`）。

## 约束与决策

- 版本号遵循语义化版本，0.x 阶段 Breaking 用 minor bump
- Notes 状态标注格式：文件头部加 `**状态**：已实现`
- CHANGELOG 格式参考 Keep a Changelog
- 归档后重建 `changes/unreleased/` 空结构（含 notes/、proposals/、specs/ 子目录 + 空 CHANGELOG.md）
