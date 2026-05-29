# Build — 完整构建流程

Steps 1–9，用于 `/graphify <path>` 首次构建。

## 执行前规则

- 若未提供路径，使用 `.`
- 在 `graphify-out/.graphify_python` 中保持唯一的解释器路径
- 尽早检查停止条件（`total_files == 0`、空图、超大数据集决策门）
- 提取必须诚实：显式 confidence tag，不捏造边

## 流程结束后

- 总结输出文件和 token/cost 信息
- 仅粘贴报告的关键章节（God Nodes、Surprising Connections、Suggested Questions）
- 提供一个高价值的后续探索问题

---

## Step 1 - 确保 graphify 已安装

```bash
PYTHON=""
GRAPHIFY_BIN=$(which graphify 2>/dev/null)
if [ -z "$PYTHON" ] && command -v uv >/dev/null 2>&1; then
    _UV_PY=$(uv tool run graphifyy python -c "import sys; print(sys.executable)" 2>/dev/null)
    if [ -n "$_UV_PY" ]; then PYTHON="$_UV_PY"; fi
fi
if [ -z "$PYTHON" ] && [ -n "$GRAPHIFY_BIN" ]; then
    _SHEBANG=$(head -1 "$GRAPHIFY_BIN" | tr -d '#!')
    case "$_SHEBANG" in
        *[!a-zA-Z0-9/_.-]*) ;;
        *) "$_SHEBANG" -c "import graphify" 2>/dev/null && PYTHON="$_SHEBANG" ;;
    esac
fi
if [ -z "$PYTHON" ]; then PYTHON="python3"; fi
if ! "$PYTHON" -c "import graphify" 2>/dev/null; then
    if command -v uv >/dev/null 2>&1; then
        uv tool install --upgrade graphifyy -q 2>&1 | tail -3
        _UV_PY=$(uv tool run graphifyy python -c "import sys; print(sys.executable)" 2>/dev/null)
        if [ -n "$_UV_PY" ]; then PYTHON="$_UV_PY"; fi
    else
        "$PYTHON" -m pip install graphifyy -q 2>/dev/null \
          || "$PYTHON" -m pip install graphifyy -q --break-system-packages 2>&1 | tail -3
    fi
fi
mkdir -p graphify-out
"$PYTHON" -c "import sys; open('graphify-out/.graphify_python', 'w', encoding='utf-8').write(sys.executable)"
```

若 import 成功，不打印任何内容，直接进入 Step 2。

**后续所有 bash 块中，用 `$(cat graphify-out/.graphify_python)` 替代 `python3`。**

---

## Step 2 - 检测文件

```bash
$(cat graphify-out/.graphify_python) -c "
import json
from graphify.detect import detect
from pathlib import Path
result = detect(Path('INPUT_PATH'))
print(json.dumps(result))
" > .graphify_detect.json
```

将 INPUT_PATH 替换为实际路径。静默读取后展示简洁摘要：

```
Corpus: X files · ~Y words
  code:     N files (.py .ts .go ...)
  docs:     N files (.md .txt ...)
  papers:   N files (.pdf ...)
  images:   N files
  video:    N files (.mp4 .mp3 ...)
```

省略数量为 0 的类别。

决策逻辑：
- `total_files` 为 0：停止
- `skipped_sensitive` 非空：提及跳过的文件数量，不展示文件名
- `total_words` > 2,000,000 或 `total_files` > 200：显示警告和前 5 个子目录，询问用户选择
- 否则：若有 video 文件进入 Step 2.5，否则直接进入 Step 3

---

## Step 2.5 - 转录视频/音频（仅当检测到 video 文件时）

若 detect 返回零个 video 文件，跳过此步。

从 detect 输出中读取 god node 标签，自行编写一句领域提示作为 Whisper 的 initial prompt。若素材中仅有视频文件无其他内容，使用通用 fallback：`"Use proper punctuation and paragraph breaks."`

```bash
$(cat graphify-out/.graphify_python) -c "
import json, os
from pathlib import Path
from graphify.transcribe import transcribe_all

detect = json.loads(Path('.graphify_detect.json').read_text())
video_files = detect.get('files', {}).get('video', [])
prompt = os.environ.get('GRAPHIFY_WHISPER_PROMPT', 'Use proper punctuation and paragraph breaks.')

transcript_paths = transcribe_all(video_files, initial_prompt=prompt)
print(json.dumps(transcript_paths))
" > graphify-out/.graphify_transcripts.json
```

