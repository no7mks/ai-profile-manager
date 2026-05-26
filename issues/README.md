# Issue Management

项目级 issue 存储，记录已确认的缺陷。

---

## Issue ID

格式：`ISS-<5位随机数字>`

- 全局唯一，随机生成，创建前检查 `issues/` 目录不重复即可
- 任何分支均可创建
- 生成方式：`printf '%05d' $((RANDOM % 100000))`（zsh/bash 内置，无需安装）

文件命名：`ISS-<id>-<简短描述>.md`

示例：`ISS-38271-env-var-cannot-override.md`

---

## 目录结构

```
issues/
├── README.md
├── ISS-38271-xxx.md          # open / closed（待发布）
└── fixed/                    # 已发布的修复归档
    └── ISS-12045-yyy.md
```

- `issues/`：活跃 issue（open）和已修复但未发布的 issue（closed）
- `issues/fixed/`：release/hotfix finish 时，已确认发布的 issue 移入此处

---

## 生命周期

```text
发现 → 记录(open) → 修复(closed) → 发布归档(fixed/)
```

| 阶段 | Status | 位置 | Fixed In |
|------|--------|------|----------|
| 未修复 | `open` | `issues/` | 空 |
| 已修复未发布 | `closed` | `issues/` | commit hash 或预判 tag |
| 已发布 | `closed` | `issues/fixed/` | 正式 tag |

### 收敛时机

release/hotfix finish 时：

1. `grep` 找出 `issues/` 中 status=closed 的文件
2. review Found In / Fixed In，替换为正式 tag
3. `mv` 到 `issues/fixed/`

---

## 创建与分流规则

- 确认是 bug → 创建 issue 到 `issues/`
- 不是 bug 的观察/想法 → `docs/notes/`
- feature 开发中发现的已有 bug → 创建 issue（不等 merge）

---

## Found In / Fixed In 规则

| 字段 | 填写规则 |
|------|---------|
| Found In | 最近的已有 tag（如 `v0.1.1`）；无合适 tag 则填 `develop` |
| Fixed In | 有明确 tag（release/hotfix）→ 填 tag；无 tag → 填 commit hash |

### Release / Hotfix Finish 时的 Tag Review

- 将临时值（commit hash、预判 tag）统一替换为正式发布 tag
- Found In 中的 `develop` 如有更精确的 tag 归属，一并修正

---

## Severity

| 级别 | 含义 | 发布门槛 |
|------|------|----------|
| `[P0] critical` | 核心功能不可用 | 阻塞发布 |
| `[P1] major` | 重要功能异常 | 阻塞发布 |
| `[P2] minor` | 非核心功能异常或体验问题 | 需确认是否可接受 |
| `[P3] trivial` | 文档、格式等极低影响 | 不阻塞 |

---

## Issue 文件规范

### 必填字段

| 字段 | 说明 |
|------|------|
| Title | 问题标题 |
| Severity | `[P0]` / `[P1]` / `[P2]` / `[P3]` |
| Status | `open` / `closed` |
| Found In | 见上方规则 |
| Fixed In | 见上方规则；未修复留空 |
| Related Test | 关联测试项；无则留空 |

### 必填章节

- Description：问题描述
- Steps to Reproduce：复现步骤
- Expected Behavior：期望行为
- Actual Behavior：实际行为
- Analysis：原因分析（可选）
- History：事件记录（按时间倒序）

### History 格式

```markdown
## History

- `2026-03-26 17:00 +08` `v0.2.0` [发现] 描述
- `2026-03-26 23:00 +08` `abc1234` [修复] 描述
- `2026-03-27 18:00 +08` `v0.2.0` [关闭] 验证通过
```

- 格式：`` `北京时间` `tag或commit` [事件类型] 描述 ``
- 时间格式：`YYYY-MM-DD HH:mm +08`
- 事件类型：`发现` / `分析` / `修复` / `关闭` / `重开` / `延后`
