# Abilities Model

ability 语义与 `abilities.yaml` 格式；界定何者不是 ability。

---

## Ability 定义

**Ability**：在 registry 登记、**可安装**（`add`/`install`/preset/`bootstrap`）且 **可卸载**（`remove`/`uninstall` 或 project 上 `cleanup`）的条目（skill / rule / agent / hook / gitignore / prompt）。`show`/`check`/`update` 只操作 ability；**preset 名不是 ability**。

**不是 ability**：bootstrap/scaffold（`apm bootstrap` → `ProjectInitializer`，**不进 yaml**，cleanup 不卸）；`PROJECT.md` 与 `docs/state|manual` 内容由 init Agent 写。Deploy scope 根解析见 `docs/state/deploy-scope.md`。

---

## abilities.yaml 格式

### 顶层结构

```yaml
version: "1"
bootstrap:
  includes:
    - skill:apm
    - rule:git:git-conventions
rules:
  - ...
# agents, skills, hooks, gitignore, presets, prompts
```

- `version`: 必填 `"1"`
- 已知 section: `bootstrap`、`rules`、`agents`、`skills`、`hooks`、`gitignore`、`presets`、`prompts`
- 未知 section: 解析时忽略
- 所有 entry 均为 project-only（无 `scopes` 字段）

### bootstrap section

顶层 `bootstrap.includes` 为 **Bootstrap Include List**（`type:path` 格式），供 `apm bootstrap` 安装阶段使用。

- `AbilityRegistry::bootstrapIncludes()` 解析 `bootstrap.includes`，返回 `list<array{type: string, path: string}>`
- `bootstrap` section 不存在或 `includes` 为空时返回空数组
- `AbilityRegistry::validateBootstrapIncludes()` 校验所有引用在 registry 中存在，否则抛 `AbilityRegistryException::invalidBootstrapReference()`

---

## Ability Entry 格式（rules / agents / skills / hooks）

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| path | string | 是 | ability 标识名 |
| description | string | 是 | 人类可读描述 |
| targets | mapping | 是 | 平台 → 安装路径，非空 |

### targets

```yaml
targets:
  cursor: .cursor/rules/my-rule.mdc
  kiro: .kiro/steering/my-rule.md
```

key 为平台（cursor / kiro），value 为相对 workspace root 的安装路径。

### hook 源路径

hook 源路径与 skill/rule/agent 不同：Kiro `<packageRoot>/hooks/<name>.kiro.hook`；Cursor `<packageRoot>/hooks/<name>/`。`targets` 仅定义安装目标。

---

## gitignore section

```yaml
gitignore:
  - marker: php
    description: PHP 相关忽略规则
```

| 字段 | 类型 | 说明 |
|------|------|------|
| marker | string | gitignore 模板 key |
| description | string | 人类可读描述 |

gitignore / prompts 恒为 project-only。

---

## presets section

```yaml
presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - rule:git:git-conventions
      - skill:gitflow
```

| 字段 | 类型 | 说明 |
|------|------|------|
| name | string | preset 名称 |
| description | string | 人类可读描述 |
| includes | list | `type:name` 引用（skill / rule / agent / hook） |

### 已移除 preset `default`

PRP-002 起无 `default` preset。首装：`apm bootstrap` → 进仓 init/bootstrap → `add preset <name>`。其余 preset（`gitflow`、`spec-core`、`graphify`、`php` 等）保留。

### Preset 存储

`presets` section 为 preset SSOT；PresetRegistry 直接读写 abilities.yaml（无 `_presets.json`）。

---

## 解析行为

- 每个 ability entry 须含 path、description、非空 targets；缺字段收集后 `AbilityRegistryException::validationErrors()`。
- `getEntry(type, path)` 按 section 查找 conventional ability。

| 异常 | 触发条件 |
|------|---------|
| fileNotFound | yaml 不存在或不可读 |
| invalidYaml | 解析失败或根非 mapping |
| validationErrors | 字段校验失败 |
| invalidBootstrapReference | `bootstrap.includes` 引用的 ability 不存在 |
| legacyScopesField | entry 含遗留 `scopes` 字段（fail-fast） |
| legacyGlobalSetupKey | 含遗留 `global-setup` 顶层 key（fail-fast） |
