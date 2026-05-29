<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Typed commands lifecycle — skill:install → skill:check → skill:uninstall.
 * Same for rule:* and agent:* commands.
 */
final class TypedCommandsLifecycleTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSkillSource('graphify', "# Graphify\nGraph knowledge.\n");
        $this->createRuleSource('branch-overview', 'git', 'cursor', "branch rules\n");
        $this->createRuleSource('branch-overview', 'git', 'kiro', "branch rules kiro\n");
        $this->createAgentSource('code-reviewer', 'cursor', "# Code Reviewer\n");
        $this->createAgentSource('code-reviewer', 'kiro', "# Code Reviewer Kiro\n");

        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            '      kiro: .kiro/skills/graphify',
            'rules:',
            '  - path: branch-overview',
            '    description: Branch overview',
            '    targets:',
            '      cursor: .cursor/rules/git/branch-overview.mdc',
            '      kiro: .kiro/steering/git/branch-overview.md',
            'agents:',
            '  - path: code-reviewer',
            '    description: Code reviewer',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
            '      kiro: .kiro/agents/code-reviewer.md',
        ]) . "\n");
    }

    // ─── skill:install → skill:check → skill:uninstall ───────────────

    public function testSkillLifecycleOnCursor(): void
    {
        // Install
        $r = $this->apm(['skill:install', 'graphify', '-t', 'cursor']);
        self::assertSame(0, $r['exit'], "Install failed: {$r['stderr']}");
        self::assertStringContainsString('Installed skill graphify', $r['stdout']);
        $this->assertFileInWorkspace('.cursor/skills/graphify/SKILL.md');

        // Check — should be unchanged
        $r = $this->apm(['skill:check', 'graphify', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('[ok]', $r['stdout']);

        // Uninstall
        $r = $this->apm(['skill:uninstall', 'graphify', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('Uninstalled skill graphify', $r['stdout']);
        $this->assertFileNotInWorkspace('.cursor/skills/graphify/SKILL.md');
    }

    public function testSkillLifecycleOnKiro(): void
    {
        $r = $this->apm(['skill:install', 'graphify', '-t', 'kiro']);
        self::assertSame(0, $r['exit'], "Install failed: {$r['stderr']}");
        $this->assertFileInWorkspace('.kiro/skills/graphify/SKILL.md');

        $r = $this->apm(['skill:check', 'graphify', '-t', 'kiro']);
        self::assertSame(0, $r['exit']);

        $r = $this->apm(['skill:uninstall', 'graphify', '-t', 'kiro']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.kiro/skills/graphify/SKILL.md');
    }

    // ─── rule:install → rule:check → rule:uninstall ──────────────────

    public function testRuleLifecycleOnCursor(): void
    {
        $r = $this->apm(['rule:install', 'branch-overview', '-t', 'cursor']);
        self::assertSame(0, $r['exit'], "Install failed: {$r['stderr']}");
        self::assertStringContainsString('Installed rule branch-overview', $r['stdout']);
        $this->assertFileInWorkspace('.cursor/rules/git/branch-overview.mdc');

        $r = $this->apm(['rule:check', 'branch-overview', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('[ok]', $r['stdout']);

        $r = $this->apm(['rule:uninstall', 'branch-overview', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.cursor/rules/git/branch-overview.mdc');
    }

    public function testRuleLifecycleOnKiro(): void
    {
        $r = $this->apm(['rule:install', 'branch-overview', '-t', 'kiro']);
        self::assertSame(0, $r['exit'], "Install failed: {$r['stderr']}");
        $this->assertFileInWorkspace('.kiro/steering/git/branch-overview.md');

        $r = $this->apm(['rule:check', 'branch-overview', '-t', 'kiro']);
        self::assertSame(0, $r['exit']);

        $r = $this->apm(['rule:uninstall', 'branch-overview', '-t', 'kiro']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.kiro/steering/git/branch-overview.md');
    }

    // ─── agent:install → agent:check → agent:uninstall ───────────────

    public function testAgentLifecycleOnCursor(): void
    {
        $r = $this->apm(['agent:install', 'code-reviewer', '-t', 'cursor']);
        self::assertSame(0, $r['exit'], "Install failed: {$r['stderr']}");
        self::assertStringContainsString('Installed agent code-reviewer', $r['stdout']);
        $this->assertFileInWorkspace('.cursor/agents/code-reviewer.md');

        $r = $this->apm(['agent:check', 'code-reviewer', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('[ok]', $r['stdout']);

        $r = $this->apm(['agent:uninstall', 'code-reviewer', '-t', 'cursor']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.cursor/agents/code-reviewer.md');
    }

    // ─── Drift detection on typed uninstall ──────────────────────────

    public function testSkillUninstallBlockedByDrift(): void
    {
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);

        // Modify
        file_put_contents(
            $this->workspace . '/.cursor/skills/graphify/SKILL.md',
            "# User modified\n",
        );

        // Uninstall without force
        $r = $this->apm(['skill:uninstall', 'graphify', '-t', 'cursor']);
        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('Re-run with --force', $r['stdout']);

        // Uninstall with force
        $r = $this->apm(['skill:uninstall', 'graphify', '-t', 'cursor', '--force']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.cursor/skills/graphify/SKILL.md');
    }

    // ─── Check reports missing when not installed ─────────────────────

    public function testSkillCheckReportsMissingWhenNotInstalled(): void
    {
        $r = $this->apm(['skill:check', 'graphify', '-t', 'cursor']);

        self::assertSame(2, $r['exit']);
        self::assertStringContainsString('[miss]', $r['stdout']);
    }
}
