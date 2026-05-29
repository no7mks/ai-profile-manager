---
name: graphify
description: "当用户提到 graphify、知识图谱、依赖分析、模块耦合、架构可视化，或要求构建/查询 graph 时激活。"
---

# /graphify

将任意文件夹（代码、文档、论文、图片、视频）转化为可查询的知识图谱，输出交互式 HTML、GraphRAG-ready JSON 和 GRAPH_REPORT.md。

## 用法速查

```
/graphify init                                        # 等同于 /graphify .（首次构建）
/graphify update                                      # 等同于 /graphify . --update（增量更新）
/graphify                                             # 对当前目录执行完整 pipeline
/graphify <path>                                      # 对指定路径执行完整 pipeline
/graphify <path> --mode deep                          # 深度提取，更多 INFERRED 边
/graphify <path> --update                             # 增量更新——仅重新提取变更文件
/graphify <path> --cluster-only                       # 跳过提取，仅重新聚类
/graphify <path> --no-viz                             # 跳过可视化，只生成报告 + JSON
/graphify <path> --svg                                # 额外导出 graph.svg
/graphify <path> --graphml                            # 导出 graph.graphml（Gephi/yEd）
/graphify <path> --neo4j                              # 生成 cypher.txt 供 Neo4j 导入
/graphify <path> --neo4j-push bolt://localhost:7687   # 直接推送到 Neo4j
/graphify <path> --mcp                                # 启动 MCP stdio server
/graphify <path> --watch                              # 监听文件变更，自动重建（无需 LLM）
/graphify add <url>                                   # 抓取 URL 加入素材并更新图谱
/graphify query "<question>"                          # BFS 遍历——广度上下文
/graphify query "<question>" --dfs                    # DFS——追踪特定路径
/graphify query "<question>" --budget 1500            # 限制输出 token 数
/graphify path "A" "B"                                # 两个概念间的最短路径
/graphify explain "X"                                 # 单节点的邻居与上下文解释
```

## 调用规则

- 若用户输入 `/graphify --help` 或 `-h`，直接打印上方用法速查并停止，不执行任何命令
- 若未提供路径，默认使用 `.`（当前目录），不要询问用户
- `/graphify init` 等同于 `/graphify .`（首次构建的语义别名）
- `/graphify update` 等同于 `/graphify . --update`（增量更新的语义别名）

## 任务路由

根据用户意图选择对应流程，所有路由平级：

### 构建图谱

- **完整构建**：`/graphify <path>` → [build.md](references/build.md)
- **深度提取**：`/graphify <path> --mode deep` → [build.md](references/build.md)（设置 DEEP_MODE）

### 增量更新

- **增量更新**：`/graphify <path> --update` → [update.md](references/update.md)

### 查询图谱

- **关系问题**：`/graphify query "<question>"` → [query.md](references/query.md)
- **追踪路径**：`/graphify path "A" "B"` → [query.md](references/query.md#for-graphify-path)
- **解释节点**：`/graphify explain "X"` → [query.md](references/query.md#for-graphify-explain)

### 导出与重组

- **仅重新聚类**：`/graphify <path> --cluster-only` → [export.md](references/export.md#for---cluster-only)
- **Neo4j / SVG / GraphML / MCP**：→ [export.md](references/export.md)

### 添加内容与自动化

- **添加 URL**：`/graphify add <url>` → [ingest.md](references/ingest.md#for-graphify-add)
- **文件监听**：`/graphify <path> --watch` → [ingest.md](references/ingest.md#for---watch)
- **Git hook**：`graphify hook install` → [ingest.md](references/ingest.md#for-git-commit-hook)

## 通用执行原则

- 若未提供路径，默认 `.`
- 绝不捏造边或事实——不确定时使用 AMBIGUOUS
- 始终使用 confidence tag（`EXTRACTED`/`INFERRED`/`AMBIGUOUS`）
- 优先给出简洁结果，再提供深入跟进
- 若所需图谱文件不存在，告知用户先运行哪个前置命令
- 超过 5000 节点的图谱生成 HTML 前必须警告用户
- 始终在报告中展示 token 消耗
