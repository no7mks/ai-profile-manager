#!/usr/bin/env bash
# E2E Test: Task 7.2 — 验证 hook install → check → uninstall 完整流程（Kiro 平台）
# Ref: Requirement 9, AC 1-4
#
# 测试步骤：
#   1. 创建临时 package root（含 hook 源文件）和临时 workspace
#   2. 通过 Installer::installTyped 安装 hook 到 .kiro/hooks/
#   3. 通过 CheckService::checkTyped 验证 hook 状态为 ok (unchanged)
#   4. 修改已安装 hook 文件，再次 check 验证状态为 drift (modified)
#   5. 通过 Installer::uninstallTyped 卸载 hook，验证文件被删除
#
# 由于 PresetRegistry::loadFromWorkspace() 当前不传递 hooks 字段，
# 本测试使用 PHP helper 脚本直接调用 Installer/CheckService 来验证完整流程。

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"

PASS=0
FAIL=0

assert_pass() {
  local desc="$1"
  echo "  PASS: $desc"
  ((PASS++))
}

assert_fail() {
  local desc="$1"
  echo "  FAIL: $desc"
  ((FAIL++))
}

echo "=== Task 7.2: Hook install → check → uninstall (Kiro) ==="
echo ""

# --- Setup: Create temp package root and workspace ---
TMPBASE=$(mktemp -d)
PKG_ROOT="$TMPBASE/pkg"
WORKSPACE="$TMPBASE/workspace"

mkdir -p "$PKG_ROOT/hooks"
mkdir -p "$WORKSPACE"

# Create hook source file
cat > "$PKG_ROOT/hooks/test-e2e-hook.kiro.hook" <<'HOOKEOF'
version: 1
description: Test hook for E2E validation
event: fileEdited
action: askAgent
outputPrompt: Review the changes
HOOKEOF

# Create abilities.yaml in package root
cat > "$PKG_ROOT/abilities.yaml" <<'YAML'
version: "1"
hooks:
  - path: test-e2e-hook
    description: Test E2E hook
    targets:
      kiro: .kiro/hooks/test-e2e-hook.kiro.hook
YAML

echo "Setup: PKG_ROOT=$PKG_ROOT"
echo "Setup: WORKSPACE=$WORKSPACE"
echo ""

# --- Step 1: Install hook via Installer::installTyped ---
echo "--- Step 1: Install hook ---"

INSTALL_OUTPUT=$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
chdir('$WORKSPACE');
\$registry = new AbilityRegistry('$PKG_ROOT/abilities.yaml');
\$installer = new Installer(registry: \$registry, packageRoot: '$PKG_ROOT');
\$items = ['skills'=>[],'rules'=>[],'agents'=>[],'hooks'=>['test-e2e-hook']];
\$result = \$installer->installTyped(\$items, ['kiro']);
foreach (\$result['lines'] as \$line) { echo \$line . PHP_EOL; }
echo 'EXIT_CODE=' . \$result['exit_code'] . PHP_EOL;
" 2>&1)

echo "$INSTALL_OUTPUT"

if [ -f "$WORKSPACE/.kiro/hooks/test-e2e-hook.kiro.hook" ]; then
  assert_pass "Hook file installed at .kiro/hooks/test-e2e-hook.kiro.hook"
else
  assert_fail "Hook file NOT found at .kiro/hooks/test-e2e-hook.kiro.hook"
fi

if echo "$INSTALL_OUTPUT" | grep -qi '\[ok\].*hook.*test-e2e-hook'; then
  assert_pass "Install output contains [ok] marker for hook"
else
  assert_fail "Install output missing [ok] marker for hook"
fi

if echo "$INSTALL_OUTPUT" | grep -q 'EXIT_CODE=0'; then
  assert_pass "Install exit code is 0"
else
  assert_fail "Install exit code is not 0"
fi

echo ""

# --- Step 2: Check hook status (should be ok/unchanged) ---
echo "--- Step 2: Check hook status (expect: ok/unchanged) ---"

