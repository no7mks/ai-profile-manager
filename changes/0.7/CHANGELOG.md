# Changelog (Unreleased)

面向开发者的详细变更日志。Release 时随目录 rename 为 `changes/<version>/CHANGELOG.md`。

---

## Breaking

- Ability 文件从 `abilities/` 独立目录迁移到真实生效路径（`.cursor/`、`.kiro/`），不再使用后缀区分 target。
- 新增 `abilities.yaml` 作为 ability 注册表，取代原有的目录扫描逻辑。
- `scaffold/` 目录废弃，模板文件直接维护在项目根目录对应位置。
- Issue 体系简化：取消 L/release 系列分类，统一 `ISS-<5位随机数字>` 编号；归档位置改为 `issues/fixed/`。
- `docs/changes/` 提升为根目录级 `changes/`。

## Removed

- 移除 `capture`、`skill:capture`、`rule:capture`、`agent:capture`、`ingest` 命令及相关代码。
- 移除 `listFromFilesystem` fallback 逻辑，install/check 统一从 `abilities.yaml` 解析。

## Added

- 新增 hook 类型 ability 支持（Kiro 和 Cursor 双平台），可通过 `install`/`uninstall`/`check` 统一管理。
- `show` 命令新增 `--type` 过滤选项，支持按 ability 类型筛选展示。
- 新增 `docs/state/` 系列文档：`architecture.md`、`cli-commands.md`、`abilities-model.md`、`install-behavior.md`、`gitignore.md`。
- 新增 `docs/manual/usage.md` 使用手册。
- 新增 `changes/` 根目录级归档体系，含 `changes/README.md` 定义归档规则。
- 新增 `issues/fixed/` 作为已发布 issue 的统一归档位置。
- 引入 PHPStan level 8 静态分析，零 baseline。
- 新增 `apm init` reference 文档（`init-workflow.md`），定义探测/确认/生成流程。

## Changed

- doc-convergence steering 更新：路径从 `docs/changes/` 改为 `changes/`，新增 spec 归档章节，issue 归档改为 `issues/fixed/`。
- gitflow finish-flow 更新：所有分支 finish 均可收敛 issue（不限 release），spec 归档由 finish 流程负责。
- issue History 时间格式从 UTC 改为北京时区 `+08`。
- Installer 路径解析重构为 Source-is-Target 模式，从 `abilities.yaml` targets 字段获取源路径。
- AbilityDiffService baseline 解析适配新布局，支持 no-baseline/new 状态。
- PresetRegistry 从 JSON 文件迁移到 `abilities.yaml` presets section。
- Preset 命令改为依赖注入 PresetRegistry，消除测试污染。
- `show` 命令 `--type` 过滤改为从 abilities.yaml 解析。

## Fixed

- 修复 E2E 测试 fixture 适配新布局。
- 修复单元/集成测试 fixture 适配 Source-is-Target 布局。
- 消除 PresetRegistryTest/PresetManifestCommandsTest 中重复的 removeDir 逻辑。

---

## Specs

- `abilities-relocation`：将 abilities/scaffold 迁移为源即目标结构，简化 apm 功能范围。
- `prp001-remaining-work`：PRP-001 Phase 2 代码适配、测试修复、质量提升（PHPStan level 8、行覆盖率 97%+）。

## Proposals

- `PRP-001-abilities-relocation`：abilities 迁移提案（status: implemented）。
