<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Full preset lifecycle — install → check → show → uninstall → check.
 *
 * Validates the complete user journey through a real CLI process invocation.
 */
final class PresetLifecycleTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up a preset with a skill, rule, and agent
        $this->createSkillSource('demo-skill', "# Demo Skill\nContent here.\n");
        $this->createRuleSource('demo-rule', 'git', 'cursor', "---\nDemo rule content\n");
        $this->createRuleSource('demo-rule', 'git', 'kiro', "---\nDemo rule kiro\n");
        $this->createAgentSource('demo-agent', 'cursor', "# Demo Agent\n");
        $this->createAgentSource('demo-agent', 'kiro', "# Demo Agent Kiro\n");

        // Create abilities.yaml first (skills/rules/agents), then append presets section
        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: demo-skill',
            '    description: Demo skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
            '      kiro: .kiro/skills/demo-skill',
            'rules:',
            '  - path: demo-rule',
            '    description: Demo rule',
            '    targets:',
            '      cursor: .cursor/rules/git/demo-rule.mdc',
            '      kiro: .kiro/steering/git/demo-rule.md',
            'agents:',
            '  - path: demo-agent',
            '    description: Demo agent',
            '    targets:',
            '      cursor: .cursor/agents/demo-agent.md',
            '      kiro: .kiro/agents/demo-agent.md',
        ]) . "\n");

        $this->createPresets([
            'demo' => [
                'skills' => ['demo-skill'],
                'rules' => ['demo-rule'],
                'agents' => ['demo-agent'],
            ],
        ]);
    }

    // ─── install → verify files ──────────────────────────────────────

    public function testInstallPresetCreatesAllFilesOnCursor(): void
    {
        $r = $this->apm(['install', 'demo', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        self::assertStringContainsString('Preset: demo', $r['stdout']);
        self::assertStringContainsString('Installed skill demo-skill', $r['stdout']);
        self::assertStringContainsString('Installed rule demo-rule', $r['stdout']);
        self::assertStringContainsString('Installed agent demo-agent', $r['stdout']);

        $this->assertFileInWorkspace('.cursor/skills/demo-skill/SKILL.md');
        $this->assertFileInWorkspace('.cursor/rules/git/demo-rule.mdc');
        $this->assertFileInWorkspace('.cursor/agents/demo-agent.md');

        self::assertSame("# Demo Skill\nContent here.\n", $this->readWorkspaceFile('.cursor/skills/demo-skill/SKILL.md'));
    }

    public function testInstallPresetCreatesAllFilesOnKiro(): void
    {
        $r = $this->apm(['install', 'demo', '-t', 'kiro']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        $this->assertFileInWorkspace('.kiro/skills/demo-skill/SKILL.md');
        $this->assertFileInWorkspace('.kiro/steering/git/demo-rule.md');
        $this->assertFileInWorkspace('.kiro/agents/demo-agent.md');
    }

    // ─── install → check (unchanged) ────────────────────────────────

    public function testCheckReportsUnchangedAfterFreshInstall(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);
        $r = $this->apm(['check', 'demo', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        self::assertStringContainsString('Preset: demo', $r['stdout']);
        self::assertStringContainsString('[ok]', $r['stdout']);
        self::assertStringNotContainsString('[drift]', $r['stdout']);
        self::assertStringNotContainsString('[miss]', $r['stdout']);
    }

    // ─── install → modify → check (drift) ───────────────────────────

    public function testCheckReportsDriftAfterModification(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);

        // Simulate user editing the installed skill
        file_put_contents(
            $this->workspace . '/.cursor/skills/demo-skill/SKILL.md',
            "# Modified by user\n",
        );

        $r = $this->apm(['check', 'demo', '-t', 'cursor']);

        self::assertSame(2, $r['exit']);
        self::assertStringContainsString('[drift]', $r['stdout']);
    }

    // ─── install → show (installed status) ───────────────────────────

    public function testShowReportsInstalledAfterInstall(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);
        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        self::assertStringContainsString('skill:demo-skill  installed', $r['stdout']);
        self::assertStringContainsString('rule:demo-rule  installed', $r['stdout']);
        self::assertStringContainsString('agent:demo-agent  installed', $r['stdout']);
    }

    // ─── install → uninstall → verify files removed ──────────────────

    public function testUninstallPresetRemovesAllFiles(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);

        // Verify files exist first
        $this->assertFileInWorkspace('.cursor/skills/demo-skill/SKILL.md');

        // Uninstall (no drift, so no --force needed)
        $r = $this->apm(['preset:uninstall', 'demo', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        self::assertStringContainsString('Uninstalled skill demo-skill', $r['stdout']);
        self::assertStringContainsString('Uninstalled rule demo-rule', $r['stdout']);
        self::assertStringContainsString('Uninstalled agent demo-agent', $r['stdout']);

        $this->assertFileNotInWorkspace('.cursor/skills/demo-skill/SKILL.md');
        $this->assertFileNotInWorkspace('.cursor/rules/git/demo-rule.mdc');
        $this->assertFileNotInWorkspace('.cursor/agents/demo-agent.md');
    }

    // ─── install → uninstall → check (missing) ──────────────────────

    public function testCheckReportsMissingAfterUninstall(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);
        $this->apm(['preset:uninstall', 'demo', '-t', 'cursor']);

        $r = $this->apm(['check', 'demo', '-t', 'cursor']);

        self::assertSame(2, $r['exit']);
        self::assertStringContainsString('[miss]', $r['stdout']);
    }

    // ─── install → modify → uninstall blocked → uninstall --force ────

    public function testUninstallBlockedByDriftThenForcedSucceeds(): void
    {
        $this->apm(['install', 'demo', '-t', 'cursor']);

        // Modify installed file
        file_put_contents(
            $this->workspace . '/.cursor/skills/demo-skill/SKILL.md',
            "# User modified\n",
        );

        // Uninstall without force should fail
        $r = $this->apm(['preset:uninstall', 'demo', '-t', 'cursor']);
        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('Re-run with --force', $r['stdout']);
        $this->assertFileInWorkspace('.cursor/skills/demo-skill/SKILL.md');

        // Uninstall with force should succeed
        $r = $this->apm(['preset:uninstall', 'demo', '-t', 'cursor', '--force']);
        self::assertSame(0, $r['exit']);
        $this->assertFileNotInWorkspace('.cursor/skills/demo-skill/SKILL.md');
    }

    // ─── Multi-target install ────────────────────────────────────────

    public function testInstallOnBothTargetsCreatesFilesInBothLocations(): void
    {
        $r = $this->apm(['install', 'demo', '-t', 'cursor', '-t', 'kiro']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        $this->assertFileInWorkspace('.cursor/skills/demo-skill/SKILL.md');
        $this->assertFileInWorkspace('.kiro/skills/demo-skill/SKILL.md');
        $this->assertFileInWorkspace('.cursor/agents/demo-agent.md');
        $this->assertFileInWorkspace('.kiro/agents/demo-agent.md');
    }

    // ─── Unknown preset ──────────────────────────────────────────────

    public function testInstallUnknownPresetFails(): void
    {
        $r = $this->apm(['install', 'nonexistent', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('requires an explicit type prefix', $r['stdout']);
        self::assertStringContainsString('apm add skill', $r['stdout']);
    }

    // ─── Unknown target ──────────────────────────────────────────────

    public function testInstallUnknownTargetFails(): void
    {
        $r = $this->apm(['install', 'demo', '-t', 'vscode']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('Unknown targets', $r['stdout']);
    }
}
