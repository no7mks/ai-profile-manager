# AI Profile Manager (apm)

管理 AI IDE/CLI abilities（skill、rule、agent）的 PHP CLI 工具。

---

## 架构概览

- 单体 CLI 应用，基于 Symfony Console
- abilities 注册表（`abilities.yaml`）驱动安装/检查逻辑
- 目标平台：Cursor（`.cursor/`）、Kiro（`.kiro/`）

---

## 技术栈

- 语言：PHP 8.5
- 构建/包管理：Composer
- 测试框架：PHPUnit 13

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 单元测试（Unit）— 默认 suite，不含 E2E
./vendor/bin/phpunit

# E2E 测试（通过 bin/apm 入口验证完整生命周期）
./vendor/bin/phpunit --testsuite e2e

# 覆盖率测试（Coverage）
./vendor/bin/phpunit --coverage-text
```

测试执行约束：

- 测试命令必须将完整输出写入文件（日志或报告），后续检索与过滤基于该文件进行。
- 禁止仅为了切换过滤方式而反复重跑同一测试命令。

---

## 知识图谱

本项目使用 graphify 维护持久化知识图谱（`graphify-out/`）。架构、依赖、模块耦合相关问题优先查图谱，而非 grep 或逐文件阅读。

```
/graphify .                    # 构建或重建图谱
/graphify . --update           # 增量更新（仅变更文件）
/graphify query "<question>"   # 查询关系
/graphify path "A" "B"         # 两个概念间的最短路径
/graphify explain "X"          # 解释单个节点
```

---

## 版本号位置

- `composer.json`：`version` 字段（若存在）
- `src/Application.php`：Symfony Application 第二个构造参数

---

## 敏感文件

- 无
