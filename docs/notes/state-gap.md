# State 文档缺口

当前 `docs/state/` 只有一个极简的 `architecture.md`，缺少大量系统行为细节。

---

## 缺失内容

1. **CLI 命令体系** — 所有命令的签名、参数、行为规则、错误场景
2. **abilities.yaml 数据模型** — 格式定义、字段约束、类型（rule/skill/agent/gitignore/preset）
3. **安装/检查/卸载行为** — 具体逻辑、边界条件、diff 判定规则、force 语义
4. **Preset 系统** — 组合逻辑、引用格式（`skill:xxx` / `rule:xxx` / `agent:xxx`）
5. **Gitignore 管理** — `@apm:block` marker 格式、操作规则
6. **目标平台映射** — 路径约定、文件命名规则、skill 目录级 duplicate 策略
7. **Scaffold/Init 行为** — 初始化逻辑、不覆盖规则、模板来源

---

## 建议拆分

| 文件 | 覆盖内容 |
|------|---------|
| `architecture.md` | 保留并补充模块职责细节 |
| `cli-commands.md` | 命令签名、参数、行为、错误场景 |
| `abilities-model.md` | abilities.yaml 格式、字段约束、preset 引用格式 |
| `install-behavior.md` | install/check/uninstall 逻辑、边界条件、diff 判定 |
| `gitignore.md` | marker block 格式与操作规则 |

---

## 时机

等 PRP-001 完成后统一补写（当前代码仍有 capture 残留，state 应反映最终实际状态）。
