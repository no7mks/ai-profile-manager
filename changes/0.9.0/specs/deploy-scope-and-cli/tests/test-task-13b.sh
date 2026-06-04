#!/usr/bin/env bash
# E2E Test: Task 13.2 — show dual scope, cleanup, update non-global rejection
# Ref: Requirement 7, 9, 10
#
# Isolated HOME + workspace + APM_PACKAGE_ROOT / APM_BASELINE_ROOT (see EndToEndTestCase).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
VENDOR_AUTOLOAD="$PROJECT_ROOT/vendor/autoload.php"

if [[ ! -f "$VENDOR_AUTOLOAD" ]]; then
  echo "ERROR: run composer install in $PROJECT_ROOT first" >&2
  exit 1
fi

PASS=0
FAIL=0
BASE="$(mktemp -d "${TMPDIR:-/tmp}/apm-e2e-13b.XXXXXX")"
HOME_DIR="$BASE/home"
WORKSPACE="$BASE/workspace"
PKG="$BASE/pkg"
LOG="$BASE/e2e-13b.log"
WRAPPER="$BASE/apm-e2e.php"

cleanup() {
  rm -rf "$BASE"
}
trap cleanup EXIT

pass() {
  echo "  PASS: $1" | tee -a "$LOG"
  PASS=$((PASS + 1))
}

fail() {
  echo "  FAIL: $1" | tee -a "$LOG"
  FAIL=$((FAIL + 1))
}

mkdir -p "$HOME_DIR" "$WORKSPACE" "$PKG/.cursor/skills/demo-skill" "$PKG/.cursor/rules/git"
touch "$LOG"

# ─── Package fixture (minimal abilities.yaml + sources) ─────────────────────

cat >"$PKG/abilities.yaml" <<'YAML'
version: "1"
skills:
  - path: demo-skill
    description: Demo skill for cleanup E2E
    targets:
      cursor: .cursor/skills/demo-skill
rules:
  - path: dual-rule
    description: Rule installable in user and project
    scopes:
      - user
      - project
    targets:
      cursor: .cursor/rules/git/dual-rule.mdc
  - path: git:demo-rule
    description: Demo rule for cleanup E2E
    targets:
      cursor: .cursor/rules/git/demo-rule.mdc
agents: []
hooks: []
YAML

echo "demo skill baseline" >"$PKG/.cursor/skills/demo-skill/SKILL.md"
echo "dual rule baseline" >"$PKG/.cursor/rules/git/dual-rule.mdc"
echo "demo rule baseline" >"$PKG/.cursor/rules/git/demo-rule.mdc"

# ─── CLI wrapper (override package root via env) ─────────────────────────────

cat >"$WRAPPER" <<PHP
#!/usr/bin/env php
<?php
declare(strict_types=1);

require '$VENDOR_AUTOLOAD';

use AiProfileManager\Core\Application;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

\$packageRoot = getenv('APM_PACKAGE_ROOT') ?: __DIR__;
\$registryPath = \$packageRoot . '/abilities.yaml';
\$registry = file_exists(\$registryPath) ? new AbilityRegistry(\$registryPath) : null;
\$installerArgs = ['packageRoot' => \$packageRoot];
if (\$registry !== null) {
    \$installerArgs['registry'] = \$registry;
}
\$installer = new Installer(...\$installerArgs);
\$presetRegistry = \$registry !== null ? new PresetRegistry(\$registry) : null;
\$app = Application::createSymfonyApplication(\$installer, \$presetRegistry);
exit(\$app->run(new ArgvInput(\$argv), new ConsoleOutput()));
PHP
chmod +x "$WRAPPER"

apm() {
  (
    export HOME="$HOME_DIR"
    export APM_PACKAGE_ROOT="$PKG"
    export APM_BASELINE_ROOT="$PKG"
    cd "$WORKSPACE"
    php "$WRAPPER" "$@" 2>&1
  )
}

# ─── Test 1: show merged dual-scope view (Req 9) ───────────────────────────

echo "=== Task 13.2: show dual scope, cleanup, update ===" | tee -a "$LOG"
echo "" | tee -a "$LOG"
echo "[Test 1] show merged user+project view with dual-scope warning" | tee -a "$LOG"

EXIT_CODE=0
OUTPUT="$(apm rule:install dual-rule -t cursor)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]]; then
  pass "rule:install dual-rule (project) exit 0"
else
  fail "rule:install dual-rule (project) exit $EXIT_CODE"
fi

EXIT_CODE=0
OUTPUT="$(apm rule:install dual-rule -t cursor --scope=user)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]]; then
  pass "rule:install dual-rule (user) exit 0"
else
  fail "rule:install dual-rule (user) exit $EXIT_CODE"
fi

EXIT_CODE=0
OUTPUT="$(apm show -t cursor --type rule)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]]; then
  pass "show (merged) exit 0"
else
  fail "show (merged) exit $EXIT_CODE"
fi

if echo "$OUTPUT" | grep -q 'rule:dual-rule'; then
  pass "merged show lists rule:dual-rule"
else
  fail "merged show missing rule:dual-rule"
fi

if echo "$OUTPUT" | grep -q '\[warn\] installed in both user and project'; then
  pass "merged show contains dual-scope warning"
else
  fail "merged show missing dual-scope warning"
fi

if echo "$OUTPUT" | grep -q '(user+project)'; then
  pass "merged show contains (user+project) scope label"
else
  fail "merged show missing (user+project) label"
fi

# ─── Test 2: show --scope filters (Req 9) ──────────────────────────────────

