---
name: lark-sheets
description: "当用户需要将数据写入飞书表格、从飞书表格读取数据、追加行数据、或对接飞书开放平台时激活。"
---

# 飞书电子表格接入

本 Skill 提供飞书（Lark）电子表格的读写能力，包括认证、读取、覆盖写入和追加写入。

## 配置说明

飞书相关凭证和表格信息**由用户提供**，Agent 负责将其写入 `.config/.env` 文件保存。

| 配置项 | 说明 | 来源 |
|--------|------|------|
| `FEISHU_APP_ID` | 飞书自建应用 App ID | 用户提供 → Agent 写入 `.config/.env` |
| `FEISHU_APP_SECRET` | 飞书自建应用密钥 | 用户提供 → Agent 写入 `.config/.env` |
| `SPREADSHEET_TOKEN` | 目标表格 token | 用户提供 → Agent 写入 `.config/.env` |
| `SHEET_ID` | 默认 Sheet ID | 用户提供 → Agent 写入 `.config/.env` |

> **工作流程：** 用户提供配置 → Agent 更新 `.config/.env` → 从 `.env` 读取配置执行任务。
> 配置已存在时，直接从 `.config/.env` 读取，**无需再向用户索要**。

## Agent 交互流程

每次任务只需用户提供：

1. 数据来源（手动输入 / TGA 查询结果 / 其他）
2. 写入哪个 Sheet（不指定则默认使用 `SHEET_ID`）
3. 覆盖还是追加
4. 列的对应关系（如需筛选列）

## 敏感信息规范

**所有凭证必须存放在 `.config/.env` 文件中，禁止硬编码在代码里。**

```
<项目根目录>/
├── .config/
│   └── .env        ← 敏感配置，已加入 .gitignore
└── ...
```

```python
import pathlib, os
from dotenv import load_dotenv

_ENV_PATH = pathlib.Path(__file__).parent / ".config" / ".env"
load_dotenv(_ENV_PATH)

FEISHU_APP_ID     = os.environ["FEISHU_APP_ID"]
FEISHU_APP_SECRET = os.environ["FEISHU_APP_SECRET"]
SPREADSHEET_TOKEN = os.environ["SPREADSHEET_TOKEN"]
SHEET_ID          = os.environ["SHEET_ID"]
```

## 关键经验

### Wiki 表格的 SPREADSHEET_TOKEN

飞书 Wiki 页面（`feishu.cn/wiki/xxx`）中的表格，URL 里的 token **不是** SPREADSHEET_TOKEN，需要通过 Wiki API 获取真实的 `obj_token`：

```python
resp = httpx.get(
    "https://open.feishu.cn/open-apis/wiki/v2/spaces/get_node",
    headers=headers,
    params={"token": wiki_node_token},
)
spreadsheet_token = resp.json()["data"]["node"]["obj_token"]
```

### 追加写入用 PUT，不用 values_append

`values_append` 接口在某些表格上会报 `90202: wrong range`，改用 **PUT 覆盖写入 + 手动定位行号** 的方式追加。详见 [api-reference.md](references/api-reference.md)。

## 网络前提

飞书开放平台接口需要公网可达（`open.feishu.cn`）。

## 依赖

```
httpx
python-dotenv
```

## 详细 API 参考

见 [references/api-reference.md](references/api-reference.md)。
