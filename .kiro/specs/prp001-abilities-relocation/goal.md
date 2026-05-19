# Spec Goal: Abilities Relocation

## 来源

- Proposal: `docs/proposals/PRP-001-abilities-relocation.md`（status: in-progress）
- Note: `docs/notes/state-gap.md`（state 文档缺口，PRP-001 完成后补写）

## 背景摘要

apm 项目正在将 ability 文件从独立模板仓库（`abilities/` + `scaffold/`）迁移为"源即目标"结构——文件直接放在 `.cursor/` 和 `.kiro/` 下生效。Phase 1（文件迁移）已部分完成，需确认剩余并补齐；Phase 2（代码适配）尚未开始。

同时，`docs/state/` 当前仅有极简 `architecture.md`，缺少 CLI 命令体系、数据模型、安装行为等系统行为细节，需在本次一并补写。

## 目标

1. **完成 Phase 1 文件迁移** — 确认 PRP-001 迁移映射表中所有文件已就位；未完成的补齐
2. **Phase 2 代码适配** — apm CLI 代码全面适配新文件布局：
   - install/check 从 `abilities.yaml` 解析，从真实路径读取
   - 废弃后缀解析逻辑，改为路径前缀判定
   - gitignore 命令直接操作 `.gitignore` marker block
   - skill install 处理目录级 duplicate
   - scaffold install 从项目根目录读取模板（硬编码路径，不走 abilities.yaml）
3. **移除 capture/ingest** — 直接删除相关代码、命令注册、测试，不保留兼容层
4. **补写 state 文档** — 按 `docs/notes/state-gap.md` 建议，补写完整的 `docs/state/` 文档体系：
   - `cli-commands.md`：命令签名、参数、行为、错误场景
   - `abilities-model.md`：abilities.yaml 格式、字段约束、preset 引用格式
   - `install-behavior.md`：install/check/uninstall 逻辑、边界条件、diff 判定
   - `gitignore.md`：marker block 格式与操作规则
   - 更新 `architecture.md`：反映新模块职责与数据流

## 不做的事情（Non-Goals）

- 跨项目 ability 分发
- Ability 版本管理
- 重新引入 capture/ingest 或任何兼容层
- 旧数据迁移脚本（不兼容旧格式）

## Clarification 记录

| # | 问题 | 回答 |
|---|------|------|
| 1 | Spec 范围：Phase 1 + Phase 2 还是分开？ | Phase 1 已部分完成需确认补齐，Phase 2 必须做，一个 spec 覆盖 |
| 2 | state 文档补写是否纳入本次 spec？ | 纳入，tasks 中包含完整 state 补写 |
| 3 | capture/ingest 移除策略？ | 直接删除，不保留兼容层 |

## 约束与决策

- `abilities.yaml` 是 apm 唯一 ability 注册入口
- Skills 在 cursor/kiro 两个 target 下保持完全相同的 duplicate，接受冗余
- Scaffold 不纳入 `abilities.yaml`，由 `apm init` 硬编码路径处理
- State 文档应反映最终实际状态（代码改造完成后的行为）
- 旧 `abilities/` 和 `scaffold/` 目录废弃后删除
