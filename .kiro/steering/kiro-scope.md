---
inclusion: always
description: Kiro 平台专属规则，始终生效
---

# Kiro Scope Rules

## `<spec-dir>` 取值

- `<spec-dir>` = `.kiro/specs`

## 项目信息来源

- Agent 首次接触项目时，必须先读取根目录下的 `PROJECT.md` 获取项目的技术栈、构建命令、运行入口、敏感文件清单等项目特定信息。
- 读取 `PROJECT.md` 时优先使用各项的 `confirmed` 值；若为 `UNKNOWN`，先向用户确认再继续执行。

