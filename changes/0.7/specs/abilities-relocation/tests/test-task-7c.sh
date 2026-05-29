#!/usr/bin/env bash
# E2E Test: Task 7.3 — 验证 hook install → check → uninstall 完整流程（Cursor 平台）
# Ref: Requirement 10, AC 1-7
#
# 验证步骤：
#   1. install preset with hook → .cursor/hooks/<name>/ 目录存在 + hooks.json 条目存在
#   2. check preset → hook 状态为 ok
#   3. uninstall preset → 目录删除 + hooks.json 条目移除
#
# 注意：Installer 从 packageRoot/hooks/<name>/ 读取 hook 源文件。
#       在开发模式下 packageRoot = 项目根目录。
#       本测试在项目根目录临时创建 hook 源文件，测试结束后清理。

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
APM="php $PROJECT_ROOT/bin/apm"

PASS=0
FAIL=0

pass() {
  echo "  PASS: $1"
  ((PASS++))
}

fail() {
  echo "  FAIL: $1"
  ((FAIL++))
}

# --- Setup ---
WORKSPACE=$(mktemp -d)
HOOK_NAME="test-e2e-hook"

# Create hook source at project root (where Installer looks for hooks)
mkdir -p "$PROJECT_ROOT/hooks/$HOOK_NAME"
cat > "$PROJECT_ROOT/hooks/$HOOK_NAME/$HOOK_NAME.sh" << 'SCRIPT'
#!/usr/bin/env bash
echo "test hook executed"
SCRIPT
chmod +x "$PROJECT_ROOT/hooks/$HOOK_NAME/$HOOK_NAME.sh"

cat > "$PROJECT_ROOT/hooks/$HOOK_NAME/$HOOK_NAME.json" << 'JSON'
{
  "preToolUse": [
    {
      "command": ".cursor/hooks/test-e2e-hook/test-e2e-hook.sh",
      "matcher": "Write"
    }
  ]
}
JSON

# Create _presets.json in workspace (PresetRegistry reads from cwd)
mkdir -p "$WORKSPACE/abilities"
cat > "$WORKSPACE/abilities/_presets.json" << 'JSON'
{
  "test-hook-preset": {
    "skills": [],
    "rules": [],
    "agents": [],
    "hooks": ["test-e2e-hook"]
  }
}
JSON

# Create gitignore template (required by Installer)
mkdir -p "$WORKSPACE/abilities/gitignore"
touch "$WORKSPACE/abilities/gitignore/template.gitignore"

# Set APM_BASELINE_ROOT to project root (for CheckService to find hook sources)
export APM_BASELINE_ROOT="$PROJECT_ROOT"

# Change to workspace directory
ORIGINAL_DIR="$(pwd)"
cd "$WORKSPACE"

# Cleanup function
cleanup() {
  cd "$ORIGINAL_DIR"
  rm -rf "$WORKSPACE"
  rm -rf "$PROJECT_ROOT/hooks/$HOOK_NAME"
  rmdir "$PROJECT_ROOT/hooks" 2>/dev/null || true
}
trap cleanup EXIT

echo "=== Task 7.3: Hook install → check → uninstall (Cursor) ==="
echo ""
echo "  Workspace: $WORKSPACE"
echo "  Hook source: $PROJECT_ROOT/hooks/$HOOK_NAME/"
echo ""

# --- Step 1: Install preset with hook ---
echo "--- Step 1: Install ---"
INSTALL_OUTPUT=$($APM install test-hook-preset --target cursor 2>&1) && INSTALL_EXIT=$? || INSTALL_EXIT=$?
echo "$INSTALL_OUTPUT"
echo ""

if [ "$INSTALL_EXIT" -eq 0 ]; then
  pass "install exit code = 0"
else
  fail "install exit code = $INSTALL_EXIT (expected 0)"
fi

if [ -d "$WORKSPACE/.cursor/hooks/$HOOK_NAME" ]; then
  pass ".cursor/hooks/$HOOK_NAME/ directory exists"
else
  fail ".cursor/hooks/$HOOK_NAME/ directory does NOT exist"
