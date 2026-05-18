---
name: graphify
description: "Graphify unified entry: what it is, how to start, and task-based routing for build/query/integrations."
---

# /graphify

Turn any folder into a queryable knowledge graph with:
- auditable confidence tags (`EXTRACTED`, `INFERRED`, `AMBIGUOUS`)
- structural output artifacts (`graph.json`, `GRAPH_REPORT.md`)
- optional visualization/integration output artifacts (HTML, SVG, GraphML, Neo4j, MCP)

## What it is for

Graphify is useful when you need a persistent map of relationships across code/docs/papers/images instead of one-off chat answers.

Use it for:
- a codebase you are new to
- a mixed research corpus (papers + notes + screenshots + links)
- a long-lived `/raw` workflow where retrieval should improve over time

## Quick start (minimal path)

Use this when users ask "run graphify here" and do not need advanced options:

```bash
/graphify .
```

Then surface:
- where output artifacts are (`graphify-out/`)
- top insights from report (God Nodes, Surprising Connections, Suggested Questions)
- one suggested follow-up query

## Task router (equal-weight entry)

Pick by user intent. Keep all routes at the same level.

### Build or refresh graph

- **Initial build**: `/graphify <path>`  
  Details: [Pipeline steps](references/pipeline-steps.md)
- **Incremental update**: `/graphify <path> --update`  
  Details: [Incremental update](references/incremental-and-modes.md#for---update-incremental-re-extraction)
- **Re-cluster only**: `/graphify <path> --cluster-only`  
  Details: [Cluster-only mode](references/incremental-and-modes.md#for---cluster-only)
- **Deep extraction**: `/graphify <path> --mode deep`  
  Details: [Pipeline steps](references/pipeline-steps.md#step-3---extract-entities-and-relationships)

### Query the graph

- **Ask relationship question**: `/graphify query "<question>"`  
  Details: [Query command](references/query-commands.md#for-graphify-query)
- **Trace a path**: `/graphify path "A" "B"`  
  Details: [Path command](references/query-commands.md#for-graphify-path)
- **Explain one concept/node**: `/graphify explain "NodeName"`  
  Details: [Explain command](references/query-commands.md#for-graphify-explain)

### Ingest and integrations

- **Add URL to corpus**: `/graphify add <url>`  
  Details: [Add integration](references/integrations.md#for-graphify-add)
- **Watch filesystem changes**: `/graphify <path> --watch`  
  Details: [Watch mode](references/integrations.md#for---watch)
- **Git hook automation**: `graphify hook install`  
  Details: [Git hook](references/integrations.md#for-git-commit-hook)
- **CLAUDE.md always-on integration**: `graphify claude install`  
  Details: [CLAUDE.md integration](references/integrations.md#for-native-claudemd-integration)

### Exports and external systems

- **HTML report graph**: default on build (skip with `--no-viz`)
- **SVG**: `/graphify <path> --svg`
- **GraphML**: `/graphify <path> --graphml`
- **Neo4j file export**: `/graphify <path> --neo4j`
- **Neo4j direct push**: `/graphify <path> --neo4j-push bolt://localhost:7687`
- **MCP server**: `/graphify <path> --mcp`  
  Details: [Pipeline optional exports](references/pipeline-steps.md#step-7---optional-exports)

## Shared execution principles (all commands)

Apply these principles regardless of `build/query/path/explain/add`:
- If no path is provided for path-based commands, default to `.`
- Never fabricate edges or facts not supported by graph/source
- Always surface uncertainty using confidence tags
- Prefer concise results first, then offer a deeper follow-up
- If required graph artifacts are missing, tell user what prerequisite command to run

## Recommended interaction rhythm

1. Build or update graph
2. Read report highlights
3. Navigate with `query` / `path` / `explain`
4. Add integrations only when needed (`watch`, hooks, Neo4j, MCP)

## Full execution references

Use these as the authoritative contracts for step-by-step execution details:
- [Pipeline steps (build/update/export flow)](references/pipeline-steps.md)
- [Incremental and cluster-only modes](references/incremental-and-modes.md)
- [Query commands (`query`, `path`, `explain`)](references/query-commands.md)
- [Integrations (`add`, `--watch`, hooks, CLAUDE.md)](references/integrations.md)

## Honesty Rules

- Never invent an edge. If unsure, use AMBIGUOUS.
- Never skip corpus-size warnings on large datasets.
- Always show token cost when available in outputs.
- Never hide cohesion scores behind symbols; show raw numbers.
- Never run HTML viz on a graph with more than 5,000 nodes without warning.
