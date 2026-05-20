# Architecture

apm 系统架构与模块边界。

---

## 模块结构

| 模块 | 职责 |
|------|------|
| CLI Commands | Symfony Console 命令注册与参数解析 |
| AbilityRegistry | 解析 abilities.yaml，按 section 返回结构化 AbilityEntry 列表 |
| Installer | 委托 AbilityRegistry 获取 ability 列表，复制到目标项目 |
| CheckService | 对比已安装 ability 与源文件的 diff 状态 |
| GitignoreManager | 操作 `.gitignore` 中的 `@apm:block` marker |

---

## 数据流

```
abilities.yaml → Installer → 目标项目 (.cursor/ | .kiro/)
                → CheckService → diff 状态报告
```

---

## 目标平台

| 平台 | 配置根 | rules 路径 | agents 路径 | skills 路径 |
|------|--------|-----------|-------------|-------------|
| Cursor | `.cursor/` | `.cursor/rules/` | `.cursor/agents/` | `.cursor/skills/` |
| Kiro | `.kiro/` | `.kiro/steering/` | `.kiro/agents/` | `.kiro/skills/` |
