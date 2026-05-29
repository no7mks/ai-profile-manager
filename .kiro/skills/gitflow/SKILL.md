---
name: gitflow
description: 统一执行 GitFlow 的 start/finish 流程（feature/release/hotfix），包含分支创建、文档收敛、版本/tag/merge 顺序与错误处理。用户提到 start/finish、release、hotfix、proposal 状态流转、分支收口时使用。
---

# Gitflow

用于统一执行 GitFlow start/finish 的单一 skill。

## 触发场景

- 用户提到 `start` / `finish` / `release` / `hotfix` / `feature`
- 用户要执行 proposal 生命周期流转（`accepted -> in-progress -> implemented -> released`）
- 用户要执行发布收口（merge、tag、push、changelog、版本号同步）

## 使用原则

1. 先识别是 **start** 还是 **finish**。
2. 执行任何 git 操作前，先读取并遵循 git 规则文件：
   - Cursor: `.cursor/rules/git/git-conventions.mdc`
   - Kiro: `.kiro/steering/git/git-conventions.md`
3. 需要分支切换时，始终处理 worktree 占用场景。
4. 出错即停并报告，不静默吞错。

## 流程入口

- start 流程：见 [references/start-flow.md](references/start-flow.md)
- finish 流程：见 [references/finish-flow.md](references/finish-flow.md)
- worktree 与错误处理：见 [references/worktree-and-errors.md](references/worktree-and-errors.md)

## 完成输出要求

完成后统一报告：

- 执行类型（feature/release/hotfix 的 start 或 finish）
- 分支与版本信息
- 关键动作摘要（merge/tag/状态流转/文档收敛）
- 遇到的阻断条件或人工决策点（如 worktree `git -C` 授权）
