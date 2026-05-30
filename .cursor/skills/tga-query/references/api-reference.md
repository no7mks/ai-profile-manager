# TGA Query API Reference

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
| `open_query.eventView.filts` | array | — | 过滤条件 |
| `open_query.eventView.groupBy` | array | — | 分组字段 |
| `open_query.events` | array | ✓ | 指标列表 |

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
| `comparator` | string | 比较运算符 |
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
| `format` | string | `integer` / `float` / `percent` |
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
curl -s -X POST http://10.1.5.65:8811/api/v1/tga-query/query \
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