fi

if [ -f "$WORKSPACE/.cursor/hooks/$HOOK_NAME/$HOOK_NAME.sh" ]; then
  pass ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.sh exists"
else
  fail ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.sh does NOT exist"
fi

if [ -f "$WORKSPACE/.cursor/hooks/$HOOK_NAME/$HOOK_NAME.json" ]; then
  pass ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.json exists"
else
  fail ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.json does NOT exist"
fi

if [ -f "$WORKSPACE/.cursor/hooks.json" ]; then
  pass ".cursor/hooks.json exists"
  if grep -q ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.sh" "$WORKSPACE/.cursor/hooks.json"; then
    pass "hooks.json contains command entry for $HOOK_NAME"
  else
    fail "hooks.json does NOT contain command entry for $HOOK_NAME"
    echo "    hooks.json content:"
    cat "$WORKSPACE/.cursor/hooks.json" | sed 's/^/    /'
  fi
  if grep -q '"version"' "$WORKSPACE/.cursor/hooks.json"; then
    pass "hooks.json has version field"
  else
    fail "hooks.json missing version field"
  fi
else
  fail ".cursor/hooks.json does NOT exist"
  fail "hooks.json contains command entry (skipped - file missing)"
  fail "hooks.json has version field (skipped - file missing)"
fi

echo ""

# --- Step 2: Check preset → hook status ok ---
echo "--- Step 2: Check ---"
CHECK_OUTPUT=$($APM check test-hook-preset --target cursor 2>&1) && CHECK_EXIT=$? || CHECK_EXIT=$?
echo "$CHECK_OUTPUT"
echo ""

if [ "$CHECK_EXIT" -eq 0 ]; then
  pass "check exit code = 0 (all ok)"
else
  fail "check exit code = $CHECK_EXIT (expected 0 for all ok)"
fi

if echo "$CHECK_OUTPUT" | grep -qi "ok.*hook\|hook.*ok\|hook.*unchanged\|\[ok\].*hook"; then
  pass "check output reports hook as ok/unchanged"
else
  fail "check output does NOT report hook as ok/unchanged"
  echo "    check output: $CHECK_OUTPUT"
fi

echo ""

# --- Step 3: Uninstall preset ---
echo "--- Step 3: Uninstall ---"
UNINSTALL_OUTPUT=$($APM preset:uninstall test-hook-preset --target cursor --force 2>&1) && UNINSTALL_EXIT=$? || UNINSTALL_EXIT=$?
echo "$UNINSTALL_OUTPUT"
echo ""

if [ "$UNINSTALL_EXIT" -eq 0 ]; then
  pass "uninstall exit code = 0"
else
  fail "uninstall exit code = $UNINSTALL_EXIT (expected 0)"
fi

if [ ! -d "$WORKSPACE/.cursor/hooks/$HOOK_NAME" ]; then
  pass ".cursor/hooks/$HOOK_NAME/ directory removed"
else
  fail ".cursor/hooks/$HOOK_NAME/ directory still exists after uninstall"
fi

if [ -f "$WORKSPACE/.cursor/hooks.json" ]; then
  if grep -q ".cursor/hooks/$HOOK_NAME/$HOOK_NAME.sh" "$WORKSPACE/.cursor/hooks.json"; then
    fail "hooks.json still contains command entry after uninstall"
    echo "    hooks.json content:"
    cat "$WORKSPACE/.cursor/hooks.json" | sed 's/^/    /'
  else
    pass "hooks.json no longer contains command entry for $HOOK_NAME"
  fi
  if grep -q '"version"' "$WORKSPACE/.cursor/hooks.json"; then
    pass "hooks.json structure preserved after uninstall (version field present)"
  else
    fail "hooks.json structure NOT preserved after uninstall"
  fi
else
  fail "hooks.json was deleted (should preserve structure per AC7)"
fi

echo ""

# --- Summary ---
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 7.3: ALL PASS"
  exit 0
else
  echo "❌ Task 7.3: FAILED ($FAIL failures)"
  exit 1
fi
