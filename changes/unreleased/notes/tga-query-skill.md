---
name: tga-query
version: 1.1.0
description: "TGA（数数系统）游戏数据查询：调用 TGA Query API 查询游戏运营数据，返回 CSV 格式结果。支持事件分析、留存分析、漏斗分析等模型，可按区服、平台、国家、时间范围过滤，支持自定义指标组合。当用户需要查询 DAU、DNU、付费金额、留存率、LTV 等游戏数据指标时使用。"
metadata:
  endpoint: "http://10.1.5.65:8811/"
---

# TGA（数数系统）数据查询

## Agent 交互流程

**在参数不完整时，必须逐步向用户确认，禁止自行猜测关键参数。**  
按以下顺序收集，已有的步骤可跳过：

### 第一步：确认服务地址

询问用户 API 服务的 host，**默认 `http://10.1.5.65:8811/`**：

> TGA Query API 的服务地址是？（直接回车使用默认 `http://10.1.5.65:8811/`）
>
> 说明：10.1.* 的 IP 是公司内的 IP，需要在公司内或 VPN 连接内网后才可访问。

### 第二步：确认分析模型

询问用户使用哪种分析模型：

> 请选择分析模型：
> 1. EVENT — 事件分析（最常用）
> 2. RETENTION — 留存分析
> 3. FUNNEL — 漏斗分析
> 4. DISTRIBUTION — 分布分析
> 5. PATH — 路径分析
> 6. PROP_ANALYSIS — 用户属性分析

### 第三步：获取 open_query

请用户从数数后台复制 API 查询条件：

> 请前往数数后台，打开对应报表，点击右上角 **「条件代码」→「API 查询条件」→「复制代码」**，然后将复制的 JSON 粘贴到这里。

**粘贴后的处理规则：**
- 若用户粘贴的是完整 `open_query` JSON，直接使用，进入第四步
- 若用户粘贴的仅是片段或描述性文字，根据"常用过滤片段"推断，并向用户确认推断结果
- `projectId` 不存在时默认填 `87`

### 第四步：确认并补全缺失参数

检查 `open_query` 中以下字段，缺失时逐一询问：

| 缺失字段 | 询问方式 |
|---------|---------|
| `startTime` / `endTime` | "查询时间范围是？（如：最近7天 / 2026-05-01 至 2026-05-07）" |
| `timeParticleSize` | "时间粒度：按天 / 按周 / 按月？" |
| `filts` 为空 | "需要过滤区服或平台吗？如：全服、欧服、iOS 等" |
| `events` 为空 | "需要查询哪些指标？如：DAU、新增、付费金额等" |

> **【必须执行】** 推断 filts 时，优先从"常用过滤片段"中匹配，**在发起请求前，必须把推断出的完整过滤条件列出来，明确询问用户是否正确，得到确认后才能继续**。禁止静默推断后直接请求。

### 第五步：发起请求并展示结果

参数确认完毕后，调用接口，解析 CSV，以表格形式展示结果。  
若结果超过 20 行，默认只展示前 20 行并说明总行数。

---

## 接口地址

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/tga-query/health` | GET | 健康检查，返回 `{"status":"ok"}` |
| `/api/v1/tga-query/query` | POST | 数据查询，返回 CSV |

---

## 查询接口

**POST** `http://<host>/api/v1/tga-query/query`

### 请求体结构

```json
{
  "report_model": "EVENT",
  "open_query": {
    "projectId": 87,
    "eventView": {
      "startTime": "YYYY-MM-DD 00:00:00",
      "endTime":   "YYYY-MM-DD 23:59:59",
      "timeParticleSize": "day",
      "relation": "and",
      "filts":   [],
      "groupBy": []
    },
    "events": []
  }
}
```

### 参数说明

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `report_model` | string | ✓ | 分析模型 |
| `open_query.projectId` | number | ✓ | 项目 ID，默认 `87` |
| `open_query.eventView.startTime` | string | ✓ | 格式 `"YYYY-MM-DD 00:00:00"` |
| `open_query.eventView.endTime` | string | ✓ | 格式 `"YYYY-MM-DD 23:59:59"` |
| `open_query.eventView.timeParticleSize` | string | ✓ | `"day"` / `"week"` / `"month"` |
| `open_query.eventView.filts` | array | — | 过滤条件，见下文 |
| `open_query.eventView.groupBy` | array | — | 分组字段，见下文 |
| `open_query.events` | array | ✓ | 指标列表，见下文 |

### report_model 取值

