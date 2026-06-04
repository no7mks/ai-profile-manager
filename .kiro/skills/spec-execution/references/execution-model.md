# 执行模型

所有被派发的 sub-agent 必须遵守本文件的规则。main-agent 派发时必须将本文件内容传递给 sub-agent。

---

## 异常处理

遇到 failure（编译失败、测试失败、运行时错误等）时，按以下流程处理：

```
failure
|
+-- 本次变更引入?
|   |
|   YES --> 常规修复 (max 3 retries)
|           +-- ok     --> continue
|           +-- 3x fail --> STOP, report Blocker
|
+-- 既有问题
    |
    prompt 中明确说了可忽略?
    +-- YES --> skip, continue
    +-- NO  --> try fix (max 3 retries)
                +-- ok   --> continue
                +-- 3x fail --> STOP ALL, report
```

### 关键原则

- **不逃避**：既有问题不是借口，默认态度是立即尝试解决
- **不自行跳过**：不得以"pre-existing issue"、"非本次引入"等理由自行决定跳过
- **快速止损**：既有问题最多 3 次修复机会，失败即终止并汇报，避免在非本次任务的问题上消耗过多上下文

### Blocker Escalation

除修复失败外，以下情况也必须立即终止并汇报：

| 场景 | 说明 |
|------|------|
| 指令不清 | task 描述模糊，存在多种合理理解，不同理解会导致不同实现 |
| 缺少依赖 | task 依赖的外部资源、配置、权限不可用，且无法在当前 session 内解决 |
| 设计决策 | 实现过程中遇到 design.md 未覆盖的架构、接口、数据模型决策 |

汇报时应包含：问题描述、已尝试的方案（如有）、建议的下一步选项。

---

## 测试规范

### 自动化测试分层

| 层 | 测试类型 | 覆盖范围 |
|----|---------|---------|
| Service | property-based tests | 数据生成、唯一性、不变性、边界条件 |
| API / CLI / Controller | integration tests | I/O 行为（request/response、stdout/stderr、exit code） |
| 单元测试 | 补充测试 | 错误路径与边界条件 |

### 单元测试覆盖自检

每次完成单元测试编写后，对照 requirements 和当前实现，review 是否覆盖了以下场景：

- 正常流程（happy path）
- 边界条件（空值、空列表、极端输入）
- 错误场景（无效输入、不存在的资源、异常路径）
- 中文 / Unicode 内容的 round-trip

如果发现遗漏场景，补充测试后再标记 task 完成。

### Bug Fix 测试规则

| 场景 | 测试要求 | 强度 |
|------|---------|------|
| 正式 bug fix（有 issue 记录） | 必须先编写 reproduction test，运行确认在未修复代码上失败，然后修复，再次确认通过 | must |
| 开发中发现的行为问题（逻辑错误、边界遗漏、状态异常等） | 应补充对应的 test case 覆盖该场景，不强制 test-first 顺序 | should |
| 开发中的编码错误（编译失败、typo、配置缺失等） | 不需要专门写 test | — |

判定标准：如果问题属于"行为层面"（即系统产出了错误的结果或行为），就应该有 test 覆盖；如果只是"写错了"（编译不过、拼写错误），则不需要。

---

## Checkpoint

- checkpoint task 必须执行其描述中指定的验证命令
- 通过标准：测试全部通过 + 输出干净（无 compiler warning、无 deprecation warning、无异常堆栈）
- **State 同步**：若本 top-level task 改变了系统事实（行为、边界、配置等），在 checkpoint commit 前更新对应 `docs/state/` 文件；无变化可省略
- 未通过 checkpoint，不得标记完成
- checkpoint 失败时，修复问题后重新执行验证，直到通过

---

## 特殊任务类型

### E2E 测试

执行时按 `e2e-testing` steering 的完整规范编排和执行。本文件不重复其内容。

### Code Review

委托给 `code-reviewer` sub-agent 执行，不在当前 sub-agent 上下文中内联执行。

### Release Stabilize

在 release 分支上执行 stabilize 阶段的测试 task 时，遵循以下额外规则：

**Alpha Tag**：
- 每个测试 task 开始前打 alpha tag（如 `v0.2-alpha3`）
- alpha tag 序号：查询已有 alpha tag，取最大序号 +1；无 alpha tag 则为 alpha1
- alpha tag 打出后到 commit 前，禁止任何 git commit

**问题处理**：
- 发现问题时：3 轮对话内能修复则直接修复，不提 issue
- 超过 3 轮修不好的：创建 issue 文件，标注发现时的 alpha tag

**Issue Severity**：

| Severity | 处理方式 |
|----------|----------|
| `[P0] critical` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P1] major` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P2] minor` | 需用户确认是否可接受带 issue 发布 |
| `[P3] trivial` | 可忽略，不阻塞发布 |

**Issue 修复规则**：
- 修复前必须先编写 reproduction test
- 修复后重新执行对应测试项，确认通过后更新 issue 状态为 closed

**Beta Tag**：
- beta tag 由用户手动控制，agent 不可自主打
