# 新增 lark-sheets 和 tga-query Skills

## Goal

将 `docs/notes/` 中的两个 skill draft 正式加入能力库，作为独立能力（不挂靠 preset），同时在 Kiro 和 Cursor 双平台部署。

## Scope

- 创建 `.kiro/skills/lark-sheets/` 和 `.kiro/skills/tga-query/` 目录结构
- 创建 `.cursor/skills/lark-sheets/` 和 `.cursor/skills/tga-query/` 目录结构
- 拆分 note 内容为 SKILL.md（主入口 + 交互流程）+ references/（API 细节、错误码等）
- Refine frontmatter：description 改为激活触发条件
- 在 `abilities.yaml` 的 `skills` 列表中注册两个新 skill

## Assumptions

- 两个 skill 内容以 note draft 为基础，review 后可能微调但不大改
- 不挂靠任何 preset，用户需要时通过 `apm install` 单独安装或手动触发
- Cursor 和 Kiro 使用相同的 SKILL.md 内容（共享同一份文件或内容一致）

## Files

- `.kiro/skills/lark-sheets/SKILL.md`
- `.kiro/skills/lark-sheets/references/api-reference.md`
- `.kiro/skills/tga-query/SKILL.md`
- `.kiro/skills/tga-query/references/api-reference.md`
- `.cursor/skills/lark-sheets/SKILL.md`
- `.cursor/skills/lark-sheets/references/api-reference.md`
- `.cursor/skills/tga-query/SKILL.md`
- `.cursor/skills/tga-query/references/api-reference.md`
- `abilities.yaml`

## Plan

- [x] Step 1: 创建 lark-sheets skill — SKILL.md（含 refined frontmatter + 配置说明 + 交互流程 + 敏感信息规范）
- [x] Step 2: 创建 lark-sheets references/api-reference.md（认证接口 + 电子表格接口 + Wiki 经验 + 追加写入经验 + 错误码）
- [x] Step 3: 创建 tga-query skill — SKILL.md（含 refined frontmatter + Agent 交互流程 + 常用过滤片段 + 区服速查）
- [x] Step 4: 创建 tga-query references/api-reference.md（接口地址 + 请求体结构 + 参数说明 + 响应格式 + 错误码 + 完整示例）
- [x] Step 5: 同步 Cursor 平台 — 将 .kiro/skills/ 下两个 skill 复制到 .cursor/skills/
- [x] Step 6: 注册 abilities.yaml — 在 skills 列表中添加 lark-sheets 和 tga-query 条目（双 target）
- [x] Step 7: state 文档同步（`docs/state/`）— 无需更新，abilities.yaml 即 SSOT
- [x] Step 8: manual 文档同步（`docs/manual/`）— 无需更新，现有 usage.md 已覆盖 skill 安装模式
- [x] Step 9: code-review
- [x] Step 10: final checkpoint（整体验证 + commit）

## Validation

- `abilities.yaml` 格式正确，新 skill 条目完整
- SKILL.md frontmatter 的 description 为激活触发条件而非功能描述
- references 内容完整覆盖原 note 中的 API 细节
- Kiro 和 Cursor 两侧文件一致
- 无遗漏的敏感信息硬编码

## Risks

- SKILL.md 与 references 的拆分边界需要 review 确认，可能需要微调
- TGA 服务依赖内网/VPN 环境，需在 SKILL.md 中明确标注网络前提条件
