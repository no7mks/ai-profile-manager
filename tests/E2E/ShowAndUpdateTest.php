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
        self::assertStringContainsString('Skills', $r['stdout']);
        self::assertStringContainsString('graphify', $r['stdout']);
        self::assertStringContainsString('gitflow', $r['stdout']);
        self::assertStringContainsString('Rules', $r['stdout']);
        self::assertStringContainsString('branch-overview', $r['stdout']);
        self::assertStringContainsString('Agents', $r['stdout']);
        self::assertStringContainsString('code-reviewer', $r['stdout']);
        self::assertStringContainsString('[not-installed]', $r['stdout']);
    }

    public function testShowReflectsInstalledStatusAfterInstall(): void
    {
        $this->apm(['skill:install', 'graphify', '-t', 'cursor']);

        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('[installed] graphify', $r['stdout']);
    }

    // ─── show: type filter ───────────────────────────────────────────

    public function testShowTypeFilterShowsOnlyRequestedType(): void
    {
        $r = $this->apm(['show', '-t', 'cursor', '--type', 'skill']);

        self::assertSame(0, $r['exit']);
        self::assertStringContainsString('Skills', $r['stdout']);
        self::assertStringContainsString('graphify', $r['stdout']);
        self::assertStringNotContainsString('Rules', $r['stdout']);
        self::assertStringNotContainsString('Agents', $r['stdout']);
    }

    // ─── show: preset mapping ────────────────────────────────────────

    public function testShowDisplaysPresetMapping(): void
    {
        $r = $this->apm(['show', '-t', 'cursor']);

        self::assertSame(0, $r['exit']);
        // gitflow skill should show preset: gitflow
        self::assertStringContainsString('presets: gitflow', $r['stdout']);
    }

    // ─── show: unknown target ────────────────────────────────────────

    public function testShowRejectsUnknownTarget(): void
    {
        $r = $this->apm(['show', '-t', 'vscode']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('Unknown targets', $r['stdout']);
    }

    // ─── update: writes knowledge base ───────────────────────────────

    public function testUpdateWritesKnowledgeBase(): void
    {
        $homeDir = $this->workspace . '/fakehome';
        mkdir($homeDir, 0775, true);

        $r = $this->apm(['update'], ['HOME' => $homeDir]);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}");
        self::assertFileExists($homeDir . '/.config/apm/knowledge-base.json');

        $kb = json_decode(file_get_contents($homeDir . '/.config/apm/knowledge-base.json'), true);
        self::assertIsArray($kb);
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