转录后将 transcript 路径加入 docs 列表，在 Step 3B 中作为文档处理。

---

## Step 3 - 提取实体和关系

注意是否传入了 `--mode deep`，若是则在 Step B2 中设置 `DEEP_MODE=true`。

**Part A 和 Part B 并行执行。**

### Part A - 代码文件的结构提取（AST）

```bash
$(cat graphify-out/.graphify_python) -c "
import sys, json
from graphify.extract import collect_files, extract
from pathlib import Path

code_files = []
detect = json.loads(Path('.graphify_detect.json').read_text())
for f in detect.get('files', {}).get('code', []):
    code_files.extend(collect_files(Path(f)) if Path(f).is_dir() else [Path(f)])

if code_files:
    result = extract(code_files)
    Path('.graphify_ast.json').write_text(json.dumps(result, indent=2))
    print(f'AST: {len(result[\"nodes\"])} nodes, {len(result[\"edges\"])} edges')
else:
    Path('.graphify_ast.json').write_text(json.dumps({'nodes':[],'edges':[],'input_tokens':0,'output_tokens':0}))
    print('No code files - skipping AST extraction')
"
```

### Part B - 语义提取

**快速路径**：若检测到零个 docs/papers/images（纯代码项目），跳过 Part B 直接进入 Part C。

**Step B0 - 检查提取缓存**

```bash
$(cat graphify-out/.graphify_python) -c "
import json
from graphify.cache import check_semantic_cache
from pathlib import Path

detect = json.loads(Path('.graphify_detect.json').read_text())
all_files = [f for files in detect['files'].values() for f in files]

cached_nodes, cached_edges, cached_hyperedges, uncached = check_semantic_cache(all_files)

if cached_nodes or cached_edges or cached_hyperedges:
    Path('.graphify_cached.json').write_text(json.dumps({'nodes': cached_nodes, 'edges': cached_edges, 'hyperedges': cached_hyperedges}))
Path('.graphify_uncached.txt').write_text('\n'.join(uncached))
print(f'Cache: {len(all_files)-len(uncached)} files hit, {len(uncached)} files need extraction')
"
```

若所有文件已缓存，跳到 Part C。

**Step B1 - 分块**

从 `.graphify_uncached.txt` 加载文件，按 20-25 个一组分块。图片单独一块。同目录文件尽量分到同一块。

**Step B2 - 顺序提取**

逐文件处理，提取规则：
- `EXTRACTED`：源中显式存在的关系（import、call、citation）
- `INFERRED`：合理推断（共享结构、隐含依赖）
- `AMBIGUOUS`：不确定——标记但不省略
- 代码文件：仅提取 AST 无法发现的语义边，不重复提取 import
- 文档/论文：命名概念、实体、引用。rationale 存为节点属性而非独立节点。使用 `file_type:"rationale"` 表示概念类节点。不要发明 `concept` 等自定义 file_type。`calls` 边：source=caller, target=callee
- 图片：使用 vision 理解图片内容，不仅仅 OCR
- DEEP_MODE：积极添加 INFERRED 边
- 语义相似：两个概念解决相同问题但无结构链接时，添加 `semantically_similar_to` INFERRED 边（confidence 0.6-0.95），仅限非显而易见的跨文件链接
- Hyperedge：3+ 节点共享概念/流程且 pairwise 边无法表达时添加，每文件最多 3 个
- `confidence_score` 必填：EXTRACTED=1.0, INFERRED=0.6-0.9, AMBIGUOUS=0.1-0.3

输出 schema：
```json
{"nodes":[{"id":"filestem_entityname","label":"Human Readable Name","file_type":"code|document|paper|image|rationale","source_file":"relative/path","source_location":null,"source_url":null,"captured_at":null,"author":null,"contributor":null}],"edges":[{"source":"node_id","target":"node_id","relation":"calls|implements|references|cites|conceptually_related_to|shares_data_with|semantically_similar_to|rationale_for","confidence":"EXTRACTED|INFERRED|AMBIGUOUS","confidence_score":1.0,"source_file":"relative/path","source_location":null,"weight":1.0}],"hyperedges":[{"id":"snake_case_id","label":"Human Readable Label","nodes":["node_id1","node_id2","node_id3"],"relation":"participate_in|implement|form","confidence":"EXTRACTED|INFERRED","confidence_score":0.75,"source_file":"relative/path"}],"input_tokens":0,"output_tokens":0}
```

