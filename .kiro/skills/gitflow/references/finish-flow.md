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
3. 文档收敛（按 doc-convergence steering 执行）：
   - 归档 notes
   - 确认 state / manual 一致性
   - 更新 `changes/unreleased/CHANGELOG.md`（详细）和根 `CHANGELOG.md` `[Unreleased]`（摘要）
   - 统一提交
4. Spec 归档：`mv .kiro/specs/<spec-name>/ → changes/unreleased/specs/<spec-name>/`
5. 切回 `develop` 并合并 feature：`git merge --no-ff feature/<name> -m "merge feature/<name> into develop"`
6. 归档 proposal（status `in-progress -> implemented`，移入 `changes/unreleased/proposals/`），提交

## Release Finish

前置检查（任一失败即停止）：

1. 无 critical/major open issue 阻塞
2. 全量测试通过

步骤：

1. Issue 收敛：
   - 找出 `issues/` 中 status=closed 的 issue
   - Review Found In / Fixed In，替换为正式 release tag
   - `mv` 到 `issues/fixed/`
2. 文档收敛（按 doc-convergence steering 执行）：
   - Release 归档：`changes/unreleased/` rename 为 `changes/<version>/`
   - 在 `changes/<version>/CHANGELOG.md` 中记录本版本修复的 issue 编号
   - 版本 CHANGELOG：将 `[Unreleased]` 归入新版本小节
   - Spec 归档（如有未归档的 spec）
   - proposal status → `released`
   - 重建空的 `changes/unreleased/{notes,proposals,specs}/`
   - 确认 state/manual 一致性
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

1. Issue 收敛：
   - 找出 `issues/` 中 status=closed 的 issue
   - Review Found In / Fixed In，替换为正式 hotfix tag
   - `mv` 到 `issues/fixed/`
2. 文档收敛（按 doc-convergence steering 执行）：
   - 版本 CHANGELOG、根 CHANGELOG
   - 在 `changes/<version>/CHANGELOG.md` 中记录本版本修复的 issue 编号
   - 确认 state/manual 一致性
   - 归档 notes
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
