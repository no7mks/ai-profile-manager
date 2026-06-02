---
inclusion: auto
description: 当执行任何 git 操作（特别是 commit 操作）时，都应该读取这个文件
---

# Git Conventions

日常 git 操作的基础规则。

---
## Commit Message 规范

格式：`<action>(scope)[ by <Agent>]: description`

- `+` 新增（功能、文件、配置等）
- `-` 删除 / 移除
- `*` 修改 / 重构 / 更新

- Agent 自动生成的 commit 必须带工具标识：`by Kiro`
- description 用中文描述实际工作内容（保留专有名词、代码名），避免笼统地写「完成 Task 1.2」

示例：

- `+(state): add architecture.md`（人工 commit，无标识）
- `-(command) by Kiro: remove deprecated ListCommand`
- `*(service) by Kiro: refactor delete to use soft-delete`

merge commit 不使用 action 前缀：`merge <source> into <target>`

若 merge 过程中解决了冲突，commit message body 应列出冲突文件及每个文件的解决策略（一句话），格式：

```
merge <source> into <target>

conflicts resolved:
- <file-path>: <一句话说明取舍策略>
```

---
## Commit 范围

- 只 commit 当前任务上下文有关的改动，不要把无关文件混入同一个 commit。
- 提交前先查看分支与工作区状态（如 `git status`，必要时 `git diff` / `git diff --staged`），确认改动与当前任务一致；不要在未看清差异的情况下提交。
- 不要轻易使用 `git add -A`（或等价的一次性大范围暂存）；优先按路径或交互式粒度暂存（如 `git add <path>`、`git add -p`），避免把无关、未完成或未审查的改动混入同一 commit。
- 避免依赖 `git commit -a` 等「自动暂存已跟踪文件」的快捷方式代替上述检查。

---

## Merge 策略（强约束）

- 禁止 rebase 改写历史
- 禁止 squash merge
- 跨分支合并禁止 fast-forward（必须 `--no-ff`），同分支同步（如 pull 同名远程分支）允许 fast-forward
- merge commit 应清晰表达来源分支与目标分支

---

## Worktree 规范

worktree 放在项目同级的 `${repo-name}-worktrees/` 目录下，按分支类型分子目录：

```
../${repo-name}-worktrees/<feature|release|hotfix>/<name>/
```

示例（项目目录为 `~/git/my-project`）：

- `feature/user-auth` → `~/git/my-project-worktrees/feature/user-auth/`
- `hotfix/1.0.1` → `~/git/my-project-worktrees/hotfix/1.0.1/`

---

## 未提交改动保护（强约束）

核心原则：**不得在未经用户授权的情况下丢弃工作区或暂存区的改动。**

以下命令会导致改动不可恢复地丢失，未经用户明确同意禁止执行：

- `git checkout -- <path>`（丢弃工作区文件修改）
- `git restore <path>`（同上，新语法）
- `git reset --hard`（丢弃工作区 + 暂存区所有改动）
- `git clean -f` / `git clean -fd`（删除未跟踪文件/目录）
- `git stash drop` / `git stash clear`（销毁已暂存的改动）

安全替代方案（无需授权即可使用）：

- 需要切换分支但有未提交改动 → `git stash` 保存后再切换
- 需要撤销暂存 → `git reset <path>`（仅取消 stage，不丢改动）
- 需要查看差异 → `git diff` / `git diff --staged`

---

## 非交互约束

- Agent 执行的所有 git 命令必须是非交互式的，不得触发编辑器或等待用户输入
- `git merge --no-ff` 时必须附带 `-m "<message>"` 参数，避免打开编辑器
- 未经过用户允许，不要使用 `git -C` 切换目录
