# 特殊任务

## 手工测试

- 执行时机：feature 分支上完成（finish 之前），或推迟到 release stabilize 阶段
- 生成或执行手工测试时，按 manual-testing 规范编排和执行

## Code Review

任何 spec（feature / release / hotfix），在 finish 之前，`tasks.md` 必须包含一个 code-review task。

执行时委托给 `code-reviewer` sub-agent，不在主 agent 上下文中内联执行。

## Release Stabilize

在 release 分支上执行 stabilize 阶段的测试 task 时，遵循以下额外规则：

### Alpha Tag

- 每个测试 task 开始前打 alpha tag（如 `v0.2-alpha3`）
- alpha tag 序号：查询已有 alpha tag，取最大序号 +1；无 alpha tag 则为 alpha1
- alpha tag 打出后到 commit 前，禁止任何 git commit

### 问题处理

- 发现问题时：3 轮对话内能修复则直接修复，不提 issue
- 超过 3 轮修不好的：创建 issue 文件，标注发现时的 alpha tag

### Issue Severity 处理

| Severity | 处理方式 |
|----------|----------|
| `[P0] critical` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P1] major` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P2] minor` | 需用户确认是否可接受带 issue 发布 |
| `[P3] trivial` | 可忽略，不阻塞发布 |

### Issue 修复规则

- 修复前必须先编写 reproduction test
- 修复后重新执行对应测试项，确认通过后更新 issue 状态为 closed

### Beta Tag

- beta tag 由用户手动控制，agent 不可自主打
