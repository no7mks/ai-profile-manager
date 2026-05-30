---
name: lark-sheets
version: 1.2.0
description: "飞书（Lark）电子表格接入：创建自建应用、获取 tenant_access_token、读写电子表格数据。当用户需要将数据写入飞书表格、从飞书表格读取数据、追加行数据、或对接飞书开放平台时使用。"
metadata:
  base_url: "https://open.feishu.cn/open-apis"
---

# 飞书电子表格接入

## 配置说明（本项目）

飞书相关凭证和表格信息**由用户提供**，Agent 负责将其写入 `.config/.env` 文件保存。

| 配置项 | 说明 | 来源 |
|--------|------|------|
| `FEISHU_APP_ID` | 飞书自建应用 App ID | 用户提供 → Agent 写入 `.config/.env` |
| `FEISHU_APP_SECRET` | 飞书自建应用密钥 | 用户提供 → Agent 写入 `.config/.env` |
| `SPREADSHEET_TOKEN` | 目标表格 token（Wiki URL 最后一段，需通过 Wiki API 转换为真实 obj_token） | 用户提供 → Agent 写入 `.config/.env` |
| `SHEET_ID` | 默认 Sheet ID | 用户提供 → Agent 写入 `.config/.env` |
| `TGA_HOST` | 数数服务地址，**默认 `http://10.1.5.65:8811`，无需向用户索要** | 可选，不填则使用默认值 |

> **工作流程：** 用户提供上述配置 → Agent 更新 `.config/.env` → 从 `.env` 读取配置执行任务。
> 配置已存在时，直接从 `.config/.env` 读取，**无需再向用户索要**。

每次任务只需用户提供：
1. TGA 查询条件 JSON
2. 写入哪个 Sheet（不指定则默认 Sheet1）
3. 覆盖还是追加
4. 列的对应关系（如需筛选列）

---

## 重要经验：Wiki 表格的 SPREADSHEET_TOKEN

飞书 Wiki 页面（`feishu.cn/wiki/xxx`）中的表格，**URL 里的 token 不是 SPREADSHEET_TOKEN**，需要通过 Wiki API 获取真实的 `obj_token`：

```python
# wiki_node_token 是 URL 最后一段，如 SHv6wDInbiOalRkFVv6cUWL1nme
resp = httpx.get(
    "https://open.feishu.cn/open-apis/wiki/v2/spaces/get_node",
    headers=headers,
    params={"token": wiki_node_token},
)
# 真实的 spreadsheetToken 在 data.node.obj_token
spreadsheet_token = resp.json()["data"]["node"]["obj_token"]
```

---

## 重要经验：追加写入用 PUT，不用 values_append

`values_append` 接口（POST）在某些表格上会报 `90202: wrong range`，**改用 PUT 覆盖写入 + 手动定位行号** 的方式追加：

```python
def find_last_row(token: str, spreadsheet_token: str, sheet_id: str) -> int:
    """读取 A 列，找到最后一个有数据的行号（1-indexed）。"""
    headers = {"Authorization": f"Bearer {token}"}
    full_range = f"{sheet_id}!A1:A1000"
    resp = httpx.get(
        f"https://open.feishu.cn/open-apis/sheets/v2/spreadsheets/{spreadsheet_token}/values/{full_range}",
        headers=headers,
    )
    values = resp.json()["data"]["valueRange"].get("values", [])
    last = 0
    for i, row in enumerate(values):
        if row and row[0] not in (None, ""):
            last = i + 1
    return last


def append_rows(token: str, spreadsheet_token: str, sheet_id: str, rows: list[list]) -> None:
    """追加写入：先找最后一行，再用 PUT 写入到下一行。"""
    last_row = find_last_row(token, spreadsheet_token, sheet_id)
    start_row = last_row + 1
    col_count = max(len(r) for r in rows)
    end_col = chr(ord("A") + col_count - 1)
    end_row = start_row + len(rows) - 1
    range_str = f"{sheet_id}!A{start_row}:{end_col}{end_row}"

    headers = {"Authorization": f"Bearer {token}"}
    payload = {"valueRange": {"range": range_str, "values": rows}}
    resp = httpx.put(
        f"https://open.feishu.cn/open-apis/sheets/v2/spreadsheets/{spreadsheet_token}/values",
        headers=headers,
        json=payload,
    )
    data = resp.json()
    if data.get("code") != 0:
        raise RuntimeError(f"写入表格失败: {data}")
```

