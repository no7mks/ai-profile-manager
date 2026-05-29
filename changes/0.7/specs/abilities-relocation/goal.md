# Spec Goal: PRP-001 Phase 2 — CLI 适配、Hook 支持与 State 文档补写

## 来源

- `docs/proposals/PRP-001-abilities-relocation.md`（Phase 2 + 功能精简）
- `docs/notes/hook-ability.md`
- `docs/notes/state-gap.md`

## 背景摘要

PRP-001 Phase 1 已完成：`abilities/` 和 `scaffold/` 目录废弃，文件迁移到真实生效路径（`.cursor/`、`.kiro/`），`abilities.yaml` 注册表已建立。

但 CLI 代码仍停留在旧架构：
- `src/Capture/` 模块及 capture/ingest 命令仍存在
- install/check 逻辑尚未完全适配新的路径前缀判定方式
- 后缀解析逻辑残留

同时有两个待办 note 需要在本 spec 中一并解决。

## 目标

### 1. CLI 代码适配新布局（PRP-001 Phase 2）

- install/check/uninstall 命令从 `abilities.yaml` 解析 ability 列表，从真实路径读取文件
- 废弃后缀解析逻辑，改为路径前缀判定
- gitignore 命令直接操作 `.gitignore` 中的 marker block
- skill 的 install 逻辑处理目录级 duplicate

### 2. 功能精简

- 移除 `capture` 相关代码（`src/Capture/`、`CaptureCommand`、`RuleCaptureCommand`、`SkillCaptureCommand`、`AgentCaptureCommand`）
- 移除 `ingest` 相关代码（`IngestCaptureChangeCommand`）
- 移除对应的命令注册、测试、fixture

### 3. Hook Ability 支持

- `abilities.yaml` 新增 `hooks` 段，定义 hook ability 注册格式
- Kiro 侧：文件级复制安装（`.kiro/hooks/<name>.kiro.hook`）
- Cursor 侧：合并到 `.cursor/hooks.json`（JSON merge 逻辑）
- install/check/uninstall 逻辑覆盖 hook 类型
- `apm show` 展示 hook 类型 ability

### 4. State 文档补写

本 spec 全部代码工作完成后，统一补写 `docs/state/` 文档，反映系统最终状态（含 hook 支持）。拆分为：
- `architecture.md`（补充模块职责细节）
- `cli-commands.md`（命令签名、参数、行为、错误场景）
- `abilities-model.md`（abilities.yaml 格式、字段约束、preset 引用格式）
- `install-behavior.md`（install/check/uninstall 逻辑、边界条件、diff 判定）
- `gitignore.md`（marker block 格式与操作规则）

## 不做的事情（Non-Goals）

- 跨项目 ability 分发
- ability 版本管理
- capture/ingest 功能的替代方案
- Kiro ↔ Cursor hook 事件类型的自动映射（只做各自平台的原生支持）

## Clarification 记录

### Q1: 三块工作（Phase 2 CLI、hook、state-gap）的组织方式？
**A**: 三者全部纳入同一个 spec。

### Q2: 执行顺序偏好？
**A**: 不关心顺序，交给 tasks 阶段按最优效率排列。

### Q3: State 文档的内容边界？
**A**: 反映本 spec 全部完成后的系统最终状态（含 hook 支持）。鉴于 Phase 1 已完成但未同步更新 state，允许在本 spec 最后统一补写（对"每个 task 都更新 state"约定的折扣）。

## 约束与决策

- 本 spec 在 `feature/abilities-relocation` 分支上继续推进
- State 文档放在所有代码改动之后统一补写，不逐 task 更新
- Hook 的跨平台事件映射不在本次范围内——某些 hook 可能只有单平台 target
- Cursor 侧 hook 安装策略（合并 vs 独立文件）需在 design 阶段细化
- 卸载时从 `hooks.json` 精确移除条目的机制需在 design 阶段确定
