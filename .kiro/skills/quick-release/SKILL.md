---
name: quick-release
description: 直接从 master 或 develop 上执行快速发布，不创建 release/hotfix 分支。保留文档收敛、构建验证、版本号更新、tag 等核心步骤，适用于小版本或紧急场景。
---

# Quick Release

在不走 release/hotfix 分支的前提下，直接从 master 或 develop 完成发布。

## 触发场景

- 用户提到 `quick release`、`快速发布`、`直接发布`
- 用户明确表示不需要创建 release/hotfix 分支但仍要走发布流程
- 小版本 patch 或文档修正等低风险发布

## 使用原则

1. 执行任何 git 操作前，先读取并遵循 git 规则文件：`.kiro/steering/git/git-conventions.md`
2. 出错即停并报告，不静默吞错。
3. 文档收敛步骤参照 doc-convergence steering（`.kiro/steering/doc/doc-convergence.md`）。
4. 不创建额外分支（但 merge/tag 阶段会在 master 与 develop 间切换）。

## 执行协议

每个 Step 进入时输出：

```
▶ Step N: <步骤名称>
准备做：<本步骤要完成的事项概述>
```

每个 Step 结束时输出：

```
✓ Step N 完成：<简略总结>
```

重要的 sub-step 完成时也可输出简短总结。

---

## Step 1: 环境检测

```bash
git branch --show-current
```

允许的来源分支：

| 来源 | 适用场景 |
|------|---------|
| `develop` | 常规小版本发布（minor / patch） |
| `master` | 仅文档或配置修正的 patch 发布 |

其他分支 → 停止并告知用户需先切换到 master 或 develop。

---

## Step 2: 前置检查

以下任一失败即停止：

1. 工作区干净（无未提交改动）：`git status --porcelain` 输出为空
2. master 与 develop 无分歧（develop 已包含 master 的所有 commit）：`git merge-base --is-ancestor master develop`；若失败则提示用户先同步或改用 gitflow finish
3. 全量测试通过
4. 构建成功
5. 无 critical/major open issue 阻塞（检查 `issues/` 目录）

---

## Step 3: 确定版本号

1. 读取当前版本声明（`PROJECT.md` 或项目配置文件中的版本字段）。
2. 用户提供目标版本号；未提供则询问。
3. 确认版本号符合 semver 且大于当前版本。

---

## Step 4: 文档收敛

按 doc-convergence steering（`.kiro/steering/doc/doc-convergence.md`）执行全套归档流程：

1. 归档 notes（已解决的 → `changes/unreleased/notes/`）
2. 归档 proposals（status → `released`，移入 `changes/unreleased/proposals/`）
3. 归档 specs（移入 `changes/unreleased/specs/`）
4. 归档 issues（closed 的移入 `issues/fixed/`，更新 Fixed In 为 `v<version>`）
5. 确认 state / manual 一致性
6. 更新 `changes/unreleased/CHANGELOG.md`
7. **Release 归档**：rename `changes/unreleased/` → `changes/<version>/`
8. 从 `changes/<version>/CHANGELOG.md` 提炼摘要写入根 `CHANGELOG.md`
9. 重建空的 `changes/unreleased/{notes,proposals,specs}/` 及空 `CHANGELOG.md`

与 gitflow finish 的差异：

- Proposal status 直接标记 `released`（跳过 `implemented` 中间态，因为 quick-release 本身即发布动作）。

收敛完成后统一提交：`*(doc) by Kiro: release <version> 文档收敛`

---

## Step 5: 更新版本号

1. 更新项目中所有版本号声明（`PROJECT.md`、`package.json` 等）。
2. 提交：`*(version) by Kiro: bump version to <version>`

---

## Step 6: 构建验证

再次执行全量测试 + 构建，确认版本号更新后仍通过。失败则停止。

---

## Step 7: Merge 与 Tag

发布始终从 master 出发：tag 打在 master 上，最终从 master 同步回 develop。

### 来源为 develop

需要先将 develop 合并到 master：

1. `git checkout master`
2. `git merge --no-ff develop -m "merge develop into master"`

### 来源为 master

无需额外 merge，直接继续。

### 公共步骤（两条路径汇合后）

1. 在 master 上打 tag：`git tag v<version>`
2. 推送：`git push origin master v<version>`
3. 将 master 同步回 develop：
   - `git checkout develop`
   - `git merge --no-ff master -m "merge master into develop"`
   - `git push origin develop`

---

## Step 8: 清理预发布 Tag（可选）

如存在该版本的预发布 tag（`-alpha*`、`-beta*`、`-rc*`），删除本地和远程：

```bash
git tag -d v<version>-<prerelease>
git push origin --delete v<version>-<prerelease>
```

---

## 分支保护（强约束）

- 不创建任何额外分支。
- 不删除任何分支。

---

## Completion

统一输出：

- 来源分支
- 版本号与 tag
- 关键操作摘要（文档收敛、merge、tag、push）
- 最终分支状态
