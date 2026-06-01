# init 应区分新项目与已有项目

当前 `/apm init` 的 workflow 假设项目已有可检测元数据（package manifest、git history、README 等）。对全新空项目，Detection Phase 产出接近零，Generation 退化为大量 TODO 占位，用户体验差。

---

## 现状问题

| 场景 | Detection 产出 | 生成质量 |
|------|---------------|---------|
| 已有项目 | 技术栈、命令、版本、分支、变更摘要 | 高，大部分 auto-fill |
| 全新项目 | 几乎为空 | 低，全是 TODO 占位符 |

新用户拿到一堆 TODO 文件，感觉工具没有提供价值。

---

## 改进方向

将 init 拆为两条路径，Detection Phase 结束后根据元数据丰富度自动分流：

| 路径 | 触发条件 | 行为 |
|------|---------|------|
| 检测式 init | 检测到 manifest + git history | 现有流程（auto-fill + 确认） |
| 规划式 init | 几乎无元数据（空目录/刚 git init） | 交互引导，帮用户做初始决策 |

### 规划式 init 可能的交付物

- `PROJECT.md`：通过问答填充（语言/框架选型、项目目标、预期架构）
- `docs/state/`：记录初始化决策（选型理由、约束条件），而非"当前状态"
- `docs/manual/`：生成"开发环境搭建"骨架（基于选型结果给出具体步骤，而非 TODO）

### 实现考虑

- 可能需要独立的 ref file（如 `init-workflow-new-project.md`）定义规划式流程
- Confirmation Phase 的问题集不同：已有项目是"确认检测结果"，新项目是"收集规划意图"
- 幂等性规则不变：重跑时如果项目已有代码，自动切换到检测式路径
