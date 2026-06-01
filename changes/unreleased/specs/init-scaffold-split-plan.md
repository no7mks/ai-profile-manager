# init scaffold 拆分：修复 ISS-32020 + 区分新/旧项目

## Goal

1. 修复 ISS-32020：scaffold 不再将 apm 自身业务文档复制到用户项目
2. 实现 init 新/旧项目分流：Detection Phase 后根据元数据丰富度自动选择"检测式"或"规划式"路径

## Scope

- `src/Service/ProjectInitializer.php`：scaffold 逻辑从 `mirrorDirectory` 全量递归改为显式复制指定文件 + 创建目录骨架
- `apm init` workflow（skill reference）：新增分流判断 + 规划式路径定义
- 相关测试

## Assumptions

- 不新建 `scaffold/` 目录，源文件直接取自包根（`docs/README.md`、`issues/README.md`、`AGENTS.md`）
- scaffold 只复制：`docs/README.md` + 子目录 `.gitkeep`、`issues/README.md`、`AGENTS.md`
- "规划式 init" 是 Agent 侧行为（skill reference 定义），不需要新增 PHP 代码
- 分流判断标准：检测到 package manifest **且** 存在 git history → 检测式；否则 → 规划式

## Files

- `src/Service/ProjectInitializer.php` — 改为显式复制指定文件 + 创建目录骨架
- `.cursor/skills/apm/references/init-workflow.md` — 新增分流逻辑
- `.kiro/skills/apm/references/init-workflow.md` — 同步
- `tests/ProjectInitializerTest.php` — 更新测试
- `tests/E2E/` — 涉及 `apm install` 的 E2E 测试如需调整则更新
- `issues/ISS-32020-scaffold-mirrors-package-docs.md` — 标记 closed

## Plan

- [x] Step 1: 编写测试——验证 scaffold 只在目标目录创建骨架（README + .gitkeep），不复制 state/*.md 等业务文档（RED）
- [x] Step 2: 修改 `ProjectInitializer::init()`——不再用 `mirrorDirectory` 复制 docs/issues，改为显式复制 `docs/README.md`、`issues/README.md`、`AGENTS.md` + 创建子目录 + `.gitkeep`（GREEN）
- [x] Step 3: 运行全量测试（含 E2E）确认无回归，如有测试依赖旧 scaffold 行为则一并修正
- [x] Step 4: 更新 init-workflow skill reference（cursor + kiro 两端同步）：
  - 在 Detection Phase 末尾新增 "Routing" 小节，定义分流判断（manifest + git history → 检测式；否则 → 规划式）
  - 新增 "Section 6: Planning-mode Path" 定义规划式路径的 Agent 行为（交互引导收集选型意图、生成 PROJECT.md + state baseline + manual 骨架）
  - 更新 Existing-Content Strategy 的决策矩阵，补充规划式路径下的文件生成策略
- [x] Step 5: 关闭 ISS-32020（更新 Status=closed, Fixed In）
- [x] Step 6: 归档 `docs/notes/init-new-vs-existing.md`（内容已落地到 init-workflow）
- [x] Step 7: state 文档同步（`docs/state/install-behavior.md` 更新 scaffold 行为描述）
- [x] Step 8: manual 文档同步（`docs/manual/usage.md` 如有 scaffold 相关说明则更新）
- [x] Step 9: code-review
- [x] Step 10: final checkpoint（全量测试 + commit）

## Validation

- `./vendor/bin/phpunit` 全量通过
- `./vendor/bin/phpunit --testsuite e2e` 通过
- 手动验证：在空目录执行 `apm install` 后，`docs/state/` 和 `docs/manual/` 只含 `.gitkeep`，不含 apm 自身文档

## Risks

- 规划式 init 的 Agent 行为定义需要足够清晰，避免 Agent 在空项目上仍然产出大量 TODO
- E2E 测试中 `apm install` 的 fixture 可能需要适配新的 scaffold 行为
