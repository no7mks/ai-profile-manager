# 编排规则

当 tasks.md 没有 TDG（Task Dependency Graph）时，main-agent 按以下策略自行分析 sub-task 的依赖关系，生成 TDG。

**产出物**：在 tasks.md 的最后生成一个 `## Task Dependency Graph` section，内容如下（包含```json的标识符）：

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1"] }
  ]
}
```

---

## 并行分析流程

1. **上下文分析**：列出每个 sub-task 需要读取的文件和需要修改的文件
2. **冲突检测**：如果两个 sub-task 修改同一个文件，或一个 sub-task 的输出是另一个的输入，则存在冲突
3. **分组**：将不冲突的 sub-task 分为同一 wave，冲突的 sub-task 放入后续 wave
4. **输出**：按 wave 顺序列出分组结果，向用户汇报

## 判断原则

- 读同一文件不算冲突，写同一文件才算
- 一个 sub-task 的产出（新文件、新接口）被另一个 sub-task 依赖 → 串行
- 不确定时，保守选择串行