处理完所有文件后写入 `.graphify_semantic_new.json`。

**Step B3 - 缓存与合并**

若超过半数 chunk 失败，停止并告知用户。

合并所有 chunk 文件：
```bash
$(cat graphify-out/.graphify_python) -c "
import json, glob
from pathlib import Path

chunks = sorted(glob.glob('graphify-out/.graphify_chunk_*.json'))
all_nodes, all_edges, all_hyperedges = [], [], []
total_in, total_out = 0, 0
for c in chunks:
    d = json.loads(Path(c).read_text())
    all_nodes += d.get('nodes', [])
    all_edges += d.get('edges', [])
    all_hyperedges += d.get('hyperedges', [])
    total_in += d.get('input_tokens', 0)
    total_out += d.get('output_tokens', 0)
Path('graphify-out/.graphify_semantic_new.json').write_text(json.dumps({
    'nodes': all_nodes, 'edges': all_edges, 'hyperedges': all_hyperedges,
    'input_tokens': total_in, 'output_tokens': total_out,
}, indent=2))
print(f'Merged {len(chunks)} chunks: {total_in:,} in / {total_out:,} out tokens')
"
```

保存到缓存：
```bash
$(cat graphify-out/.graphify_python) -c "
import json
from graphify.cache import save_semantic_cache
from pathlib import Path

new = json.loads(Path('.graphify_semantic_new.json').read_text()) if Path('.graphify_semantic_new.json').exists() else {'nodes':[],'edges':[],'hyperedges':[]}
saved = save_semantic_cache(new.get('nodes', []), new.get('edges', []), new.get('hyperedges', []))
print(f'Cached {saved} files')
"
```

合并 cached + new：
```bash
$(cat graphify-out/.graphify_python) -c "
import json
from pathlib import Path

cached = json.loads(Path('.graphify_cached.json').read_text()) if Path('.graphify_cached.json').exists() else {'nodes':[],'edges':[],'hyperedges':[]}
new = json.loads(Path('.graphify_semantic_new.json').read_text()) if Path('.graphify_semantic_new.json').exists() else {'nodes':[],'edges':[],'hyperedges':[]}

all_nodes = cached['nodes'] + new.get('nodes', [])
all_edges = cached['edges'] + new.get('edges', [])
all_hyperedges = cached.get('hyperedges', []) + new.get('hyperedges', [])
seen = set()
deduped = []
for n in all_nodes:
    if n['id'] not in seen:
        seen.add(n['id'])
        deduped.append(n)

merged = {
    'nodes': deduped,
    'edges': all_edges,
    'hyperedges': all_hyperedges,
    'input_tokens': new.get('input_tokens', 0),
    'output_tokens': new.get('output_tokens', 0),
}
Path('.graphify_semantic.json').write_text(json.dumps(merged, indent=2))
print(f'Extraction complete - {len(deduped)} nodes, {len(all_edges)} edges ({len(cached[\"nodes\"])} from cache, {len(new.get(\"nodes\",[]))} new)')
"
```

清理临时文件：`rm -f .graphify_cached.json .graphify_uncached.txt .graphify_semantic_new.json`

### Part C - 合并 AST + 语义为最终提取结果

```bash
$(cat graphify-out/.graphify_python) -c "
import sys, json
from pathlib import Path

ast = json.loads(Path('.graphify_ast.json').read_text())
sem = json.loads(Path('.graphify_semantic.json').read_text())

seen = {n['id'] for n in ast['nodes']}
merged_nodes = list(ast['nodes'])
for n in sem['nodes']:
    if n['id'] not in seen:
        merged_nodes.append(n)
        seen.add(n['id'])

merged_edges = ast['edges'] + sem['edges']
merged_hyperedges = sem.get('hyperedges', [])
merged = {
    'nodes': merged_nodes,
    'edges': merged_edges,
    'hyperedges': merged_hyperedges,
    'input_tokens': sem.get('input_tokens', 0),
    'output_tokens': sem.get('output_tokens', 0),
}
Path('.graphify_extract.json').write_text(json.dumps(merged, indent=2))
total = len(merged_nodes)
edges = len(merged_edges)
print(f'Merged: {total} nodes, {edges} edges ({len(ast[\"nodes\"])} AST + {len(sem[\"nodes\"])} semantic)')
"
```

