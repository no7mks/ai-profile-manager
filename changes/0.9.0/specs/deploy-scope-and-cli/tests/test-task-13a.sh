#!/usr/bin/env bash
# E2E Test: Task 13.1 — global-setup → bootstrap → typed add
# Ref: Requirement 5, 6, 8
#
# Uses isolated HOME and workspace; does not touch real ~/.cursor.
# Runs: php <repo>/bin/apm with APM_PACKAGE_ROOT and APM_BASELINE_ROOT = repo root.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
APM="php $PROJECT_ROOT/bin/apm"

PASS=0
FAIL=0

assert_pass() {
  echo "  PASS: $1"
  ((PASS++))
}

assert_fail() {
  echo "  FAIL: $1"
  ((FAIL++))
}

# Run apm in workspace (or override cwd) with isolated HOME and package env.
run_apm() {
  local cwd="${1:-$WORKSPACE}"
  shift
  (
    cd "$cwd" || exit 1
    export HOME="$FAKE_HOME"
    export APM_PACKAGE_ROOT="$PROJECT_ROOT"
    export APM_BASELINE_ROOT="$PROJECT_ROOT"
    $APM "$@" 2>&1
  )
}

run_apm_capture() {
  local cwd="${1:-$WORKSPACE}"
  shift
  local out
  out=$(run_apm "$cwd" "$@") && echo "$out" && return 0 || { echo "$out"; return 1; }
}

# --- Setup ---
TMPBASE=$(mktemp -d)
FAKE_HOME="$TMPBASE/home"
WORKSPACE="$TMPBASE/workspace"
ISOLATED_CWD="$TMPBASE/isolated-cwd"

mkdir -p "$FAKE_HOME" "$WORKSPACE" "$ISOLATED_CWD"

cleanup() {
  rm -rf "$TMPBASE"
}
trap cleanup EXIT

echo "=== Task 13.1: global-setup → bootstrap → typed add ==="
echo ""
echo "  PROJECT_ROOT=$PROJECT_ROOT"
echo "  FAKE_HOME=$FAKE_HOME"
echo "  WORKSPACE=$WORKSPACE"
echo ""

# ─── Requirement 5: global-setup ───────────────────────────────────
echo "--- Step 1: global-setup (user scope, -t cursor) ---"

GS_OUTPUT=$(run_apm "$ISOLATED_CWD" global-setup -t cursor) && GS_EXIT=0 || GS_EXIT=$?
echo "$GS_OUTPUT"
echo ""

if [ "$GS_EXIT" -eq 0 ]; then
  assert_pass "global-setup exit code = 0"
else
  assert_fail "global-setup exit code = $GS_EXIT (expected 0)"
fi

if echo "$GS_OUTPUT" | grep -qi '/apm init'; then
  assert_pass "global-setup success output prompts /apm init"
else
  assert_fail "global-setup output missing /apm init prompt"
fi

if [ -f "$FAKE_HOME/.cursor/skills/apm/SKILL.md" ]; then
  assert_pass "skill:apm installed under fake HOME (user scope)"
else
  assert_fail "skill:apm NOT found at \$HOME/.cursor/skills/apm/SKILL.md"
fi

if [ -f "$FAKE_HOME/.cursor/rules/doc/writing-conventions.mdc" ]; then
  assert_pass "global-setup rule installed under fake HOME"
else
  assert_fail "rule doc:writing-conventions NOT found under fake HOME"
fi

if [ -f "$WORKSPACE/.cursor/skills/apm/SKILL.md" ]; then
  assert_fail "global-setup incorrectly wrote skill:apm into workspace"
else
  assert_pass "global-setup did not install abilities into workspace"
fi

echo ""
echo "--- Step 2: global-setup idempotent (second run) ---"

GS2_OUTPUT=$(run_apm "$WORKSPACE" global-setup -t cursor) && GS2_EXIT=0 || GS2_EXIT=$?
echo "$GS2_OUTPUT"
echo ""

if [ "$GS2_EXIT" -eq 0 ]; then
  assert_pass "second global-setup exit code = 0 (idempotent)"
else
  assert_fail "second global-setup exit code = $GS2_EXIT"
fi

if echo "$GS2_OUTPUT" | grep -q 'already up to date'; then
  assert_pass "second global-setup reports already up to date"
else
  assert_fail "second global-setup missing 'already up to date' idempotency marker"
fi

echo ""
echo "--- Step 3: global-setup rejects --scope ---"

SCOPE_OUTPUT=$(run_apm "$WORKSPACE" global-setup --scope=user -t cursor 2>&1) && SCOPE_EXIT=0 || SCOPE_EXIT=$?
echo "$SCOPE_OUTPUT"
echo ""

if echo "$SCOPE_OUTPUT" | grep -qi 'scope'; then
  assert_pass "global-setup --scope rejected with error"
else
  assert_fail "global-setup --scope did not produce expected rejection"
fi

# ─── Requirement 6: bootstrap (scaffold only) ──────────────────────
echo ""
echo "--- Step 4: bootstrap (scaffold only) ---"

BS_OUTPUT=$(run_apm "$WORKSPACE" bootstrap -t cursor) && BS_EXIT=0 || BS_EXIT=$?
echo "$BS_OUTPUT"
echo ""

if [ "$BS_EXIT" -eq 0 ]; then
  assert_pass "bootstrap exit code = 0"
else
  assert_fail "bootstrap exit code = $BS_EXIT (expected 0)"
fi

