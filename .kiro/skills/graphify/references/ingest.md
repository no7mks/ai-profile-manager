# Ingest — 添加内容与自动化

外部内容添加（`add`）和自动化（`--watch`、git hook、CLAUDE.md）。

## 通用规则

- 集成不应静默失败；始终展示错误和下一步操作
- 对变更素材/状态的命令，确认输出文件路径
- 若集成需要后续操作（`--update`、凭据、运行守护进程），明确说明

---

## For /graphify add

抓取 URL 并加入素材库，然后更新图谱。

```bash
$(cat graphify-out/.graphify_python) -c "
import sys
from graphify.ingest import ingest
from pathlib import Path

try:
    out = ingest('URL', Path('./raw'), author='AUTHOR', contributor='CONTRIBUTOR')
    print(f'Saved to {out}')
except ValueError as e:
    print(f'error: {e}', file=sys.stderr)
    sys.exit(1)
except RuntimeError as e:
    print(f'error: {e}', file=sys.stderr)
    sys.exit(1)
"
```

将 `URL` 替换为实际 URL，`AUTHOR` 和 `CONTRIBUTOR` 替换为用户提供的值。若命令报错，告知用户原因。成功保存后自动对 `./raw` 运行 `--update` pipeline。

支持的 URL 类型（自动检测）：
- Twitter/X → 通过 oEmbed 抓取，保存为 `.md`
- arXiv → 摘要 + 元数据保存为 `.md`
- PDF → 下载为 `.pdf`
- 图片（.png/.jpg/.webp）→ 下载，下次构建时 vision 提取
- 任意网页 → 通过 html2text 转为 markdown

---

## For --watch

启动后台监听器，监控文件夹并在文件变更时自动更新图谱。

```bash
python3 -m graphify.watch INPUT_PATH --debounce 3
```

将 INPUT_PATH 替换为要监听的文件夹。行为取决于变更类型：

- **仅代码文件**（.py, .ts, .go 等）：立即重新运行 AST 提取 + 重建 + 聚类，无需 LLM
- **文档、论文或图片**：写入 `graphify-out/needs_update` 标志并打印通知，提示运行 `/graphify --update`

Debounce（默认 3s）：等待文件活动停止后再触发。按 Ctrl+C 停止。

---

## For git commit hook

安装 post-commit hook，每次 commit 后自动重建图谱。

```bash
graphify hook install    # 安装
graphify hook uninstall  # 移除
graphify hook status     # 检查状态
```

每次 `git commit` 后，hook 检测哪些代码文件变更（通过 `git diff HEAD~1`），对这些文件重新运行 AST 提取并重建 `graph.json` 和 `GRAPH_REPORT.md`。文档/图片变更被 hook 忽略——需手动运行 `/graphify --update`。

若已存在 post-commit hook，graphify 追加而非替换。

---

## For native CLAUDE.md integration

每个项目运行一次，使 graphify 在 Claude Code 会话中始终可用：

```bash
graphify claude install    # 安装
graphify claude uninstall  # 移除
```

在本地 `CLAUDE.md` 中写入 `## graphify` 章节，指示 Claude 在回答代码库问题前检查图谱，并在代码变更后重建图谱。