---

## Step 4 - 构建图谱、聚类、分析

```bash
mkdir -p graphify-out
$(cat graphify-out/.graphify_python) -c "
import sys, json
from graphify.build import build_from_json
from graphify.cluster import cluster, score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate
from graphify.export import to_json
from pathlib import Path

extraction = json.loads(Path('.graphify_extract.json').read_text())
detection  = json.loads(Path('.graphify_detect.json').read_text())

G = build_from_json(extraction)
communities = cluster(G)
cohesion = score_all(G, communities)
tokens = {'input': extraction.get('input_tokens', 0), 'output': extraction.get('output_tokens', 0)}
gods = god_nodes(G)
surprises = surprising_connections(G, communities)
labels = {cid: 'Community ' + str(cid) for cid in communities}
questions = suggest_questions(G, communities, labels)

report = generate(G, communities, cohesion, labels, gods, surprises, detection, tokens, 'INPUT_PATH', suggested_questions=questions)
Path('graphify-out/GRAPH_REPORT.md').write_text(report)
to_json(G, communities, 'graphify-out/graph.json')

analysis = {
    'communities': {str(k): v for k, v in communities.items()},
    'cohesion': {str(k): v for k, v in cohesion.items()},
    'gods': gods,
    'surprises': surprises,
    'questions': questions,
}
Path('.graphify_analysis.json').write_text(json.dumps(analysis, indent=2))
if G.number_of_nodes() == 0:
    print('ERROR: Graph is empty - extraction produced no nodes.')
    raise SystemExit(1)
print(f'Graph: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges, {len(communities)} communities')
"
```

若打印 `ERROR: Graph is empty`，停止并告知用户原因。将 INPUT_PATH 替换为实际路径。

---

## Step 5 - 标注社区

读取 `.graphify_analysis.json`，为每个社区写一个 2-5 词的名称。然后重新生成报告：

```bash
$(cat graphify-out/.graphify_python) -c "
import sys, json
from graphify.build import build_from_json
from graphify.cluster import score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate
from pathlib import Path

extraction = json.loads(Path('.graphify_extract.json').read_text())
detection  = json.loads(Path('.graphify_detect.json').read_text())
analysis   = json.loads(Path('.graphify_analysis.json').read_text())

G = build_from_json(extraction)
communities = {int(k): v for k, v in analysis['communities'].items()}
cohesion = {int(k): v for k, v in analysis['cohesion'].items()}
tokens = {'input': extraction.get('input_tokens', 0), 'output': extraction.get('output_tokens', 0)}

labels = LABELS_DICT
questions = suggest_questions(G, communities, labels)

report = generate(G, communities, cohesion, labels, analysis['gods'], analysis['surprises'], detection, tokens, 'INPUT_PATH', suggested_questions=questions)
Path('graphify-out/GRAPH_REPORT.md').write_text(report)
Path('.graphify_labels.json').write_text(json.dumps({str(k): v for k, v in labels.items()}))
print('Report updated with community labels')
"
```

将 `LABELS_DICT` 替换为实际 dict，`INPUT_PATH` 替换为实际路径。

---

## Step 6 - 生成可视化

**HTML 始终生成**（除非 `--no-viz`）。**Obsidian vault 仅在 `--obsidian` 时生成**。

