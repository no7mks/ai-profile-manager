# ISS-01592 isInstalledOnTarget 对带 category 的 rule 匹配失败

| 字段 | 值 |
|------|-----|
| Severity | `[P2] minor` |
| Status | `open` |
| Found In | `v0.8.0` |
| Fixed In | |
| Related Test | |

---

## Description

`Installer::isInstalledOnTarget()` 中 rule 类型的检测逻辑使用 `$fileInfo->getBasename() === $name . $suffix` 进行文件名匹配。当 `$name` 为带 category 前缀的格式（如 `plan:quick-plan-conventions`）时，实际文件位于子目录中（`plan/quick-plan-conventions.mdc`），`getBasename()` 只返回 `quick-plan-conventions.mdc`，永远不等于 `plan:quick-plan-conventions.mdc`。

此 bug 在 ISS-31532 触发后才暴露——当 baseline 不可用时 `show` 的 fallback 路径依赖 `isInstalledOnTarget`，此时带 category 的 rule 全部被判定为 not-installed。

---

## Steps to Reproduce

1. baseline 不可用（ISS-31532 场景）或 diff 返回非 unchanged/modified 状态
2. 执行 `apm show -t cursor`
3. 带 category 前缀的 rule（如 `plan:quick-plan-conventions`、`gatekeeping:gk-design` 等）即使磁盘上文件存在，仍显示 `[not-installed]`

---

## Expected Behavior

`isInstalledOnTarget` 应正确判定带 category 子目录的 rule 文件已安装。

---

## Actual Behavior

所有带 category 前缀的 rule 被误报为 not-installed。

---

## Analysis

`isInstalledOnTarget` 使用递归遍历 + basename 匹配的方式查找文件，但没有考虑 name 中冒号分隔的 category 实际对应子目录路径。

正确做法不是 strip category 再匹配 basename——因为不同 category 子目录下可能存在同名文件，它们是不同的 rule。应该将冒号转换为路径分隔符后，与文件相对于 rules/steering 根目录的相对路径做完整匹配；或者直接从 registry 取 `targets[target]` 完整路径检查文件是否存在（与 `resolveSourcePath` 逻辑一致）。

---

## History

- `2026-06-03 17:30 +08` `v0.8.0` [发现] 调查 `plan:quick-plan-conventions` 显示 not-installed 时发现
