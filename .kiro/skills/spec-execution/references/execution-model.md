# 执行模型

## 基本规则

- 当前 spec 目录下的 `tasks.md` 为唯一执行清单
- top-level task 必须按序号逐项完成，不允许跨步跳跃
- 每个 task 通过独立 session 执行（不可假设上下文继承）
- checkpoint 成功时，进行一次 commit

## Pre-execution Review

每次开始执行 tasks.md（或从中断处恢复执行）时，先做一次轻量预检：

1. **Drift 检测**：将当前要执行的 task 涉及的关键文件与 design.md 中的预期做快速比对——如果代码结构、接口签名、依赖关系已发生变化（例如被其他分支 merge 改动），标记为 drift
2. **决策**：
   - 无 drift → 正常执行
   - 发现 drift 但不影响当前 task 的实现路径 → 记录 drift，正常执行
   - 发现 drift 且影响当前 task 的实现路径 → **停下来**，向用户报告 drift 内容，等待确认后再继续

## 并行执行策略

执行一个 top-level task 时，先分析其所有 sub-task：

1. **上下文分析**：列出每个 sub-task 需要读取的文件和需要修改的文件
2. **冲突检测**：如果两个 sub-task 修改同一个文件，或一个 sub-task 的输出是另一个的输入，则存在冲突
3. **分组**：将不冲突的 sub-task 分为可并行组，冲突的 sub-task 保持串行
4. **并行执行**：同一组内的 sub-task 使用并行的 sub-agent 同时执行
5. **汇总**：并行组内所有 sub-task 完成后，统一 commit 一次，然后进入下一组

如果 tasks.md 中包含 Task Dependency Graph（TDG），优先按 TDG 的 wave 分组执行——同一 wave 内的 sub-task 并行，不同 wave 串行。如果没有 TDG 也没有标注，按上述策略自行判断。

## Sub-agent 派发上下文

派发规则详见 SKILL.md 的 Step 2.4。此处仅列出核心原则：

- 必传：激活 spec-execution skill 的指令、task 完整描述、相关文件路径、前序产出、error-handling 规则
- 按类型追加：功能实现带 quality-standards，E2E 带 e2e-testing steering，Code Review 带 special-tasks
- 不传递：整个 tasks.md、无关 references、已完成 task 的过程

## Checkpoint

- checkpoint task 必须执行其描述中指定的验证命令
- 通过标准：不仅要求测试全部通过，还要求**输出干净**——无 compiler warning、无 deprecation warning、无异常堆栈、无非预期的 stderr 输出
- **State 同步**：checkpoint commit 前，必须将本 top-level task 实现的功能行为、边界条件、错误处理、配置格式等更新到 `docs/state/` 对应文件。state 的描述粒度应足以推导 functional test / integration test 的断言
- 未通过 checkpoint，不得进入下一层实现
- checkpoint 失败时，修复问题后重新执行验证，直到通过
