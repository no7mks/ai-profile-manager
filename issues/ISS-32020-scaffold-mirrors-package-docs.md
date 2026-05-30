# ISS-32020 scaffold mirror 将 apm 自身文档复制到用户项目

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `open` |
| Found In | `v0.7.4` |
| Fixed In | |
| Related Test | `tests/ProjectInitializerTest.php` |

## Description

`apm install`（bootstrap 模式）执行 scaffold 安装时，`ProjectInitializer::init()` 使用 `mirrorDirectory` 将 apm 包自身的 `docs/` 目录整体复制到用户项目。这导致 apm 自身的业务文档（`docs/state/abilities-model.md`、`docs/state/architecture.md`、`docs/state/cli-commands.md`、`docs/manual/usage.md` 等）出现在用户项目中。

用户随后执行 `/apm init` 时，Agent 检测到这些已有文件并 Skip，无法为用户项目生成正确的 state/manual baseline。

## Steps to Reproduce

1. 在一个空项目目录执行 `apm install`
2. 检查 `docs/state/` 和 `docs/manual/` 目录内容
3. 发现包含 apm 工具自身的文档文件（abilities-model、architecture、cli-commands、usage 等）

## Expected Behavior

scaffold 只创建目录骨架和 README 文件：

```
docs/
├── README.md
├── state/       (空或仅 .gitkeep)
├── manual/      (空或仅 .gitkeep)
└── notes/       (空或仅 .gitkeep)
```

## Actual Behavior

scaffold 将 apm 包自身 `docs/` 下所有文件（包括 `state/*.md`、`manual/usage.md`、`design.md`）全部复制到用户项目。

## Analysis

`ProjectInitializer::init()` 第 47 行：

```php
$this->mirror->mirrorDirectory($this->join($this->packageRoot, 'docs'), $this->join($targetDir, 'docs'));
```

`mirrorDirectory` 是递归全量复制，不区分"scaffold 模板文件"和"apm 自身业务文档"。

**修复方向**：

1. 新建 `scaffold/` 模板目录，只放骨架文件（README + .gitkeep），scaffold 安装从该目录复制；或
2. 在 mirror 时通过白名单/排除列表，只复制 README 和 .gitkeep 文件

## History

- `2026-05-30 22:00 +08` `v0.7.4` [发现] 用户首次使用 apm install 后发现 docs 目录包含 apm 自身文档
