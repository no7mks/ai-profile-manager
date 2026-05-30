# 飞书电子表格 API Reference

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

## Wiki 表格 Token 转换

飞书 Wiki 页面中的表格，URL 里的 token 不是真实的 SPREADSHEET_TOKEN，需通过 Wiki API 转换：

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

## 追加写入（PUT + 手动定位行号）

`values_append` 接口（POST）在某些表格上会报 `90202: wrong range`，改用 PUT 覆盖写入 + 手动定位行号的方式追加：

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

## 错误码速查

| code | 含义 | 处理方式 |
|------|------|---------|
| `0` | 成功 | — |
| `99991400` | token 无效或过期 | 重新获取 token |
| `99991663` | 应用未发布 | 发布新版本 |
| `11232` | 无文档权限 | 将应用分享到目标文档并设为可编辑 |
| `1254001` | 表格不存在 | 确认 spreadsheetToken 正确 |
| `90202` | range 错误 | 不要用 values_append，改用 PUT + 手动定位行号 |
