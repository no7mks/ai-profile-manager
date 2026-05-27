---
inclusion: always
description: Kiro 平台专属规则，始终生效
---

# Kiro Agent Rules

## 强制读取

- Agent 首次接触项目时，必须先读取根目录下的 `PROJECT.md` 获取项目的技术栈、构建命令、运行入口、敏感文件清单等项目特定信息。

## Intent Mapping

Agent 开始工作时，先识别用户本轮意图并匹配下表场景（可多个）。匹配后先输出一句确认：

> 当前确认用户目的是：<匹配到的意图描述>

然后执行对应动作，再进入正式工作。

| 意图场景 | 动作 |
|---------|------|
| 创建 Spec 计划 | 激活 `spec-planning` SKILL |
| 执行任务（Execute Task） | 激活 `spec-execution` SKILL |

若意图不匹配任何场景，则认为用户意图是"自由对话"，无需额外准备。

## Kiro 专属配置

- `<spec-dir>` = `.kiro/specs`

## 项目目录结构

| 目录 | 定位 | 详细规则 |
|------|------|---------|
| `docs/` | 活跃文档（state/manual/notes/proposals） | `docs/README.md` |
| `changes/` | 归档（已完成文档按版本组织） | `changes/README.md` |
| `issues/` | 缺陷管理 | `issues/README.md` |
| `<spec-dir>/` | 进行中的 spec | — |
| `.kiro/steering/` | Kiro agent steering 规则 | — |
| `.kiro/skills/` | Kiro agent skills | — |


