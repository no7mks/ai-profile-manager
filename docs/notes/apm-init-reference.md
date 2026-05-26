# apm init 需要独立 reference file

当前 `/apm init` 在 SKILL.md 中只有一个交付物表和 PROJECT.md 模板，缺乏可执行的详细流程。

---

## 问题

Agent 执行 `/apm init` 时缺少明确指引，导致产出质量不稳定：

1. **无探测步骤**：不知道该读哪些文件来获取项目信息（`package.json`、`composer.json`、`Makefile`、`Cargo.toml`、目录结构、`git log` 等）。
2. **无交互流程**：哪些信息可自动探测、哪些必须向用户确认（如项目目标描述、敏感文件清单）、确认的顺序和方式。
3. **已有内容处理不明**：`PROJECT.md` 已存在时是补齐 TODO 还是覆盖？`docs/state/` 已有文件时是追加还是跳过？
4. **state/manual 内容指引不足**：只说"建立基线"，没有说基线应包含什么粒度、如何从代码中提取、最小可接受标准是什么。
5. **docs/changes/ 初始化**：unreleased 子目录结构是否也应该在 init 时建立（当前只有 `apm install` bootstrap 建骨架，init 不管目录）。
6. **幂等性**：重复执行 `/apm init` 的行为未定义——应该是安全的增量更新还是会破坏已有内容？

---

## 建议

在 apm skill 下新增 `references/ssot-setup.md`，内容覆盖：

- **探测阶段**：列出应检查的文件/命令清单，每项对应 PROJECT.md 的哪个字段。
- **确认阶段**：区分"可自动填充（高置信度）"和"必须用户确认"的字段；定义确认交互的格式。
- **生成阶段**：PROJECT.md 按模板生成；state 基线的最小内容要求（如：至少包含模块列表、当前版本、活跃分支）；manual 基线的最小内容要求（如：至少包含构建/测试/运行命令）。
- **已有内容策略**：明确 merge/skip/overwrite 的判定规则。
- **幂等性保证**：重复执行只补齐缺失，不破坏已有内容。
- **docs/changes/ 初始化**：确认 unreleased 子目录是否由 init 负责（或明确划给 bootstrap）。

---

## 优先级

中等。当前 `/apm init` 能用但质量不稳定，不阻塞日常工作。