CHECK_OUTPUT=$(APM_BASELINE_ROOT="$PKG_ROOT" php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use AiProfileManager\Service\CheckService;
chdir('$WORKSPACE');
\$checker = new CheckService();
\$items = ['skills'=>[],'rules'=>[],'agents'=>[],'hooks'=>['test-e2e-hook']];
\$results = \$checker->checkTyped(\$items, ['kiro']);
foreach (\$checker->renderResults(\$results) as \$line) { echo \$line . PHP_EOL; }
echo 'EXIT_CODE=' . \$checker->evaluateExitCode(\$results) . PHP_EOL;
" 2>&1)

echo "$CHECK_OUTPUT"

if echo "$CHECK_OUTPUT" | grep -q '\[ok\].*hook.*test-e2e-hook.*unchanged'; then
  assert_pass "Check shows hook status as [ok] unchanged"
else
  assert_fail "Check does NOT show hook status as [ok] unchanged"
fi

if echo "$CHECK_OUTPUT" | grep -q 'EXIT_CODE=0'; then
  assert_pass "Check exit code is 0 (no drift)"
else
  assert_fail "Check exit code is not 0"
fi

echo ""

# --- Step 3: Modify installed hook, re-check (should be drift/modified) ---
echo "--- Step 3: Modify hook and re-check (expect: drift/modified) ---"

echo "MODIFIED CONTENT - different from source" > "$WORKSPACE/.kiro/hooks/test-e2e-hook.kiro.hook"

CHECK_DRIFT_OUTPUT=$(APM_BASELINE_ROOT="$PKG_ROOT" php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use AiProfileManager\Service\CheckService;
chdir('$WORKSPACE');
\$checker = new CheckService();
\$items = ['skills'=>[],'rules'=>[],'agents'=>[],'hooks'=>['test-e2e-hook']];
\$results = \$checker->checkTyped(\$items, ['kiro']);
foreach (\$checker->renderResults(\$results) as \$line) { echo \$line . PHP_EOL; }
echo 'EXIT_CODE=' . \$checker->evaluateExitCode(\$results) . PHP_EOL;
" 2>&1)

echo "$CHECK_DRIFT_OUTPUT"

if echo "$CHECK_DRIFT_OUTPUT" | grep -q '\[drift\].*hook.*test-e2e-hook.*modified'; then
  assert_pass "Check shows hook status as [drift] modified after modification"
else
  assert_fail "Check does NOT show hook status as [drift] modified"
fi

if echo "$CHECK_DRIFT_OUTPUT" | grep -q 'EXIT_CODE=2'; then
  assert_pass "Check exit code is 2 (drift detected)"
else
  assert_fail "Check exit code is not 2 after drift"
fi

echo ""

# --- Step 4: Uninstall hook (with force since drift exists) ---
echo "--- Step 4: Uninstall hook ---"

UNINSTALL_OUTPUT=$(APM_BASELINE_ROOT="$PKG_ROOT" php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
chdir('$WORKSPACE');
\$registry = new AbilityRegistry('$PKG_ROOT/abilities.yaml');
\$installer = new Installer(registry: \$registry, packageRoot: '$PKG_ROOT');
\$items = ['skills'=>[],'rules'=>[],'agents'=>[],'hooks'=>['test-e2e-hook']];
\$result = \$installer->uninstallTyped(\$items, ['kiro'], true);
foreach (\$result['lines'] as \$line) { echo \$line . PHP_EOL; }
echo 'EXIT_CODE=' . \$result['exit_code'] . PHP_EOL;
" 2>&1)

echo "$UNINSTALL_OUTPUT"

if [ ! -f "$WORKSPACE/.kiro/hooks/test-e2e-hook.kiro.hook" ]; then
  assert_pass "Hook file deleted after uninstall"
else
  assert_fail "Hook file still exists after uninstall"
fi

if echo "$UNINSTALL_OUTPUT" | grep -qi '\[ok\].*Uninstalled hook.*test-e2e-hook'; then
  assert_pass "Uninstall output contains [ok] marker"
else
  assert_fail "Uninstall output missing [ok] marker"
fi

if echo "$UNINSTALL_OUTPUT" | grep -q 'EXIT_CODE=0'; then
  assert_pass "Uninstall exit code is 0"
else
  assert_fail "Uninstall exit code is not 0"
fi

echo ""

# --- Cleanup ---
rm -rf "$TMPBASE"

# --- Summary ---
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 7.2: ALL PASS"
  exit 0
else
  echo "❌ Task 7.2: FAILED ($FAIL failures)"
  exit 1
fi