| 值 | 用途 |
|----|------|
| `EVENT` | 事件分析（最常用，支持所有自定义指标） |
| `RETENTION` | 留存分析 |
| `FUNNEL` | 漏斗分析 |
| `DISTRIBUTION` | 分布分析 |
| `PATH` | 路径分析 |
| `PROP_ANALYSIS` | 用户属性分析 |

---

## 过滤条件（filts）

### 字段结构

| 字段 | 类型 | 说明 |
|------|------|------|
| `columnName` | string | 字段英文名（实际 key） |
| `columnDesc` | string | 字段描述（仅可读性） |
| `comparator` | string | 比较运算符，见下表 |
| `filterType` | string | 固定 `"SIMPLE"` |
| `ftv` | array | 过滤值列表 |
| `selectType` | string | 值类型：`string` / `number` / `bool-s` |
| `tableType` | string | `user` / `event` / `user_cluster` |
| `subTableType` | string | 用户群过滤时填 `"cluster_by_import"`，否则 `""` |
| `specifiedClusterDate` | string | 通常为 `""` |
| `timeUnit` | string | 通常为 `""` |

### comparator 取值

| 值 | 含义 | ftv 示例 |
|----|------|---------|
| `equal` | 等于 | `["ios"]` |
| `notEqual` | 不等于 | `["china","japan"]` |
| `range` | 数值范围 [min, max] | `["160","5000"]` |
| `greaterThan` | 大于 | `["100"]` |
| `lessThan` | 小于 | `["100"]` |
| `contain` | 包含 | `["关键词"]` |
| `notContain` | 不包含 | `["关键词"]` |
| `belongToCluster` | 属于用户群 | `[]` |
| `notBelongToCluster` | 不属于用户群 | `[]` |

### 常用过滤片段

**iOS 平台**
```json
{"columnDesc":"os","columnName":"os","comparator":"equal","filterType":"SIMPLE","ftv":["ios"],"selectType":"string","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""}
```

**Android 平台**
```json
{"columnDesc":"os","columnName":"os","comparator":"equal","filterType":"SIMPLE","ftv":["android"],"selectType":"string","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""}
```

**排除中国/香港/日本（全球服标配）**
```json
{"columnDesc":"country","columnName":"country","comparator":"notEqual","filterType":"SIMPLE","ftv":["china","hong kong","japan"],"selectType":"string","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""}
```

**欧服（server 160~5000）**
```json
{"columnDesc":"server","columnName":"server","comparator":"range","filterType":"SIMPLE","ftv":["160","5000"],"selectType":"number","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""}
```

**美服（server 10017~999999）**
```json
{"columnDesc":"server","columnName":"server","comparator":"range","filterType":"SIMPLE","ftv":["10017","999999"],"selectType":"number","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""}
```

**排除测试用户（生产数据必加）**
```json
{"columnDesc":"测试用户","columnName":"test","comparator":"notBelongToCluster","filterType":"SIMPLE","ftv":[],"selectType":"bool-s","tableType":"user_cluster","subTableType":"cluster_by_import","specifiedClusterDate":"","timeUnit":""}
```

**排除脏数据用户（生产数据必加）**
```json
{"columnDesc":"脏数据用户","columnName":"cohort_20251129_042153","comparator":"notBelongToCluster","filterType":"SIMPLE","ftv":[],"selectType":"bool-s","tableType":"user_cluster","subTableType":"cluster_by_import","specifiedClusterDate":"","timeUnit":""}
```

### 区服组合速查

| 区服 | 过滤条件组合 |
|------|------------|
| 全服（排除中日港） | `country notEqual [china, hong kong, japan]` |
| 欧服 | 全服 + `server range [160, 5000]` |
| 美服-美澳 | `country equal [united states, australia]` + `server range [10017, 999999]` |
| 美服-其他 | `server range [10017, 999999]` + `country notEqual [united states, hong kong, china, australia, japan]` |

> 生产数据查询时，始终叠加"排除测试用户"和"排除脏数据用户"两个条件。

---

## 分组（groupBy）

```json
{"columnDesc":"os","columnName":"os","tableType":"user"}
{"columnDesc":"country","columnName":"country","tableType":"user"}
{"columnDesc":"server","columnName":"server","tableType":"user"}
```

不需要分组时传 `[]`。

---

## 指标（events）

### 普通指标

