# ai-profile-manager 设计说明

## 概览

`apm` 是一个 PHP CLI 工具，用于管理 AI profile item（`skill`、`rule`、`agent`）。

## 能力路径约定（Source-is-Target）

以下路径为**权威约定**；`Installer` 均须与此一致。文中 `<cwd>` 表示执行 CLI 时的当前工作目录（**业务仓库根**）。`<packageRoot>` 为 apm 包的安装根目录。

### 核心原则

**Source-is-Target**：能力文件在 `<packageRoot>` 中的存放路径即为安装到目标项目时的路径，无需路径转换。

- abilities.yaml 的 `targets` 字段声明了每个 ability 在 `<packageRoot>` 中的真实路径
- 安装逻辑简化为：parse abilities.yaml → read `<packageRoot>/<targets[target]>` → copy to `<cwd>/<targets[target]>`

### Skill

| 场景 | 路径 |
|------|------|
| 包内源文件 | `<packageRoot>/.cursor/skills/<name>/**` 或 `<packageRoot>/.kiro/skills/<name>/**` |
| 安装到用户仓库 | `<cwd>/.cursor/skills/<name>/**` 或 `<cwd>/.kiro/skills/<name>/**` |

### Agent

| 场景 | 路径 |
|------|------|
| 包内源文件 | `<packageRoot>/.cursor/agents/<name>.md` 或 `<packageRoot>/.kiro/agents/<name>.md` |
| 安装到用户仓库 | `<cwd>/.cursor/agents/<name>.md` 或 `<cwd>/.kiro/agents/<name>.md` |

### Rule / Steering

| 场景 | 路径 |
|------|------|
| 包内源文件（cursor） | `<packageRoot>/.cursor/rules/<rule-relative-path>/<rule-name>.mdc` |
| 包内源文件（kiro） | `<packageRoot>/.kiro/steering/<rule-relative-path>/<rule-name>.md` |
| 安装到用户仓库（cursor） | `<cwd>/.cursor/rules/<rule-relative-path>/<rule-name>.mdc` |
| 安装到用户仓库（kiro） | `<cwd>/.kiro/steering/<rule-relative-path>/<rule-name>.md` |

### Hook

| 场景 | 路径 |
|------|------|
| 包内源文件（kiro） | `<packageRoot>/.kiro/hooks/<name>.kiro.hook` |
| 包内源文件（cursor） | `<packageRoot>/.cursor/hooks/<name>/` |
| 安装到用户仓库（kiro） | `<cwd>/.kiro/hooks/<name>.kiro.hook` |
| 安装到用户仓库（cursor） | `<cwd>/.cursor/hooks/<name>/` |

### Preset

- 定义存储：abilities.yaml 的 `presets` section（唯一 SSOT）

## Namespace 分层

### `AiProfileManager\Core`

- `Application`：CLI composition root。
- 负责创建并组装 service，再注册 Symfony Console command。

### `AiProfileManager\Config`

- `AppConfig`：静态 runtime configuration 与默认集合。
- 定义默认 preset、known target。

### `AiProfileManager\Service`

- `ComposerBaselineResolver`：从 `~/.composer/vendor/composer/installed.json` 解析全局安装的 `no7mks/ai-profile-manager` 路径与版本元数据；支持环境变量 `APM_BASELINE_ROOT` 覆盖（测试或本地模拟）。
- `AbilityDirectoryDiff`：对 baseline 包内目录与工作区目录做递归 diff，生成带 `patch` / 可选 `deleted` 的文件项。
- `PresetRegistry`：读写 abilities.yaml 的 presets section；presets section 为 preset 定义的唯一权威来源。
- `Installer`：从全局包（`packageRoot`）安装到 `<cwd>`；路径映射见上文 **「能力路径约定」**；缺失来源时报 `[fail]` 且 `exit_code` 非 0；末尾合并 `.gitignore` 托管段。
- `DirectoryMirrorService`：递归目录拷贝（`init` 脚手架与 Installer 共用）。
- `GitIgnoreTemplateService`：从 `<packageRoot>/.gitignore` 读取 marker block，按安装的 ability/target 渲染并幂等写入用户仓库 `.gitignore` 的托管段。
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

- 模板来源：`<packageRoot>/.gitignore`（apm 包根目录的 .gitignore 文件）。
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
