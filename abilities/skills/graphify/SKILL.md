---
name: graphify
description: "Turn a folder of files into a queryable knowledge graph with auditable relationships and report artifacts."
---

# /graphify

Build and query a knowledge graph from code/docs/media inputs.

## Usage

```bash
/graphify
/graphify <path>
/graphify <path> --update
/graphify <path> --cluster-only
/graphify query "<question>"
/graphify path "NodeA" "NodeB"
/graphify explain "NodeName"
```

## Required behavior

- Default path is current directory when omitted.
- Keep relationship honesty tags: `EXTRACTED`, `INFERRED`, `AMBIGUOUS`.
- Produce outputs under `graphify-out/`: `graph.json`, `GRAPH_REPORT.md`, and `graph.html` (unless disabled).
- For very large corpora, warn users before expensive runs and ask to narrow scope.
