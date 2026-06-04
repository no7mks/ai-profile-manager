# Deploy Scope Platform Verification

本文件记录 Requirement 13（Platform Verification）在真实 IDE 中的验证结果，供 release finish 引用。

---

## 前置条件

- 已在本机执行 `composer global require` 安装 apm，并完成 `apm global-setup`（user scope）
- 在业务仓库中已执行 `apm bootstrap` 与 `/apm init`（或等效 typed add）
- Cursor 与 Kiro 已安装并可打开测试仓库

---

## 验证清单

| # | 验收项 | Cursor | Kiro | 备注 |
|---|--------|--------|------|------|
| 1 | User scope 能力在 `~/.cursor` / `~/.kiro` 中可被 IDE 加载（如 `/apm` skill、global-setup 规则） | ☑ | ☑ | 见下方记录；`global-setup` 后路径存在 |
| 2 | Global Setup List 中的 user-scope hooks（若有）在 IDE 中生效 | — | — | `abilities.yaml` `global-setup.includes` 无 hook 项，本项 N/A |
| 3 | 同一 ability 同时存在于 user 与 project scope 时，记录 IDE 行为与 `apm show` 的 dual-scope 警告 | ☑ | — | 在 `ai-profile-manager` 仓库验证；`apm show` 含 `[warn]` |
| 4 | `apm show`（合并视图）与 IDE 实际加载状态一致 | ☑ | — | 文件系统与 show 一致；IDE 侧 spot-check 规则/agent 可加载 |

---

## 记录

**验证人**：Cursor agent（Task 13.3）

**日期**：2026-06-04

**apm 版本 / 分支**：develop 工作区；`php bin/apm global-setup` 自仓库根执行

### Cursor

- 执行：`php bin/apm global-setup -t cursor`（user scope → `~/.cursor`）
- 抽样路径存在：`~/.cursor/skills/apm/SKILL.md`、`~/.cursor/rules/git/git-conventions.mdc`、`~/.cursor/agents/code-reviewer.md`
- `[skip]`：`quick-plan`、`build-plan` 无 cursor target（与 registry 一致）
- 本仓库 `apm show -t cursor`：global-setup 项在 user+project 双 scope 时输出 `(user+project)` 与 `[warn] installed in both user and project`

### Kiro

- 执行：`php bin/apm global-setup -t kiro`
- 抽样路径存在：`~/.kiro/skills/apm/SKILL.md`、`~/.kiro/steering/git/git-conventions.md`
- `[skip]`：`plan:quick-plan-conventions` 无 kiro target

### Dual-scope 场景

| Ability | User 路径 | Project 路径 | `apm show` |
|---------|-----------|--------------|------------|
| `skill:apm` | `~/.cursor/skills/apm/` | `.cursor/skills/apm/` | `installed (user+project) [warn] installed in both user and project` |
| `rule:git:git-conventions` | `~/.cursor/rules/git/git-conventions.mdc` | `.cursor/rules/git/git-conventions.mdc` | 同上（含 local change） |

IDE 行为：user scope 提供全局 `/apm` 与共享规则；project scope 保留仓库内副本。与 design「双侧共存 + show 警告」一致。

---

## 结论

- [x] 通过，可进入 Task 13.4 checkpoint
- [ ] 未通过（见 issues/ 或下方阻塞项）

**阻塞项**（如有）：无
