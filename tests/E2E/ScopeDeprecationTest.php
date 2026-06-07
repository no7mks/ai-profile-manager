<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Verify --scope option rejection on all commands using HandlesDeployScopeOption trait,
 * and global-setup command deprecation.
 *
 * Ref: Requirement 1, 2
 */
final class ScopeDeprecationTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provide a minimal abilities.yaml for typed commands that need a registry
        $this->createAbilitiesYaml(implode("\n", [
            'skills:',
            '  - path: dummy-skill',
            '    description: Dummy skill for scope test',
            '    targets:',
            '      cursor: .cursor/skills/dummy-skill',
            '      kiro: .kiro/skills/dummy-skill',
            'rules:',
            '  - path: dummy-rule',
            '    description: Dummy rule for scope test',
            '    targets:',
            '      cursor: .cursor/rules/test/dummy-rule.mdc',
            '      kiro: .kiro/steering/test/dummy-rule.md',
            'agents:',
            '  - path: dummy-agent',
            '    description: Dummy agent for scope test',
            '    targets:',
            '      cursor: .cursor/agents/dummy-agent.md',
            '      kiro: .kiro/agents/dummy-agent.md',
            'presets:',
            '  - name: dummy-preset',
            '    description: Dummy preset for scope test',
            '    includes:',
            '      - skill:dummy-skill',
        ]) . "\n");
    }

    // ─── --scope rejection on install (preset install) ───────────────

    public function testInstallWithScopeProjectExitsWithError(): void
    {
        $r = $this->apm(['install', 'dummy-preset', '--scope', 'project', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on show ───────────────────────────────────

    public function testShowWithScopeUserExitsWithError(): void
    {
        $r = $this->apm(['show', '--scope', 'user']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on skill:install ──────────────────────────

    public function testSkillInstallWithScopeProjectExitsWithError(): void
    {
        $r = $this->apm(['skill:install', 'dummy-skill', '--scope', 'project', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on rule:install ───────────────────────────

    public function testRuleInstallWithScopeUserExitsWithError(): void
    {
        $r = $this->apm(['rule:install', 'dummy-rule', '--scope', 'user', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on agent:install ──────────────────────────

    public function testAgentInstallWithScopeFooExitsWithError(): void
    {
        $r = $this->apm(['agent:install', 'dummy-agent', '--scope', 'foo', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on skill:uninstall ────────────────────────

    public function testSkillUninstallWithScopeProjectExitsWithError(): void
    {
        $r = $this->apm(['skill:uninstall', 'dummy-skill', '--scope', 'project', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on rule:uninstall ─────────────────────────

    public function testRuleUninstallWithScopeUserExitsWithError(): void
    {
        $r = $this->apm(['rule:uninstall', 'dummy-rule', '--scope', 'user', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on agent:uninstall ────────────────────────

    public function testAgentUninstallWithScopeProjectExitsWithError(): void
    {
        $r = $this->apm(['agent:uninstall', 'dummy-agent', '--scope', 'project', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── --scope rejection on preset:uninstall ───────────────────────

    public function testPresetUninstallWithScopeUserExitsWithError(): void
    {
        $r = $this->apm(['preset:uninstall', 'dummy-preset', '--scope', 'user', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('removed', $combined);
        self::assertStringContainsString('project scope only', $combined);
    }

    // ─── Helper ────────────────────────────────────────────────────────

    /**
     * Normalize CLI output: combine stdout+stderr, collapse whitespace so
     * SymfonyStyle word-wrapping does not break substring assertions.
     *
     * @param array{exit: int, stdout: string, stderr: string} $r
     */
    private function normalizeOutput(array $r): string
    {
        return preg_replace('/\s+/', ' ', $r['stdout'] . $r['stderr']);
    }

    // ─── global-setup deprecation ────────────────────────────────────

    public function testGlobalSetupNoArgsExitsWithError(): void
    {
        $r = $this->apm(['global-setup']);

        self::assertSame(1, $r['exit']);
        $combined = $this->normalizeOutput($r);
        self::assertStringContainsString('global-setup', $combined);
        self::assertStringContainsString('apm bootstrap', $combined);
    }

    public function testGlobalSetupWithTargetFlagStillExitsWithError(): void
    {
        $r = $this->apm(['global-setup', '--target', 'cursor']);

        // global-setup doesn't accept --target, but even if it did, it should still exit 1
        self::assertNotSame(0, $r['exit']);
    }

    public function testGlobalSetupDoesNotCreateFiles(): void
    {
        $this->apm(['global-setup']);

        // Workspace should remain empty (no files created)
        $workspaceFiles = glob($this->workspace . '/{,.}*', GLOB_BRACE);
        // Filter out . and ..
        $workspaceFiles = array_filter($workspaceFiles, function (string $f) {
            $base = basename($f);
            return $base !== '.' && $base !== '..';
        });
        self::assertEmpty($workspaceFiles, 'global-setup should not create any files in workspace');

        // fakeHome should remain empty (no files created)
        $homeFiles = glob($this->fakeHome . '/{,.}*', GLOB_BRACE);
        $homeFiles = array_filter($homeFiles, function (string $f) {
            $base = basename($f);
            return $base !== '.' && $base !== '..';
        });
        self::assertEmpty($homeFiles, 'global-setup should not create any files in home directory');
    }
}