for f in docs/README.md issues/README.md AGENTS.md; do
  if [ -f "$WORKSPACE/$f" ]; then
    assert_pass "bootstrap created workspace/$f"
  else
    assert_fail "bootstrap missing workspace/$f"
  fi
done

if echo "$BS_OUTPUT" | grep -qi 'Installing scaffold'; then
  assert_pass "bootstrap output mentions scaffold"
else
  assert_fail "bootstrap output missing scaffold marker"
fi

if echo "$BS_OUTPUT" | grep -qiE 'Installing (default )?preset|Installed skill'; then
  assert_fail "bootstrap output suggests ability/preset install"
else
  assert_pass "bootstrap did not install abilities or presets"
fi

if [ -f "$WORKSPACE/.cursor/skills/graphify/SKILL.md" ]; then
  assert_fail "bootstrap incorrectly installed graphify skill"
else
  assert_pass "bootstrap left graphify skill absent from workspace"
fi

# ─── Requirement 8: bare install/add + typed add ─────────────────
echo ""
echo "--- Step 5: bare install / add fail with guidance ---"

BARE_INSTALL_OUTPUT=$(run_apm "$WORKSPACE" install) && BARE_INSTALL_EXIT=0 || BARE_INSTALL_EXIT=$?
echo "$BARE_INSTALL_OUTPUT"
echo ""

if [ "$BARE_INSTALL_EXIT" -ne 0 ]; then
  assert_pass "bare 'apm install' exits non-zero"
else
  assert_fail "bare 'apm install' should fail"
fi

if echo "$BARE_INSTALL_OUTPUT" | grep -q 'apm global-setup' \
  && echo "$BARE_INSTALL_OUTPUT" | grep -q 'apm bootstrap' \
  && echo "$BARE_INSTALL_OUTPUT" | grep -q 'apm add skill'; then
  assert_pass "bare install guidance mentions global-setup, bootstrap, typed add"
else
  assert_fail "bare install missing expected guidance text"
fi

BARE_ADD_OUTPUT=$(run_apm "$WORKSPACE" add) && BARE_ADD_EXIT=0 || BARE_ADD_EXIT=$?
echo "$BARE_ADD_OUTPUT"
echo ""

if [ "$BARE_ADD_EXIT" -ne 0 ]; then
  assert_pass "bare 'apm add' (install alias) exits non-zero"
else
  assert_fail "bare 'apm add' should fail"
fi

# Use a skill name that is not a preset (graphify is both skill and preset name).
UNTYPED_OUTPUT=$(run_apm "$WORKSPACE" install apm -t cursor) && UNTYPED_EXIT=0 || UNTYPED_EXIT=$?
echo "$UNTYPED_OUTPUT"
echo ""

if [ "$UNTYPED_EXIT" -ne 0 ]; then
  assert_pass "untyped 'apm install apm' exits non-zero"
else
  assert_fail "untyped install apm should fail"
fi

if echo "$UNTYPED_OUTPUT" | grep -q 'apm add skill'; then
  assert_pass "untyped install directs to apm add skill"
else
  assert_fail "untyped install missing typed-add guidance"
fi

if [ -f "$WORKSPACE/.cursor/skills/apm/SKILL.md" ]; then
  assert_fail "untyped install should not write apm into workspace (zero writes)"
else
  assert_pass "untyped install produced zero writes for apm in workspace"
fi

echo ""
echo "--- Step 6: typed add (skill:install) ---"

SKILL_OUTPUT=$(run_apm "$WORKSPACE" skill:install graphify -t cursor) && SKILL_EXIT=0 || SKILL_EXIT=$?
echo "$SKILL_OUTPUT"
echo ""

if [ "$SKILL_EXIT" -eq 0 ]; then
  assert_pass "skill:install graphify exit code = 0"
else
  assert_fail "skill:install graphify exit code = $SKILL_EXIT"
fi

if [ -f "$WORKSPACE/.cursor/skills/graphify/SKILL.md" ]; then
  assert_pass "graphify skill installed in workspace (project scope)"
else
  assert_fail "graphify skill NOT found in workspace after skill:install"
fi

if [ -f "$FAKE_HOME/.cursor/skills/graphify/SKILL.md" ]; then
  assert_fail "skill:install graphify incorrectly installed to user HOME"
else
  assert_pass "graphify not installed to fake HOME (default project scope)"
fi

echo ""
echo "--- Step 7: typed add synonym (skill:add) ---"

# Remove graphify to verify skill:add reinstalls
rm -rf "$WORKSPACE/.cursor/skills/graphify"

ADD_OUTPUT=$(run_apm "$WORKSPACE" skill:add graphify -t cursor) && ADD_EXIT=0 || ADD_EXIT=$?
echo "$ADD_OUTPUT"
echo ""

if [ "$ADD_EXIT" -eq 0 ]; then
  assert_pass "skill:add graphify exit code = 0"
else
  assert_fail "skill:add graphify exit code = $ADD_EXIT"
fi

if [ -f "$WORKSPACE/.cursor/skills/graphify/SKILL.md" ]; then
  assert_pass "skill:add reinstalled graphify in workspace"
else
  assert_fail "skill:add did not install graphify in workspace"
fi

# ─── Summary ─────────────────────────────────────────────────────
echo ""
echo "--- Summary ---"
echo "PASS: $PASS / $((PASS + FAIL))"
echo "FAIL: $FAIL / $((PASS + FAIL))"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo "✅ Task 13.1: ALL PASS"
  exit 0
else
  echo "❌ Task 13.1: FAILED ($FAIL failures)"
  exit 1
fi
