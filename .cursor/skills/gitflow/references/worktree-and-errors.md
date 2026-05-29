# Worktree and Errors

## Worktree Handling

当 `git checkout <branch>` 失败且提示目标分支被其他 worktree 占用时：

1. 明确告知用户当前分支被哪个 worktree 占用。
2. 询问是否允许使用 `git -C <worktree-path>` 在对应 worktree 执行后续操作。
3. 只有在用户确认后，才改为 `git -C <worktree-path>` 执行该分支上的 git 操作。

## Git Command Requirements

- 所有 git 命令必须非交互式。
- `git merge --no-ff` 必须带 `-m "<message>"`。
- 未获得用户允许，不主动使用 `git -C` 切目录执行。
- commit message 与 merge message 必须遵循项目 git conventions。

## Failure Policy

- 任意 git 命令失败：输出错误并立即停止。
- 测试失败：输出失败测试并停止。
- merge 冲突无法自动解决：输出冲突文件并请求用户协助。
- 前置条件检查失败：列出未满足项，不进入后续步骤。

## Branch Already Exists

当 `git checkout -b` 因分支已存在失败时：

- 如果当前已在目标分支：视为继续执行，跳过创建并告知用户。
- 如果目标分支存在但当前不在该分支：告知分支已存在，询问是否切换。
