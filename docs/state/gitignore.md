# Gitignore

`.gitignore` managed section 的格式与操作规则。

---

## Managed Section 格式

apm 在 `.gitignore` 中维护一个 managed section，由 BEGIN/END marker 包裹：

```gitignore
# BEGIN apm-managed-gitignore v1
<渲染的忽略规则>
# END apm-managed-gitignore v1
```

- BEGIN marker: `# BEGIN apm-managed-gitignore v1`
- END marker: `# END apm-managed-gitignore v1`
- marker 之间的内容完全由 apm 管理，每次安装时重新渲染

---

## 模板文件格式

模板文件位于 `<packageRoot>/.gitignore`，使用 `@apm:block` 标记定义条件渲染块：

```
## @apm:block ability=<key> target=<target>
<gitignore 规则行>
## @apm:end
```

### block 属性

| 属性 | 说明 |
|------|------|
| ability | ability key，匹配安装时传入的 abilityKeys 列表 |
| target | 目标平台（cursor / kiro / `*`）；`*` 表示匹配所有 target |

### 匹配规则

一个 block 被渲染的条件：
1. `ability` 值存在于当前安装的 abilityKeys 列表中
2. `target` 值为 `*`，或存在于当前安装的 targets 列表中

### 示例

```
## @apm:block ability=skill:graphify target=*
.graphify/
## @apm:end

## @apm:block ability=gitflow target=cursor
.cursor/tmp/
## @apm:end
```

---

## 操作规则

### 插入（首次安装）

当 `.gitignore` 中不存在 managed section 时：
1. 如果文件为空或不存在 → 直接写入 managed section
2. 如果文件有内容 → 在文件末尾追加空行 + managed section

### 更新（重复安装）

当 `.gitignore` 中已存在 managed section 时：
- 使用正则匹配 BEGIN...END 区域
- 整体替换为新渲染的 managed section
- 仅替换第一个匹配（理论上只有一个）

### 渲染逻辑

1. 遍历模板文件中所有 `@apm:block`
2. 对每个 block 检查 ability 和 target 是否匹配
3. 匹配的 block 中非空行收集为 patterns
4. 多个匹配 block 之间用空行分隔
5. 最终拼接为 managed section 内容

### 无匹配时

- 模板中无匹配 block → renderManagedBlock() 返回空字符串
- 空字符串时 mergeManagedSection() 不执行任何操作
- 安装流程输出 `[skip] No matched .gitignore template blocks.`

---

## 用户手动内容保留

- managed section 之外的所有内容由用户管理，apm 不会修改
- 更新操作仅替换 BEGIN...END 之间的内容
- 用户在 managed section 内手动添加的内容会在下次安装时被覆盖

---

## 错误处理

| 条件 | 行为 |
|------|------|
| 模板文件不存在 | renderManagedBlock() 返回空字符串 |
| 模板文件读取失败 | 抛出 RuntimeException |
| 模板中存在未闭合的 @apm:block | 抛出 RuntimeException |
