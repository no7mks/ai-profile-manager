#!/usr/bin/env bash
# E2E Test: Task 7.1 — 验证 Capture/Ingest 命令不可达
# Ref: Requirement 6, AC 5; Requirement 7, AC 1
#
# 验证以下命令均返回 "is not defined" 错误：
#   capture, skill:capture, rule:capture, agent:capture, ingest

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
APM="php $PROJECT_ROOT/bin/apm"

PASS=0
FAIL=0

check_command_not_defined() {
  local cmd="$1"
  local output
  local exit_code

  output=$($APM $cmd 2>&1) && exit_code=$? || exit_code=$?

  if echo "$output" | grep -qi "is not defined"; then
    echo "  PASS: '$cmd' → command is not defined (exit=$exit_code)"
    ((PASS++))
  else
    echo "  FAIL: '$cmd' → unexpected output: $output (exit=$exit_code)"
    ((FAIL++))
  fi
}

echo "=== Task 7.1: Capture/Ingest 命令不可达 ==="
echo ""

check_command_not_defined "capture"
check_command_not_defined "skill:capture"
check_command_not_defined "rule:capture"
check_command_not_defined "agent:capture"
check_command_not_defined "ingest"

echo ""
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 7.1: ALL PASS"
  exit 0
else
  echo "❌ Task 7.1: FAILED ($FAIL failures)"
  exit 1
fi