---

## 敏感信息存储规范

**所有凭证必须存放在项目根目录下的 `.config/.env` 文件中，禁止硬编码在代码里。**

```
<项目根目录>/
├── .config/
│   └── .env        ← 敏感配置，加入 .gitignore
├── .gitignore
└── your_script.py
```

```env
FEISHU_APP_ID=cli_xxxxxxxx
FEISHU_APP_SECRET=xxxxxxxxxxxxxxxx
SPREADSHEET_TOKEN=shtxxxxxxxxxxxxxx
SHEET_ID=0b5b5a
TGA_HOST=http://<host>:<port>
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

---

## 应用创建与权限配置

### 创建应用

1. 打开 [飞书开放平台](https://open.feishu.cn/app) → 点击「创建企业自建应用」
2. 填写应用名称、描述，点击「确认创建」
3. 左侧菜单 →「凭证与基础信息」，记录 `App ID` 和 `App Secret`

### 添加权限

左侧菜单 →「权限管理」→ 搜索并开启所需权限。

| 场景 | 权限 scope |
|------|-----------|
| 电子表格读写 | `sheets:spreadsheet` |
| Wiki 节点查询 | `wiki:wiki:readonly` |

> 添加权限后，在「版本管理与发布」发布新版本才生效。

### 文档授权

打开飞书文档/表格 → 右上角「分享」→ 搜索应用名称 → 设为「可编辑」。

---

## 认证接口

**POST** `https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal`

有效期 **2 小时**。

```python
def get_tenant_token(app_id: str, app_secret: str) -> str:
    resp = httpx.post(
        "https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal",
        json={"app_id": app_id, "app_secret": app_secret},
    )
    data = resp.json()
    if data.get("code") != 0:
        raise RuntimeError(f"获取飞书 token 失败: {data}")
    return data["tenant_access_token"]
```

---

## 电子表格接口

请求头统一携带：`{"Authorization": f"Bearer {token}"}`

### 获取 Sheet 列表

**GET** `/sheets/v3/spreadsheets/{spreadsheetToken}/sheets/query`

```python
resp = httpx.get(
    f"https://open.feishu.cn/open-apis/sheets/v3/spreadsheets/{spreadsheet_token}/sheets/query",
    headers=headers,
)
sheets = resp.json()["data"]["sheets"]
# [{"sheet_id": "852051", "title": "Sheet1"}, ...]
```

### 读取单元格区间

**GET** `/sheets/v2/spreadsheets/{spreadsheetToken}/values/{range}`

```python
range_str = f"{sheet_id}!A1:D10"
resp = httpx.get(
    f"https://open.feishu.cn/open-apis/sheets/v2/spreadsheets/{spreadsheet_token}/values/{range_str}",
    headers=headers,
)
values = resp.json()["data"]["valueRange"]["values"]  # 二维数组
```

### 覆盖写入

**PUT** `/sheets/v2/spreadsheets/{spreadsheetToken}/values`

```python
payload = {"valueRange": {"range": f"{sheet_id}!A1:C10", "values": rows}}
resp = httpx.put(url, headers=headers, json=payload)
```

---

## 错误码速查

| code | 含义 | 处理方式 |
|------|------|---------|
| `0` | 成功 | — |
| `99991400` | token 无效或过期 | 重新获取 token |
| `99991663` | 应用未发布 | 发布新版本 |
| `11232` | 无文档权限 | 将应用分享到目标文档并设为可编辑 |
| `1254001` | 表格不存在 | 确认 spreadsheetToken 正确 |
| `90202` | range 错误 | 不要用 values_append，改用 PUT + 手动定位行号 |

---

## 依赖

```
httpx
python-dotenv
```
