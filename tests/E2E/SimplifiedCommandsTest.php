<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: show/update/cleanup simplified behavior (no scope labels, no legacy wording).
 *
 * Validates: Requirements 5, 6, 7
 */
final class SimplifiedCommandsTest extends EndToEndTestCase
{
    // ─── Show: no scope labels (Requirement 5, AC 1-3) ─────────────

    public function testShowOutputContainsNoScopeLabels(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");
        $this->createRuleSource('branch-overview', 'git', 'cursor', "rule\n");
        $this->createAgentSource('code-reviewer', 'cursor', "# Agent\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'rules:',
            '  - path: branch-overview',
            '    description: Branch overview',
            '    targets:',
            '      cursor: .cursor/rules/git/branch-overview.mdc',
            'agents:',
            '  - path: code-reviewer',
            '    description: Code reviewer',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
        ]) . "\n");

        // Install one ability to have mixed statuses
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);

        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");

        $output = $r['stdout'];

        // Verify format: {type}:{name}  {status}   {targets}
        self::assertStringContainsString('skill:graphify  installed', $output);
        self::assertStringContainsString('rule:branch-overview  not installed', $output);
        self::assertStringContainsString('agent:code-reviewer  not installed', $output);

        // Verify NO scope labels
        self::assertStringNotContainsString('(user)', $output);
        self::assertStringNotContainsString('(project)', $output);
        self::assertStringNotContainsString('(user+project)', $output);

        // Verify no dual-scope warning
        self::assertStringNotContainsString('[warn] installed in both', $output);
        self::assertStringNotContainsString('installed in both user and project', $output);
    }

    // ─── Show: --type filter (Requirement 5, AC 4) ─────────────────

    public function testShowWithTypeFilterShowsOnlyMatchedType(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");
        $this->createSkillSource('gitflow', "# Gitflow\n");
        $this->createRuleSource('branch-overview', 'git', 'cursor', "rule\n");
        $this->createAgentSource('code-reviewer', 'cursor', "# Agent\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            '  - path: gitflow',
            '    description: Gitflow',
            '    targets:',
            '      cursor: .cursor/skills/gitflow',
            'rules:',
            '  - path: branch-overview',
            '    description: Branch overview',
            '    targets:',
            '      cursor: .cursor/rules/git/branch-overview.mdc',
            'agents:',
            '  - path: code-reviewer',
            '    description: Code reviewer',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
        ]) . "\n");

        $r = $this->apm(['show', '-t', 'cursor', '--type', 'skill']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");

        $output = $r['stdout'];

        // Only skill lines should appear
        self::assertStringContainsString('skill:graphify', $output);
        self::assertStringContainsString('skill:gitflow', $output);
        self::assertStringNotContainsString('rule:', $output);
        self::assertStringNotContainsString('agent:', $output);
    }

    // ─── Show: empty registry (Requirement 5, AC 5) ────────────────

    public function testShowEmptyRegistryReturnsSuccessWithEmptyOutput(): void
    {
        $this->createAbilitiesYaml(implode("\n", [
            'version: "1"',
            'skills: []',
            'rules: []',
            'agents: []',
        ]) . "\n");

        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        self::assertSame('', trim($r['stdout']));
    }

    // ─── Update: no scope labels (Requirement 6) ───────────────────

    public function testUpdateRejectionOutputHasNoScopeLabels(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");
        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'rules: []',
            'agents: []',
        ]) . "\n");

        // update requires global invocation; non-global invocation is rejected
        $r = $this->apm(['update']);

        // Expected: exit 1 (rejected as non-global)
        self::assertSame(1, $r['exit']);

        $output = $r['stdout'] . $r['stderr'];

        // Verify the rejection message has no scope labels
        self::assertStringNotContainsString('(user)', $output);
        self::assertStringNotContainsString('(project)', $output);
        self::assertStringNotContainsString('(user+project)', $output);
        self::assertStringNotContainsString('user scope', $output);
        self::assertStringNotContainsString('user-scope', $output);
    }

    // ─── Cleanup: no legacy wording (Requirement 7, AC 1) ──────────

    public function testCleanupOutputHasNoLegacyWording(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");
        $this->createRuleSource('branch-overview', 'git', 'cursor', "rule\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'rules:',
            '  - path: branch-overview',
            '    description: Branch overview',
            '    targets:',
            '      cursor: .cursor/rules/git/branch-overview.mdc',
            'agents: []',
        ]) . "\n");

        // Install abilities first
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);
        $this->apm(['rule:install', 'branch-overview', '-t', 'cursor']);

        // Run cleanup
        $r = $this->apm(['cleanup']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");

        $output = $r['stdout'];

        // AC 1: No legacy wording
        self::assertStringNotContainsString('user-scope', $output);
        self::assertStringNotContainsString('user scope', $output);
        self::assertStringNotContainsString('global-setup', $output);
    }

    // ─── Cleanup: success shows init guidance (Requirement 7, AC 2) ─

    public function testCleanupSuccessShowsInitGuidance(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'rules: []',
            'agents: []',
        ]) . "\n");

        // Install then cleanup
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);

        $r = $this->apm(['cleanup']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");

        $output = $r['stdout'];

        // AC 2: Contains /apm init guidance on success
        self::assertMatchesRegularExpression('#/apm\s+init#i', $output);
        self::assertStringContainsString('To reinstall project abilities', $output);
    }

    // ─── Cleanup: empty cleanup still succeeds (edge case) ─────────

    public function testCleanupWithNothingInstalledSucceeds(): void
    {
        $this->createSkillSource('graphify', "# Graphify\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'rules: []',
            'agents: []',
        ]) . "\n");

        // Don't install anything — just run cleanup
        $r = $this->apm(['cleanup']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");

        $output = $r['stdout'];

        // No legacy wording even when nothing to uninstall
        self::assertStringNotContainsString('user-scope', $output);
        self::assertStringNotContainsString('user scope', $output);
        self::assertStringNotContainsString('global-setup', $output);
    }
}
