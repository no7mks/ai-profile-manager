---
inclusion: always
description: Kiro 平台专属规则，始终生效
---

# Kiro Agent Rules

## Agent 启动后必须要做的事：

1. 载入项目基础文件 `/PROJECT.md`。加载完毕后输出：

> 这是一个 <来自 PROJECT.md 的项目概述> 项目

2. 进行意图识别：识别用户本轮意图并匹配下表场景（可多个）。匹配后先输出一句确认：

> 当前确认用户目的是：<匹配到的意图描述>

然后执行对应动作，再进入正式工作。

| 意图场景 | 动作 |
|---------|------|
| 创建 Spec 计划 | 激活 `spec-planning` SKILL |
| 执行任务（Execute Task） | 激活 `spec-execution` SKILL |

若意图不匹配任何场景，则认为用户意图是"自由对话"，无需额外准备。

## 项目目录结构

| 目录 | 定位 | 详细规则 |
|------|------|---------|
| `/README.md` | 项目对外介绍（面向用户的功能概述与快速上手） | — |
| `/CHANGELOG.md` | 面向用户的版本摘要 | — |
| `/docs/` | 活跃文档（state/manual/notes/proposals） | `docs/README.md` |
| `/changes/` | 归档（已完成文档按版本组织） | `changes/README.md` |
| `/issues/` | 缺陷管理 | `issues/README.md` |
| `/.kiro/specs/` | 即 `<spec-dir>`，记录进行中的 specs | — |
| `/.kiro/steering/` | Kiro agent steering 规则 | — |
| `/.kiro/skills/` | Kiro agent skills | — |
| `/.kiro/agents/` | Kiro custom agents | — |
| `/.kiro/hooks/` | Kiro agent hooks | — |


