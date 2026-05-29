# Abilities Model

abilities.yaml 完整格式定义与 Preset 引用模型。

---

## abilities.yaml 格式

### 顶层结构

```yaml
version: "1"

rules:
  - ...
agents:
  - ...
skills:
  - ...
hooks:
  - ...
gitignore:
  - ...
presets:
  - ...
```

- `version`: 必填，字符串，当前固定为 `"1"`
- 已知 section: `rules`、`agents`、`skills`、`hooks`、`gitignore`、`presets`、`prompts`
- 未知 section: 解析时忽略，不报错

---

## Ability Entry 格式（rules / agents / skills / hooks）

每个 ability entry 为一个 mapping，包含以下字段：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| path | string | 是 | ability 标识名（用于源文件定位与安装路径解析） |
| description | string | 是 | 人类可读描述 |
| targets | mapping | 是 | 目标平台 → 安装路径映射，不可为空 |

### targets 格式

```yaml
targets:
  cursor: .cursor/rules/my-rule.mdc
  kiro: .kiro/steering/my-rule.md
```

- key: 平台名（cursor / kiro）
- value: 相对于 workspace root 的安装目标路径

### 各类型示例

**rule**:
```yaml
rules:
  - path: doc:writing-conventions
    description: 文档书写规范
    targets:
      cursor: .cursor/rules/doc/writing-conventions.mdc
      kiro: .kiro/steering/doc/writing-conventions.md
```

**agent**:
```yaml
agents:
  - path: code-reviewer
    description: 基于 diff 的 code review agent
    targets:
      cursor: .cursor/agents/code-reviewer.md
      kiro: .kiro/agents/code-reviewer.md
```

**skill**:
```yaml
skills:
  - path: apm
    description: apm CLI 的 Agent 执行手册
    targets:
      cursor: .cursor/skills/apm/
      kiro: .kiro/skills/apm/
```

**hook**:
```yaml
hooks:
  - path: check-write-length
    description: 写入长度检查 hook
    targets:
      kiro: .kiro/hooks/check-write-length.kiro.hook
      cursor: .cursor/hooks/check-write-length/
```

> **注意**: hook 的源路径解析方式与 skill/rule/agent 不同。skill/rule/agent 使用 Source-is-Target 模式（源路径 = `<packageRoot>/<targets[target]>`），而 hook 使用硬编码路径：
> - Kiro: `<packageRoot>/hooks/<name>.kiro.hook`
> - Cursor: `<packageRoot>/hooks/<name>/`
>
> targets 字段仅用于定义安装**目标**路径，不影响源文件定位。

---

## gitignore section

gitignore section 的条目结构不同于 ability entry，直接透传为 array：

```yaml
gitignore:
  - marker: php
    description: PHP 相关忽略规则
```

| 字段 | 类型 | 说明 |
|------|------|------|
| marker | string | gitignore 模板中的 ability key |
| description | string | 人类可读描述 |

---

## presets section

presets section 定义 ability 组合（abilities.yaml 中的声明式定义）：

```yaml
presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - rule:git:branch-overview
      - rule:git:git-conventions
      - skill:gitflow
```

| 字段 | 类型 | 说明 |
|------|------|------|
| name | string | preset 名称 |
| description | string | 人类可读描述 |
| includes | list | ability 引用列表，格式为 `type:name` |


### Preset 引用格式

includes 中的每个条目使用 `type:name` 语法：

- `skill:<name>` — 引用 skill
- `rule:<name>` — 引用 rule
- `agent:<name>` — 引用 agent
- `hook:<name>` — 引用 hook

示例: `rule:git:branch-overview` 表示类型为 rule，名称为 `git:branch-overview`。

---

## Preset 存储

abilities.yaml 的 `presets` section 即为 preset 定义的**唯一存储**（SSOT）。

- PresetRegistry 直接读写 abilities.yaml 中的 presets section
- 不再使用独立的 `_presets.json` 文件
- `preset:create`、`preset:add-ability`、`preset:remove-ability`、`preset:delete` 命令直接操作 abilities.yaml

---

## 解析行为

### 验证规则

- 每个 ability entry 必须包含 path（string）、description（string）、targets（非空 mapping）
- 缺失字段时记录错误但继续解析其他条目
- 所有错误收集完毕后一次性通过 AbilityRegistryException::validationErrors() 抛出

### 错误格式

```
rules[doc:writing-conventions]: missing required field(s): targets
agents[#2]: entry must be a mapping
```

### 异常类型

| 异常 | 触发条件 |
|------|---------|
| AbilityRegistryException::fileNotFound | abilities.yaml 文件不存在或不可读 |
| AbilityRegistryException::invalidYaml | YAML 解析失败或根元素非 mapping |
| AbilityRegistryException::validationErrors | 存在一个或多个字段验证错误 |
