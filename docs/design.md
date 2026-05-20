# ai-profile-manager 设计说明

## 概览

`apm` 是一个 PHP CLI 工具，用于管理 AI profile item（`skill`、`rule`、`agent`）。

## 能力路径约定（包内 ↔ 安装目标）

以下路径为**权威约定**；`Installer` 均须与此一致。文中 `<cwd>` 表示执行 CLI 时的当前工作目录（**业务仓库根**）。`<target>` 为 `cursor` 或 `kiro`。

### Skill

| 场景 | 路径 |
|------|------|
| 包内 / apm-repo 中 SSOT | `abilities/skills/<name>/**`（整棵目录；**不**再按 `<name>/<target>/` 分平台子目录） |
| 安装到用户仓库（`install` / `skill:install`） | `<cwd>/.cursor/skills/<name>/**` 或 `<cwd>/.kiro/skills/<name>/**`（按本次 `-t` 各执行一次镜像） |

安装实现：对包内 `abilities/skills/<name>/` 做递归目录拷贝到目标侧 `skills/<name>/`。

### Agent

| 场景 | 路径 |
|------|------|
| 包内 / apm-repo 中 SSOT | `abilities/agents/<name>.<target>.md`（**扁平**单文件；每个 target 一份源文件） |
| 安装到用户仓库 | `<cwd>/.cursor/agents/<name>.md` 或 `<cwd>/.kiro/agents/<name>.md`（**目标侧**不再带 `.cursor` / `.kiro` 后缀，统一为 `<name>.md`） |

`discoverWorkspaceAbilities` 列举 agent 名：扫描 `abilities/agents/` 下形如 `<name>.cursor.md`、`<name>.kiro.md` 的**文件**（非子目录），解析 `<name>` 后去重排序。

### Rule / Steering

| 场景 | 路径 |
|------|------|
| 包内 / apm-repo 中 SSOT | `abilities/rules/<rule-relative-path>/<rule-name>.<target>.<ext>`，其中 `<rule-relative-path>` 可为多层子目录或为空（文件直接在 `abilities/rules/` 下）；`<ext>` 支持 **cursor**：`.cursor.mdc`、`.cursor.md`；**kiro**：`.kiro.md`、`.kiro.mdc`。安装与 check 在**同一 basename** 多后缀时优先 `.cursor.mdc` / `.kiro.md`。 |
| 安装到用户仓库（cursor） | `<cwd>/.cursor/rules/<rule-relative-path>/<rule-name>.mdc`（输出扩展名统一为 `.mdc`） |
| 安装到用户仓库（kiro） | `<cwd>/.kiro/steering/<rule-relative-path>/<rule-name>.md`（输出扩展名统一为 `.md`） |

规则发现：在 `abilities/rules/` 下**递归**查找 basename 等于 `<rule-name>.<target>.*` 的源文件；`<rule-relative-path>` 由源文件相对 `abilities/rules/` 的父目录推出，安装时保持到 `.cursor/rules/` 或 `.kiro/steering/`。

### Preset（补充）

- Manifest：`abilities/_presets.json`。

## Namespace 分层

### `AiProfileManager\Core`

- `Application`：CLI composition root。
- 负责创建并组装 service，再注册 Symfony Console command。

### `AiProfileManager\Config`

- `AppConfig`：静态 runtime configuration 与默认集合。
- 定义默认 preset、known target；当仓库中不存在 `abilities/_presets.json` 时作为 preset 来源。

### `AiProfileManager\Service`

- `ComposerBaselineResolver`：从 `~/.composer/vendor/composer/installed.json` 解析全局安装的 `no7mks/ai-profile-manager` 路径与版本元数据；支持环境变量 `APM_BASELINE_ROOT` 覆盖（测试或本地模拟）。
- `AbilityDirectoryDiff`：对 baseline 包内目录与工作区目录做递归 diff，生成带 `patch` / 可选 `deleted` 的文件项。
- `PresetRegistry`：读写 `abilities/_presets.json`；文件存在时其为 preset 定义的权威来源。
- `Installer`：从全局包（`packageRoot`）安装到 `<cwd>`；路径映射见上文 **「能力路径约定」**；缺失来源时报 `[fail]` 且 `exit_code` 非 0；末尾合并 `.gitignore` 托管段。
- `DirectoryMirrorService`：递归目录拷贝（`init` 脚手架与 Installer 共用）。
- `GitIgnoreTemplateService`：从 `abilities/gitignore/template.gitignore` 读取 marker block，按安装的 ability/target 渲染并幂等写入用户仓库 `.gitignore` 的托管段。
- `CheckService`：状态评估与结果渲染模型。
- `KnowledgeBaseUpdater`：写入本地 knowledge snapshot。

## Command 面

`src/Command` 下的 command 构成 CLI API：

- Install：`install`、`skill:install`、`rule:install`、`agent:install`
- Uninstall：`preset:uninstall`、`skill:uninstall`、`rule:uninstall`、`agent:uninstall`
- Check：`check`、`skill:check`、`rule:check`、`agent:check`
- Preset：`preset:create`、`preset:add-ability`、`preset:remove-ability`、`preset:delete`
- Update：`update`

typed command 是稳定核心模型；`install`（无参数）承担项目初始化（scaffold + apm skill，并提示 `/apm init` 完成 SSOT ready 初始化：`PROJECT.md` + `docs/state/` 与 `docs/manual/` 下按项目上下文生成的内容文件，不固定文件名）；`*:uninstall` 在删除前先复用 `check` 状态模型做 preflight（命中 `modified` 且未传 `--force` 时阻断）；preset 子命令通过 manifest diff 产生变更。

## 安装期 `.gitignore` 注入（单模板）

`install`、`skill:install`、`rule:install`、`agent:install` 在安装输出末尾执行内置 `.gitignore` 注入步骤，不提供额外 flag 开关。

- 模板来源：`abilities/gitignore/template.gitignore`（apm-repo 内唯一模板文件）。
- marker 语法：
  - `## @apm:block ability=<id> target=<target>`
  - `## @apm:end`
- `<id>` 约定：
  - `skill:<name>` / `rule:<name>` / `agent:<name>`：匹配 typed ability 安装
  - 无前缀（如 `gitflow`）：匹配 preset 名称
- `<target>` 约定：`cursor` / `kiro` / `*`。

安装时根据本次安装请求构建匹配键集合并渲染规则，写入业务仓库根 `.gitignore` 的托管段：

- `# BEGIN apm-managed-gitignore v1`
- `# END apm-managed-gitignore v1`

行为：

- `.gitignore` 不存在则创建。
- 托管段存在则整体替换（幂等）；不存在则追加。
- 非托管段内容保持不变。

## Exit Code 模型

状态模型：

- `unchanged`
- `modified`
- `missing`
- `unknown`

Exit code：

- `0`：全部 unchanged / 无可上报变更
- `2`：存在 modified 或 missing（capture 在有变更时）
- `1`：校验或命令错误（含 baseline 解析失败）
