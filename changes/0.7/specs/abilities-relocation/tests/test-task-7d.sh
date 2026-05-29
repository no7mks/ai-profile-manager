#!/usr/bin/env bash
# E2E Test: Task 7.4 — 验证 show 命令展示 hook
# Ref: Requirement 11, AC 1-3
#
# 验证：
#   1. `php bin/apm show` 输出包含 Hooks 分区
#   2. `php bin/apm show --type hook` 仅输出 hook 分区（不含 Skills/Agents/Rules）
#   3. `php bin/apm show --type unknown` 返回错误并列出已知类型

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
APM="php $PROJECT_ROOT/bin/apm"

PASS=0
FAIL=0

pass() {
  echo "  PASS: $1"
  PASS=$((PASS + 1))
}

fail() {
  echo "  FAIL: $1"
  FAIL=$((FAIL + 1))
}

echo "=== Task 7.4: 验证 show 命令展示 hook ==="
echo ""

# --- Test 1: `show` 输出包含 Hooks 分区 ---
echo "[Test 1] show 输出包含 Hooks 分区"
EXIT_CODE=0
OUTPUT=$($APM show --target cursor 2>&1) || EXIT_CODE=$?

if [ "$EXIT_CODE" -eq 0 ]; then
  pass "show 命令退出码为 0"
else
  fail "show 命令退出码为 $EXIT_CODE，期望 0"
fi

if echo "$OUTPUT" | grep -q "^Hooks"; then
  pass "输出包含 Hooks 分区标题"
else
  fail "输出不包含 Hooks 分区标题"
fi

# 同时验证其他分区也存在（无过滤时应包含所有分区）
if echo "$OUTPUT" | grep -q "^Skills" && echo "$OUTPUT" | grep -q "^Agents" && echo "$OUTPUT" | grep -q "^Rules"; then
  pass "无过滤时包含 Skills、Agents、Rules 分区"
else
  fail "无过滤时缺少某些分区"
fi

echo ""

# --- Test 2: `show --type hook` 仅输出 hook 分区 ---
echo "[Test 2] show --type hook 仅输出 hook 分区"
EXIT_CODE=0
OUTPUT=$($APM show --target cursor --type hook 2>&1) || EXIT_CODE=$?

if [ "$EXIT_CODE" -eq 0 ]; then
  pass "--type hook 退出码为 0"
else
  fail "--type hook 退出码为 $EXIT_CODE，期望 0"
fi

if echo "$OUTPUT" | grep -q "^Hooks"; then
  pass "--type hook 输出包含 Hooks 分区"
else
  fail "--type hook 输出不包含 Hooks 分区"
fi

# 验证不包含其他分区
if echo "$OUTPUT" | grep -q "^Skills"; then
  fail "--type hook 输出不应包含 Skills 分区"
elif echo "$OUTPUT" | grep -q "^Agents"; then
  fail "--type hook 输出不应包含 Agents 分区"
elif echo "$OUTPUT" | grep -q "^Rules"; then
  fail "--type hook 输出不应包含 Rules 分区"
else
  pass "--type hook 输出不包含 Skills/Agents/Rules 分区"
fi

echo ""

# --- Test 3: `show --type unknown` 返回错误并列出已知类型 ---
echo "[Test 3] show --type unknown 返回错误并列出已知类型"
EXIT_CODE=0
OUTPUT=$($APM show --target cursor --type unknown 2>&1) || EXIT_CODE=$?

if [ "$EXIT_CODE" -ne 0 ]; then
  pass "--type unknown 退出码非 0 (实际: $EXIT_CODE)"
else
  fail "--type unknown 退出码为 0，期望非 0"
fi

if echo "$OUTPUT" | grep -qi "Unknown type: unknown"; then
  pass "错误信息包含 'Unknown type: unknown'"
else
  fail "错误信息不包含 'Unknown type: unknown'"
fi

if echo "$OUTPUT" | tr '\n' ' ' | grep -qi "Known types:.*rule.*agent.*skill.*hook.*gitignore.*preset"; then
  pass "错误信息列出所有已知类型"
else
  fail "错误信息未列出所有已知类型"
fi

echo ""

# --- Summary ---
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 7.4: ALL PASS"
  exit 0
else
  echo "❌ Task 7.4: FAILED ($FAIL failures)"
  exit 1
fi
