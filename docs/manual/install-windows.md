# Windows Install (Scoop)

在 Windows（PowerShell + Scoop Composer）上安装 apm CLI 并完成三阶段首装的操作说明。命令行为 SSOT 见 `docs/state/cli-commands.md`。

---

## 参考环境

以下变量与路径仅作示例，请替换为你的用户名：

| 变量 | 示例值 |
|------|--------|
| `USERPROFILE` | `C:\Users\<user>` |
| `COMPOSER_HOME` | `C:\Users\<user>\scoop\persist\composer\home` |
| `HOME` | （空，正常） |
| `apm` 来源 | Scoop Composer 的 `vendor\bin\apm`（以 `Get-Command` 为准） |

---

## 1. 用 Scoop 安装 PHP 与 Composer

在 **PowerShell** 中：

```powershell
scoop install php composer
```

Scoop 通常会配置 `COMPOSER_HOME`（例如 `~\scoop\persist\composer\home`）。可用下面命令确认：

```powershell
$env:COMPOSER_HOME
```

**不要**把「设置 `HOME=%USERPROFILE%`」当作正式安装步骤；apm 在 Windows 上使用 `USERPROFILE` 解析 user scope，不依赖 `HOME`。

---

## 2. 全局安装 apm CLI

```powershell
composer global require no7mks/ai-profile-manager -W
```

`-W` 允许 Composer 升级依赖，避免 symfony 等旧 lock 阻塞安装。

### Path 与 `Get-Command`

Windows 使用 **`$env:Path`**（不是 bash 的 `$PATH`）。安装后应能直接运行：

```powershell
apm --help
Get-Command apm | Format-List Source
```

`Get-Command apm` 的 **Source** 应指向 Scoop 下的 global `vendor\bin`，例如：

`C:\Users\<user>\scoop\apps\composer\current\vendor\bin\apm.exe`

（以你机器上的 `Get-Command` 输出为准。）

### 多个 `vendor\bin`

`$env:Path` 里可能**同时**存在：

- Scoop Composer：`...\scoop\apps\composer\current\vendor\bin`
- 安装器默认：`%APPDATA%\Roaming\Composer\vendor\bin`

**以 `Get-Command apm` 的 Source 为准**，不要假设 Roaming 路径优先。`apm update` 须使用 global 安装的那份 `apm`。

---

## 3. 三阶段首装

### 阶段 1：全局能力（任意目录，通常只需一次）

```powershell
apm global-setup
```

将 Global Setup List 写入 user scope，例如：

- `%USERPROFILE%\.cursor\`（Cursor）
- `%USERPROFILE%\.kiro\`（Kiro，若包含 `-t kiro`）

### 阶段 2：项目脚手架（业务仓库根目录）

```powershell
cd C:\path\to\your-repo
apm bootstrap
```

### 阶段 3：项目上下文与能力

在 Cursor Agent 对话中执行 `/apm init`（**必须先**完成 bootstrap）。Agent 将生成 `PROJECT.md` 与 `docs/state`、`docs/manual` 基线，并按仓库特征安装 project ability。

---

## 排障

| 现象 | 处理 |
|------|------|
| `apm: command not found` | 检查 `$env:Path`；运行 `Get-Command apm`；确认 Scoop `vendor\bin` 在 Path 中 |
| `HOME` 为空 | 正常，无需设置 |
| `apm update` / `check` 显示 `unknown` 或 no baseline | 确认 `COMPOSER_HOME` 或 `%APPDATA%\Composer` 下存在 `vendor\composer\installed.json`，且其中包含 `no7mks/ai-profile-manager` |
| user scope 路径不对 | 确认 `USERPROFILE` 已设；apm **不会**用 Git Bash 的 MSYS `HOME` 覆盖 `USERPROFILE` |

更多日常命令见 `docs/manual/usage.md`。