echo "" | tee -a "$LOG"
echo "[Test 2] show --scope=project|user filters" | tee -a "$LOG"

EXIT_CODE=0
OUTPUT="$(apm show -t cursor --type rule --scope=user)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]] && echo "$OUTPUT" | grep -q 'rule:dual-rule  installed (user)'; then
  pass "show --scope=user shows installed (user)"
else
  fail "show --scope=user output unexpected"
fi

if echo "$OUTPUT" | grep -q '\[warn\] installed in both user and project'; then
  fail "show --scope=user should not include dual-scope warning"
else
  pass "show --scope=user omits dual-scope warning"
fi

EXIT_CODE=0
OUTPUT="$(apm show -t cursor --type rule --scope=project)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]] && echo "$OUTPUT" | grep -q 'rule:dual-rule  installed (project)'; then
  pass "show --scope=project shows installed (project)"
else
  fail "show --scope=project output unexpected"
fi

# ─── Test 3: cleanup project-only (Req 7) ──────────────────────────────────

echo "" | tee -a "$LOG"
echo "[Test 3] cleanup removes project scope, preserves user + docs" | tee -a "$LOG"

EXIT_CODE=0
OUTPUT="$(apm skill:install demo-skill -t cursor)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]]; then
  pass "skill:install demo-skill (project) exit 0"
else
  fail "skill:install demo-skill exit $EXIT_CODE"
fi

mkdir -p "$HOME_DIR/.cursor/skills/demo-skill" "$HOME_DIR/.cursor/rules/git"
echo "user skill copy" >"$HOME_DIR/.cursor/skills/demo-skill/SKILL.md"
echo "user rule copy" >"$HOME_DIR/.cursor/rules/git/demo-rule.mdc"

mkdir -p "$WORKSPACE/docs/state" "$WORKSPACE/docs/manual"
echo "# Project" >"$WORKSPACE/PROJECT.md"
echo "# Agents" >"$WORKSPACE/AGENTS.md"
echo "# State" >"$WORKSPACE/docs/state/architecture.md"
echo "# Manual" >"$WORKSPACE/docs/manual/usage.md"

if [[ -f "$WORKSPACE/.cursor/skills/demo-skill/SKILL.md" ]]; then
  pass "project skill present before cleanup"
else
  fail "project skill missing before cleanup"
fi

EXIT_CODE=0
OUTPUT="$(apm cleanup)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -eq 0 ]]; then
  pass "cleanup exit 0"
else
  fail "cleanup exit $EXIT_CODE"
fi

if [[ ! -f "$WORKSPACE/.cursor/skills/demo-skill/SKILL.md" ]]; then
  pass "cleanup removed project skill"
else
  fail "cleanup left project skill on disk"
fi

if [[ ! -f "$WORKSPACE/.cursor/rules/git/demo-rule.mdc" ]]; then
  pass "cleanup removed project rule from registry enumeration"
else
  fail "cleanup left project demo-rule on disk"
fi

if [[ -f "$HOME_DIR/.cursor/skills/demo-skill/SKILL.md" ]] \
  && [[ "$(cat "$HOME_DIR/.cursor/skills/demo-skill/SKILL.md")" == "user skill copy" ]]; then
  pass "cleanup preserved user-scope skill"
else
  fail "cleanup altered user-scope skill"
fi

if [[ -f "$HOME_DIR/.cursor/rules/git/demo-rule.mdc" ]] \
  && [[ "$(cat "$HOME_DIR/.cursor/rules/git/demo-rule.mdc")" == "user rule copy" ]]; then
  pass "cleanup preserved user-scope rule seed"
else
  fail "cleanup altered user-scope rule seed"
fi

if [[ -f "$WORKSPACE/PROJECT.md" && -f "$WORKSPACE/AGENTS.md" \
  && -f "$WORKSPACE/docs/state/architecture.md" && -f "$WORKSPACE/docs/manual/usage.md" ]]; then
  pass "cleanup did not remove scaffold/docs"
else
  fail "cleanup removed scaffold or docs"
fi

if echo "$OUTPUT" | grep -qi '/apm init'; then
  pass "cleanup mentions /apm init reinstall path"
else
  fail "cleanup missing /apm init guidance"
fi

# ─── Test 4: update rejects non-global invocation (Req 10) ─────────────────

echo "" | tee -a "$LOG"
echo "[Test 4] update rejects non-global apm invocation" | tee -a "$LOG"

EXIT_CODE=0
OUTPUT="$(apm update)" || EXIT_CODE=$?
echo "$OUTPUT" >>"$LOG"

if [[ "$EXIT_CODE" -ne 0 ]]; then
  pass "update exit non-zero ($EXIT_CODE)"
else
  fail "update exit 0 from non-global wrapper (expected failure)"
fi

if echo "$OUTPUT" | grep -qi 'global apm'; then
  pass "update message mentions global apm"
else
  fail "update message missing global apm guidance"
fi

# ─── Summary ───────────────────────────────────────────────────────────────

echo "" | tee -a "$LOG"
echo "--- Summary ---" | tee -a "$LOG"
echo "PASS: $PASS / $((PASS + FAIL))" | tee -a "$LOG"
echo "FAIL: $FAIL / $((PASS + FAIL))" | tee -a "$LOG"
echo "Log: $LOG" | tee -a "$LOG"
echo "" | tee -a "$LOG"

if [[ "$FAIL" -eq 0 ]]; then
  echo "✅ Task 13.2: ALL PASS" | tee -a "$LOG"
  exit 0
else
  echo "❌ Task 13.2: FAILED ($FAIL failures)" | tee -a "$LOG"
  exit 1
fi