| 字段 | 类型 | 说明 |
|------|------|------|
| `eventName` | string | 事件名（如 `"ta@mainevent"`） |
| `eventNameDisplay` | string | 展示名（如 `"DAU"`） |
| `metricName` | string | 指标引用名，用于自定义指标 `$metric.xxx` |
| `format` | string | `integer` / `float` / `percent`（percent 返回 0~1 小数） |
| `filts` | array | 指标级过滤条件，通常 `[]` |
| `quota` | string | 通常 `""` |
| `relation` | string | 固定 `"and"` |
| `type` | string | `"normal"` |
| `analysisParams` | string | 通常 `""` |
| `eventUuid` | string | 同一请求内唯一即可 |

```json
{
  "eventName": "ta@mainevent",
  "eventNameDisplay": "DAU",
  "metricName": "dau",
  "format": "integer",
  "filts": [],
  "quota": "",
  "relation": "and",
  "type": "normal",
  "analysisParams": "",
  "eventUuid": "dL-nfy1Q"
}
```

### 自定义计算指标

`type` 为 `"customized"`，`customEvent` 用 `$metric.<metricName>` 引用已定义指标。

```json
{
  "eventName": "注册转化率",
  "customEvent": "$metric.dnu/$metric.newdevice",
  "format": "percent",
  "filts": [],
  "quota": "",
  "relation": "and",
  "type": "customized",
  "customFilters": [
    {"filts": [], "index": 0, "relation": "and"},
    {"filts": [], "index": 1, "relation": "and"}
  ],
  "eventUuid": "custom-01"
}
```

---

## 响应格式

返回 UTF-8 BOM 编码的 CSV（`Content-Type: text/csv`），首行为列名。

```python
import httpx, csv, io

resp = httpx.post(
    "http://<host>/api/v1/tga-query/query",
    json={"report_model": "EVENT", "open_query": open_query},
    timeout=120,
)
resp.raise_for_status()

text = resp.content.decode("utf-8-sig")   # 去掉 BOM
rows = list(csv.reader(io.StringIO(text)))
headers, data = rows[0], rows[1:]
```

---

## 错误码

| HTTP 状态码 | 含义 | 处理方式 |
|------------|------|---------|
| `400` | 参数错误 | 响应体 `error` 字段说明原因 |
| `422` | 字段缺失或类型错误 | 确认 `report_model` 和 `open_query` 都已传入 |
| `502` | TGA 返回业务错误 | 查看响应体 `detail.tga_return_message` |
| `504` | TGA 超时或连接失败 | 检查 TGA 服务可达性，或缩短查询时间范围 |
| `500` | 服务内部错误 | 检查服务日志 |

---

## 完整示例

全服近7天 DAU + 新增设备 + 付费金额（排除测试用户）：

```bash
curl -s -X POST http://http://10.1.5.65:8811//api/v1/tga-query/query \
  -H "Content-Type: application/json" \
  -d '{
    "report_model": "EVENT",
    "open_query": {
      "projectId": 87,
      "eventView": {
        "startTime": "2026-05-06 00:00:00",
        "endTime":   "2026-05-12 23:59:59",
        "timeParticleSize": "day",
        "relation": "and",
        "filts": [
          {"columnDesc":"country","columnName":"country","comparator":"notEqual","filterType":"SIMPLE","ftv":["china","hong kong","japan"],"selectType":"string","tableType":"user","subTableType":"","specifiedClusterDate":"","timeUnit":""},
          {"columnDesc":"测试用户","columnName":"test","comparator":"notBelongToCluster","filterType":"SIMPLE","ftv":[],"selectType":"bool-s","tableType":"user_cluster","subTableType":"cluster_by_import","specifiedClusterDate":"","timeUnit":""},
          {"columnDesc":"脏数据用户","columnName":"cohort_20251129_042153","comparator":"notBelongToCluster","filterType":"SIMPLE","ftv":[],"selectType":"bool-s","tableType":"user_cluster","subTableType":"cluster_by_import","specifiedClusterDate":"","timeUnit":""}
        ],
        "groupBy": []
      },
      "events": [
        {"eventName":"ta@mainevent","eventNameDisplay":"DAU","metricName":"dau","format":"integer","filts":[],"quota":"","relation":"and","type":"normal","analysisParams":"","eventUuid":"dL-nfy1Q"},
        {"eventName":"sdk_log_device","eventNameDisplay":"新增设备","metricName":"newdevice","format":"integer","filts":[],"quota":"","relation":"and","type":"normal","analysisParams":"","eventUuid":"zvpoDRsq"},
        {"eventName":"recharge","eventNameDisplay":"付费金额","metricName":"revenue","format":"float","filts":[],"quota":"","relation":"and","type":"normal","analysisParams":"","eventUuid":"sOJuUbEj"}
      ]
    }
  }'
```
