#!/usr/bin/env bash
# E2E Test: Task 7.5 — 验证 abilities.yaml 错误处理
# Ref: Requirement 1, AC 3; Requirement 8, AC 6
#
# 验证：构造含多个格式错误条目的 abilities.yaml，执行命令后一次性报告所有错误

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
APM="php $PROJECT_ROOT/bin/apm"
ABILITIES_FILE="$PROJECT_ROOT/abilities.yaml"
BACKUP_FILE="$PROJECT_ROOT/abilities.yaml.bak-e2e"

PASS=0
FAIL=0

# --- Setup: backup original abilities.yaml and write malformed version ---
cleanup() {
  if [ -f "$BACKUP_FILE" ]; then
    mv "$BACKUP_FILE" "$ABILITIES_FILE"
  fi
}
trap cleanup EXIT

cp "$ABILITIES_FILE" "$BACKUP_FILE"

cat > "$ABILITIES_FILE" << 'MALFORMED_YAML'
version: "1"

rules:
  - description: "Rule missing path and targets"

  - path: "valid-rule"
    description: "Rule missing targets"

hooks:
  - path: "hook-no-desc-no-targets"

  - targets:
      kiro: .kiro/hooks/orphan.kiro.hook

skills:
  - path: "skill-ok"
    description: "This skill has no targets"

MALFORMED_YAML

# --- Execute: run apm show command and capture output ---
echo "=== Task 7.5: abilities.yaml 错误处理 ==="
echo ""

OUTPUT=$($APM show --target cursor 2>&1) && EXIT_CODE=$? || EXIT_CODE=$?

echo "  Command exit code: $EXIT_CODE"
echo "  Output:"
echo "$OUTPUT" | sed 's/^/    /'
echo ""

# --- Verify: check that ALL errors are reported at once ---

# 1. Check that the output contains the validation failure header
if echo "$OUTPUT" | grep -q "Ability registry validation failed"; then
  echo "  PASS: Output contains 'Ability registry validation failed' header"
  ((PASS++))
else
  echo "  FAIL: Output missing 'Ability registry validation failed' header"
  ((FAIL++))
fi

# 2. Check that multiple errors are reported (at least 3 distinct error lines)
ERROR_COUNT=$(echo "$OUTPUT" | grep -c "missing required field(s):" || true)
if [ "$ERROR_COUNT" -ge 3 ]; then
  echo "  PASS: Multiple errors reported at once (found $ERROR_COUNT error lines)"
  ((PASS++))
else
  echo "  FAIL: Expected at least 3 error lines, found $ERROR_COUNT"
  ((FAIL++))
fi

# 3. Check that errors from different sections are present (rules + hooks + skills)
RULES_ERRORS=$(echo "$OUTPUT" | grep -c "rules\[" || true)
HOOKS_ERRORS=$(echo "$OUTPUT" | grep -c "hooks\[" || true)
SKILLS_ERRORS=$(echo "$OUTPUT" | grep -c "skills\[" || true)

if [ "$RULES_ERRORS" -ge 1 ] && [ "$HOOKS_ERRORS" -ge 1 ] && [ "$SKILLS_ERRORS" -ge 1 ]; then
  echo "  PASS: Errors from multiple sections (rules=$RULES_ERRORS, hooks=$HOOKS_ERRORS, skills=$SKILLS_ERRORS)"
  ((PASS++))
else
  echo "  FAIL: Expected errors from rules, hooks, and skills (rules=$RULES_ERRORS, hooks=$HOOKS_ERRORS, skills=$SKILLS_ERRORS)"
  ((FAIL++))
fi

# 4. Check that the command exits with non-zero (error)
if [ "$EXIT_CODE" -ne 0 ]; then
  echo "  PASS: Command exited with non-zero code ($EXIT_CODE)"
  ((PASS++))
else
  echo "  FAIL: Command exited with 0, expected non-zero"
  ((FAIL++))
fi

echo ""
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 7.5: ALL PASS"
  exit 0
else
  echo "❌ Task 7.5: FAILED ($FAIL failures)"
  exit 1
fi
