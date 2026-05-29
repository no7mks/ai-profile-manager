<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Bootstrap lifecycle — `apm install` without preset argument.
 *
 * Validates that bootstrap mode installs scaffold, scope rules,
 * and the default preset abilities via real CLI process invocation.
 */
final class BootstrapLifecycleTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Scaffold sources required by ProjectInitializer
        mkdir($this->packageRoot . '/docs', 0775, true);
        file_put_contents($this->packageRoot . '/docs/README.md', "# Docs\n");
        mkdir($this->packageRoot . '/issues', 0775, true);
        file_put_contents($this->packageRoot . '/issues/README.md', "# Issues\n");
        file_put_contents($this->packageRoot . '/AGENTS.md', "# Agents\n");

        // Scope rule sources
        mkdir($this->packageRoot . '/.cursor/rules', 0775, true);
        file_put_contents($this->packageRoot . '/.cursor/rules/cursor-scope.mdc', "# Cursor Scope\n");
        mkdir($this->packageRoot . '/.kiro/steering', 0775, true);
        file_put_contents($this->packageRoot . '/.kiro/steering/kiro-scope.md', "# Kiro Scope\n");

        // Skill and agent sources for default preset
        $this->createSkillSource('apm', "# APM Skill\n");
        $this->createAgentSource('code-reviewer', 'cursor', "# Code Reviewer\n");
        $this->createAgentSource('code-reviewer', 'kiro', "# Code Reviewer\n");

        // abilities.yaml with default preset including scope rules
        $this->createAbilitiesYaml(implode("\n", [
            'rules:',
            '  - path: cursor-scope',
            '    description: Cursor scope rule',
            '    targets:',
            '      cursor: .cursor/rules/cursor-scope.mdc',
            '  - path: kiro-scope',
            '    description: Kiro scope rule',
            '    targets:',
            '      kiro: .kiro/steering/kiro-scope.md',
            'skills:',
            '  - path: apm',
            '    description: apm skill',
            '    targets:',
            '      cursor: .cursor/skills/apm',
            '      kiro: .kiro/skills/apm',
            'agents:',
            '  - path: code-reviewer',
            '    description: code review agent',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
            '      kiro: .kiro/agents/code-reviewer.md',
            'presets:',
            '  - name: default',
            '    description: Bootstrap defaults',
            '    includes:',
            '      - skill:apm',
            '      - agent:code-reviewer',
            '      - rule:cursor-scope',
            '      - rule:kiro-scope',
        ]) . "\n");
    }

    // ─── Bootstrap installs scaffold + default preset ────────────────

    public function testBootstrapInstallsScaffoldAndDefaultPreset(): void
    {
        $r = $this->apm(['install', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        // Scaffold files
        $this->assertFileInWorkspace('docs/README.md');
        $this->assertFileInWorkspace('issues/README.md');
        $this->assertFileInWorkspace('AGENTS.md');

        // Scope rule (cursor-scope only, kiro-scope has no cursor target)
        $this->assertFileInWorkspace('.cursor/rules/cursor-scope.mdc');

        // Default preset abilities
        $this->assertFileInWorkspace('.cursor/skills/apm/SKILL.md');
        $this->assertFileInWorkspace('.cursor/agents/code-reviewer.md');

        // Output messages
        self::assertStringContainsString('Installing default preset', $r['stdout']);
        self::assertStringContainsString('Installed skill apm', $r['stdout']);
        self::assertStringContainsString('Installed agent code-reviewer', $r['stdout']);
        self::assertStringContainsString('Bootstrap complete', $r['stdout']);
    }

    public function testBootstrapInstallsBothTargetsWhenNoTargetSpecified(): void
    {
        $r = $this->apm(['install']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        // Each platform gets its scope rule
        $this->assertFileInWorkspace('.cursor/rules/cursor-scope.mdc');
        $this->assertFileInWorkspace('.kiro/steering/kiro-scope.md');

        // Both platforms get default preset abilities
        $this->assertFileInWorkspace('.cursor/skills/apm/SKILL.md');
        $this->assertFileInWorkspace('.kiro/skills/apm/SKILL.md');
        $this->assertFileInWorkspace('.cursor/agents/code-reviewer.md');
        $this->assertFileInWorkspace('.kiro/agents/code-reviewer.md');
    }

    // ─── Bootstrap blocked when scaffold already exists ──────────────

    public function testBootstrapBlockedWhenScaffoldExists(): void
    {
        // Pre-create scaffold in workspace
        mkdir($this->workspace . '/docs', 0775, true);
        file_put_contents($this->workspace . '/docs/README.md', "existing\n");

        $r = $this->apm(['install', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('--force', $r['stdout']);
    }

    public function testBootstrapForceOverwritesExistingScaffold(): void
    {
        // Pre-create scaffold in workspace
        mkdir($this->workspace . '/docs', 0775, true);
        file_put_contents($this->workspace . '/docs/README.md', "existing\n");

        $r = $this->apm(['install', '-t', 'cursor', '--force']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        // File should be overwritten with package content (not "existing")
        self::assertStringStartsWith("# Docs\n", $this->readWorkspaceFile('docs/README.md'));
        self::assertStringNotContainsString('existing', $this->readWorkspaceFile('docs/README.md'));
    }

    // ─── Scope rule target isolation ────────────────────────────────

    public function testCursorTargetDoesNotInstallKiroScope(): void
    {
        $r = $this->apm(['install', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        // cursor-scope installed
        $this->assertFileInWorkspace('.cursor/rules/cursor-scope.mdc');
        // kiro-scope NOT installed (has no cursor target)
        $this->assertFileNotInWorkspace('.kiro/steering/kiro-scope.md');
    }

    public function testKiroTargetDoesNotInstallCursorScope(): void
    {
        $r = $this->apm(['install', '-t', 'kiro']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        // kiro-scope installed
        $this->assertFileInWorkspace('.kiro/steering/kiro-scope.md');
        // cursor-scope NOT installed (has no kiro target)
        $this->assertFileNotInWorkspace('.cursor/rules/cursor-scope.mdc');
    }

    // ─── Bootstrap fails when default preset is missing ──────────────

    public function testBootstrapFailsWhenDefaultPresetMissing(): void
    {
        // Overwrite abilities.yaml without default preset
        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: apm',
            '    description: apm skill',
            '    targets:',
            '      cursor: .cursor/skills/apm',
            'presets: []',
        ]) . "\n");

        $r = $this->apm(['install', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString("Preset 'default' not found", $r['stdout']);
    }
}
