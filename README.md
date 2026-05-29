# AI Profile Manager (`apm`)

`apm` 是一个用于管理 AI profile 资源（`skill`、`rule`、`agent`、`hook`）的 PHP CLI。

## Quick Start

### Step 1 安装 apm 环境

- 全局安装（推荐）：

```bash
composer global require no7mks/ai-profile-manager
apm --help # 如显示帮助则表示安装成功了
```

- 项目内安装：

```bash
composer install
php bin/apm --help # 如显示帮助则表示安装成功了
```

### Step 2 初始化仓库并导入能力

在目标项目根目录初始化（生成 scaffold 并安装 `default` preset）：

```bash
apm install              # 默认同时写入 cursor 和 kiro
apm install -t kiro      # 只写入指定平台
```

再根据项目特征，导入其他 preset：

```bash
apm install gitflow -t cursor
apm install spec-core -t kiro
apm install php
```

可用 preset 和 ability 列表见 `apm show`。

### Step 3 初始化项目上下文

在 Agent 对话中激活 `apm` skill，生成项目特有的基线文件：

```
/apm init
```