Obsidian（若 `--obsidian`）：
```bash
$(cat graphify-out/.graphify_python) -c "
import sys, json
from graphify.build import build_from_json
from graphify.export import to_obsidian, to_canvas
from pathlib import Path

extraction = json.loads(Path('.graphify_extract.json').read_text())
analysis   = json.loads(Path('.graphify_analysis.json').read_text())
labels_raw = json.loads(Path('.graphify_labels.json').read_text()) if Path('.graphify_labels.json').exists() else {}

G = build_from_json(extraction)
communities = {int(k): v for k, v in analysis['communities'].items()}
cohesion = {int(k): v for k, v in analysis['cohesion'].items()}
labels = {int(k): v for k, v in labels_raw.items()}

n = to_obsidian(G, communities, 'graphify-out/obsidian', community_labels=labels or None, cohesion=cohesion)
print(f'Obsidian vault: {n} notes in graphify-out/obsidian/')
to_canvas(G, communities, 'graphify-out/obsidian/graph.canvas', community_labels=labels or None)
print('Canvas: graphify-out/obsidian/graph.canvas')
"
```

HTML：
```bash
$(cat graphify-out/.graphify_python) -c "
import sys, json
from graphify.build import build_from_json
from graphify.export import to_html
from pathlib import Path

extraction = json.loads(Path('.graphify_extract.json').read_text())
analysis   = json.loads(Path('.graphify_analysis.json').read_text())
labels_raw = json.loads(Path('.graphify_labels.json').read_text()) if Path('.graphify_labels.json').exists() else {}

G = build_from_json(extraction)
communities = {int(k): v for k, v in analysis['communities'].items()}
labels = {int(k): v for k, v in labels_raw.items()}

if G.number_of_nodes() > 5000:
    print(f'Graph has {G.number_of_nodes()} nodes - too large for HTML viz.')
else:
    to_html(G, communities, 'graphify-out/graph.html', community_labels=labels or None)
    print('graph.html written')
"
```

---

## Step 7 - 可选导出

详见 [export.md](export.md) 中的 Neo4j / SVG / GraphML / MCP 章节。仅在用户传入对应 flag 时执行。

---

## Step 8 - Token 压缩基准（仅当 total_words > 5000）

```bash
$(cat graphify-out/.graphify_python) -c "
import json
from graphify.benchmark import run_benchmark, print_benchmark
from pathlib import Path

detection = json.loads(Path('.graphify_detect.json').read_text())
result = run_benchmark('graphify-out/graph.json', corpus_words=detection['total_words'])
print_benchmark(result)
"
```

若 `total_words <= 5000`，静默跳过。

---

## Step 9 - 保存 manifest、更新 cost、清理、报告

```bash
$(cat graphify-out/.graphify_python) -c "
import json
from pathlib import Path
from datetime import datetime, timezone
from graphify.detect import save_manifest

detect = json.loads(Path('.graphify_detect.json').read_text())
save_manifest(detect['files'])

extract = json.loads(Path('.graphify_extract.json').read_text())
input_tok = extract.get('input_tokens', 0)
output_tok = extract.get('output_tokens', 0)

cost_path = Path('graphify-out/cost.json')
if cost_path.exists():
    cost = json.loads(cost_path.read_text())
else:
    cost = {'runs': [], 'total_input_tokens': 0, 'total_output_tokens': 0}

cost['runs'].append({
    'date': datetime.now(timezone.utc).isoformat(),
    'input_tokens': input_tok,
    'output_tokens': output_tok,
    'files': detect.get('total_files', 0),
})
cost['total_input_tokens'] += input_tok
cost['total_output_tokens'] += output_tok
cost_path.write_text(json.dumps(cost, indent=2))

print(f'This run: {input_tok:,} input tokens, {output_tok:,} output tokens')
print(f'All time: {cost[\"total_input_tokens\"]:,} input, {cost[\"total_output_tokens\"]:,} output ({len(cost[\"runs\"])} runs)')
"
rm -f .graphify_detect.json .graphify_extract.json .graphify_ast.json .graphify_semantic.json .graphify_analysis.json .graphify_labels.json .graphify_chunk_*.json
rm -f graphify-out/.needs_update 2>/dev/null || true
```

告知用户：
```
Graph complete. Outputs in PATH_TO_DIR/graphify-out/

  graph.html            - interactive graph, open in browser
  GRAPH_REPORT.md       - audit report
  graph.json            - raw graph data
```

然后从 GRAPH_REPORT.md 粘贴 God Nodes、Surprising Connections、Suggested Questions 三个章节。

最后提供一个最有趣的后续探索问题：

> "The most interesting question this graph can answer: **[question]**. Want me to trace it?"
