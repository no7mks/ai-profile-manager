<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: show command output and update command side effects.
 */
final class ShowAndUpdateTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        $this->createPresets([
            'gitflow' => [
                'skills' => ['gitflow'],
                'rules' => ['branch-overview'],
                'agents' => [],
            ],
        ]);
    }

    // ─── show: lists all abilities with status ───────────────────────

    public function testShowListsAllAbilitiesNotInstalled(): void
    {
        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        self::assertStringContainsString('skill:graphify  not installed', $r['stdout']);
        self::assertStringContainsString('skill:gitflow  not installed', $r['stdout']);
        self::assertStringContainsString('rule:branch-overview  not installed', $r['stdout']);
        self::assertStringContainsString('agent:code-reviewer  not installed', $r['stdout']);
    }

    public function testShowReflectsInstalledStatusAfterInstall(): void
    {
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);

        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('skill:graphify  installed', $r['stdout']);
    }

    // ─── show: type filter ───────────────────────────────────────────

    public function testShowTypeFilterShowsOnlyRequestedType(): void
    {
        $r = $this->apm(['show', '-t', 'cursor', '--type', 'skill']);

        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('skill:graphify  not installed', $r['stdout']);
        self::assertStringContainsString('skill:gitflow  not installed', $r['stdout']);
        self::assertStringNotContainsString('rule:', $r['stdout']);
        self::assertStringNotContainsString('agent:', $r['stdout']);
    }

    // ─── show: preset mapping ────────────────────────────────────────

    public function testShowListsPresetMemberAbilities(): void
    {
        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit']);
        // Preset membership is not shown; preset-defined abilities still appear in the flat list.
        self::assertStringContainsString('skill:gitflow  not installed', $r['stdout']);
        self::assertStringContainsString('rule:branch-overview  not installed', $r['stdout']);
    }

    // ─── show: unknown target ────────────────────────────────────────

    public function testShowRejectsUnknownTarget(): void
    {
        $r = $this->apm(['show', '-t', 'vscode']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('Unknown targets', $r['stdout']);
    }

    // ─── update: writes knowledge base ───────────────────────────────

    public function testUpdateRejectsNonGlobalE2eInvocation(): void
    {
        $r = $this->apm(['update']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('global apm', $r['stdout'] . $r['stderr']);
    }

    // ─── Gitignore management ────────────────────────────────────────

    public function testInstallWritesGitignoreManagedSection(): void
    {
        $this->createGitignoreTemplate(implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @apm:end',
        ]));

        $this->createPresets([
            'graph-preset' => [
                'skills' => ['graphify'],
                'rules' => [],
                'agents' => [],
            ],
        ]);

        $r = $this->apm(['install', 'graph-preset', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        $this->assertFileInWorkspace('.gitignore');

        $gitignore = $this->readWorkspaceFile('.gitignore');
        self::assertStringContainsString('# BEGIN apm-managed-gitignore v1', $gitignore);
        self::assertStringContainsString('/.cache/graphify/', $gitignore);
        self::assertStringContainsString('# END apm-managed-gitignore v1', $gitignore);
    }

    public function testReinstallIsIdempotentForGitignore(): void
    {
        $this->createGitignoreTemplate(implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @apm:end',
        ]));

        $this->createPresets([
            'graph-preset' => [
                'skills' => ['graphify'],
                'rules' => [],
                'agents' => [],
            ],
        ]);

        $this->apm(['install', 'graph-preset', '-t', 'cursor']);
        $first = $this->readWorkspaceFile('.gitignore');

        $this->apm(['install', 'graph-preset', '-t', 'cursor']);
        $second = $this->readWorkspaceFile('.gitignore');

        self::assertSame($first, $second, '.gitignore should be idempotent on reinstall');
    }
}
