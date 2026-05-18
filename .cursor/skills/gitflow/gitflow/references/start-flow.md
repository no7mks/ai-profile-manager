# Start Flow

## Branch Naming

| 类型 | 分支名 | 来源 |
|------|--------|------|
| feature | `feature/<name>` | `develop` |
| release | `release/<version>` | `develop` |
| hotfix | `hotfix/<patch-version>` | `master` |

## Determine Branch Type

根据用户输入判断：

- 提到 `PRP`、`proposal`、`feature`：feature start
- 提到 `release`、`发布`：release start
- 提到 `hotfix`、`紧急修复`：hotfix start
- 无法判断时先询问用户

## Feature Start

1. 确认当前分支是 `develop`（`git branch --show-current`），否则停止并告知先切换。
2. 根据 PRP 编号或 feature 名定位 proposal 文件；找不到则停止并告知。
3. 检查 proposal 的 `Status`：
   - 仅 `accepted` 可继续
   - 其他状态停止并说明原因
4. 从 proposal 文件名或内容确定 `feature/<name>`（kebab-case，不确定则询问）。
5. 创建分支：`git checkout -b feature/<name> develop`。
6. 在 feature 分支把 proposal `Status` 从 `accepted` 更新为 `in-progress` 并提交。
   - 这是唯一允许在非 `develop` 分支改 proposal 状态的场景。

## Release Start

1. 确认当前分支是 `develop`，否则停止并告知先切换。
2. 获取用户提供的版本号（如 `0.4`）；未提供则询问。
3. 创建分支：`git checkout -b release/<version> develop`。

## Hotfix Start

1. 识别 hotfix 来源（issue、note 或用户直接描述的问题）。
2. 检查当前分支；hotfix 必须从 `master` 创建，不在 `master` 需先切换。
3. 读取当前版本并计算 patch + 1 作为 hotfix 版本（格式不明确时询问）。
4. 创建分支：`git checkout -b hotfix/<patch-version> master`。

## Start Completion

报告：

- 创建的分支类型与分支名
- feature 场景的 proposal 状态变更与文件路径
- hotfix 场景的 issue/note 关联信息
