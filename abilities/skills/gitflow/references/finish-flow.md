# Finish Flow

## Step 0: Branch Detection

先执行：

```bash
git branch --show-current
```

分支前缀路由：

- `feature/*` -> Feature Finish
- `release/*` -> Release Finish
- `hotfix/*` -> Hotfix Finish
- 其他分支 -> 停止并报告不可执行 finish

## Feature Finish

前置检查（任一失败即停止）：

1. 全量测试通过
2. 构建成功

步骤：

1. 同步 develop 到 feature：`git merge --no-ff develop -m "merge develop into feature/<name>"`
2. 解决冲突后提交，并重新跑全量测试
3. 文档收敛：更新 state、写 unreleased 变更记录、收敛 resolved notes，然后提交
4. 切回 `develop` 并合并 feature：`git merge --no-ff feature/<name> -m "merge feature/<name> into develop"`
5. 更新 proposal 状态：`in-progress -> implemented` 并提交

## Release Finish

前置检查（任一失败即停止）：

1. 无 critical/major open issue 阻塞
2. 全量测试通过

步骤：

1. Issue 收敛（release issues + 项目级 issues + Found In/Fixed In tag review）
2. 文档收敛：
   - 版本 CHANGELOG
   - spec 归档
   - proposal `implemented -> released` 并归档终态 proposal
   - 更新根 `CHANGELOG.md`
   - 清理已纳入 release 的 unreleased 与 resolved notes
   - 确保 state/manual 与代码一致
3. 更新版本号声明，测试通过
4. 提交收敛变更，并在 release 分支预检冲突：
   - `git merge --no-ff master -m "merge master into release/<version>"`
5. 严格顺序执行：
   - release -> master
   - `git tag v<version>`
   - `git push origin master v<version>`
   - 删除预发布 tag（`-alpha*` / `-beta*`）
   - master -> develop
6. 禁止 release 直接合并 develop

## Hotfix Finish

前置检查（任一失败即停止）：

1. hotfix 分支有修复 commit
2. 全量测试通过

步骤：

1. Issue 收敛：置 `closed`、填写 `Fixed In`、归档 fixed
2. 文档收敛：版本 CHANGELOG、根 CHANGELOG、spec/state/manual 同步、清理相关 resolved notes
3. 更新 patch 版本号声明并测试通过
4. 提交收敛变更，并在 hotfix 分支预检冲突：
   - `git merge --no-ff master -m "merge master into hotfix/<version>"`
5. 严格顺序执行：
   - hotfix -> master
   - `git tag v<version>`
   - `git push origin master v<version>`
   - master -> develop
6. 禁止 hotfix 直接合并 develop

## Finish Completion

统一输出：

- 执行类型（feature/release/hotfix）
- 关键操作摘要（merge、tag、文档/issue/proposal 处理）
- 最终分支状态
