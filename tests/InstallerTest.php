<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\HookChecker;
use AiProfileManager\Service\HookInstaller;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    public function testInstallTypedMirrorsSkillAndAgentFromPackageFixture(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/demo-skill', 0775, true);
        file_put_contents($pkg . '/abilities/skills/demo-skill/SKILL.md', "demo skill\n");
        mkdir($pkg . '/abilities/agents', 0775, true);
        file_put_contents($pkg . '/abilities/agents/demo-agent.cursor.md', "demo agent\n");

        $proj = sys_get_temp_dir() . '/apm-inst-proj-' . bin2hex(random_bytes(4));
        mkdir($proj, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($proj);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => ['demo-skill'],
            'rules' => [],
            'agents' => ['demo-agent'],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Installed skill demo-skill -> cursor', $output);
        self::assertStringContainsString('Installed agent demo-agent -> cursor', $output);

        self::assertFileExists($proj . '/.cursor/skills/demo-skill/SKILL.md');
        self::assertSame("demo skill\n", (string) file_get_contents($proj . '/.cursor/skills/demo-skill/SKILL.md'));
        self::assertFileExists($proj . '/.cursor/agents/demo-agent.md');
    }

    public function testRulesAreRenderedAsSteeringOnKiro(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-st-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/rules/spec', 0775, true);
        file_put_contents($pkg . '/abilities/rules/spec/spec-goal.kiro.md', 'x');

        $proj = sys_get_temp_dir() . '/apm-inst-stp-' . bin2hex(random_bytes(4));
        mkdir($proj, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($proj);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => ['spec-goal'],
            'agents' => [],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Installed steering spec-goal -> kiro', $output);
        self::assertFileExists($proj . '/.kiro/steering/spec/spec-goal.md');
    }

    public function testRuleInstallUsesCategoryFileLayoutNotNameTargetDirs(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-rule-flat-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/rules/git', 0775, true);
        file_put_contents($pkg . '/abilities/rules/git/branch-overview.cursor.mdc', 'cursor');
        file_put_contents($pkg . '/abilities/rules/git/branch-overview.kiro.md', 'kiro');

        $proj = sys_get_temp_dir() . '/apm-inst-rule-flat-proj-' . bin2hex(random_bytes(4));
        mkdir($proj, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($proj);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $cursor = $installer->installTyped([
            'skills' => [],
            'rules' => ['branch-overview'],
            'agents' => [],
        ], ['cursor']);
        $kiro = $installer->installTyped([
            'skills' => [],
            'rules' => ['branch-overview'],
            'agents' => [],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $cursor['exit_code']);
        self::assertSame(0, $kiro['exit_code']);
        self::assertFileExists($proj . '/.cursor/rules/git/branch-overview.mdc');
        self::assertFileExists($proj . '/.kiro/steering/git/branch-overview.md');
        self::assertDirectoryDoesNotExist($proj . '/abilities/rules/branch-overview/cursor');
        self::assertDirectoryDoesNotExist($proj . '/abilities/rules/branch-overview/kiro');
    }

    public function testInstallTypedWritesManagedGitignoreWhenTemplateMatches(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-installer-gi-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $pkg = sys_get_temp_dir() . '/apm-installer-gi-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        file_put_contents($pkg . '/abilities/skills/graphify/SKILL.md', 'x');
        // Template now lives at packageRoot/.gitignore
        file_put_contents($pkg . '/.gitignore', implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @apm:end',
        ]));
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify skill',
            '    targets:',
            '      cursor: abilities/skills/graphify',
        ]) . "\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(registry: $registry, gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => ['graphify'],
            'rules' => [],
            'agents' => [],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);

        $gitignore = (string) file_get_contents($tmp . '/.gitignore');
        self::assertStringContainsString('# BEGIN apm-managed-gitignore v1', $gitignore);
        self::assertStringContainsString('/.cache/graphify/', $gitignore);
    }

    public function testListAvailableItemsCollectsSkillsAgentsRules(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-list-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);

        // Create abilities.yaml with skills, rules, agents
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify skill',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            '      kiro: .kiro/skills/graphify',
            'rules:',
            '  - path: spec-goal',
            '    description: Spec goal rule',
            '    targets:',
            '      cursor: .cursor/rules/spec/spec-goal.mdc',
            '      kiro: .kiro/steering/spec/spec-goal.md',
            'agents:',
            '  - path: code-reviewer',
            '    description: Code reviewer agent',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
            '      kiro: .kiro/agents/code-reviewer.md',
        ]) . "\n");

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(registry: $registry, gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $items = $installer->listAvailableItems();

        self::assertSame(['graphify'], $items['skills']);
        self::assertSame(['spec-goal'], $items['rules']);
        self::assertSame(['code-reviewer'], $items['agents']);
    }

    public function testIsInstalledOnTargetDetectsSkillAgentAndRule(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-is-installed-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);
        $project = sys_get_temp_dir() . '/apm-inst-is-installed-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/skills/graphify', 0775, true);
        mkdir($project . '/.cursor/agents', 0775, true);
        mkdir($project . '/.cursor/rules/spec', 0775, true);
        file_put_contents($project . '/.cursor/agents/code-reviewer.md', 'x');
        file_put_contents($project . '/.cursor/rules/spec/spec-goal.mdc', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        self::assertTrue($installer->isInstalledOnTarget('skill', 'graphify', 'cursor'));
        self::assertTrue($installer->isInstalledOnTarget('agent', 'code-reviewer', 'cursor'));
        self::assertTrue($installer->isInstalledOnTarget('rule', 'spec-goal', 'cursor'));
        self::assertFalse($installer->isInstalledOnTarget('skill', 'missing-skill', 'cursor'));

        chdir($old);
    }

    public function testIsInstalledOnTargetSupportsKiroAndUnknownType(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-is-installed-kiro-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);
        $project = sys_get_temp_dir() . '/apm-inst-is-installed-kiro-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.kiro/steering/spec', 0775, true);
        file_put_contents($project . '/.kiro/steering/spec/spec-goal.md', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        self::assertTrue($installer->isInstalledOnTarget('rule', 'spec-goal', 'kiro'));
        self::assertFalse($installer->isInstalledOnTarget('unknown', 'spec-goal', 'kiro'));

        chdir($old);
    }

    public function testListAvailableItemsIgnoresInvalidAgentAndSortsNames(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-list-sort-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);

        // Create abilities.yaml with multiple entries to verify sorting
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: zeta',
            '    description: Zeta skill',
            '    targets:',
            '      cursor: .cursor/skills/zeta',
            '  - path: alpha',
            '    description: Alpha skill',
            '    targets:',
            '      cursor: .cursor/skills/alpha',
            'rules:',
            '  - path: b-rule',
            '    description: B rule',
            '    targets:',
            '      cursor: .cursor/rules/git/b-rule.mdc',
            '  - path: a-rule',
            '    description: A rule',
            '    targets:',
            '      kiro: .kiro/steering/git/a-rule.md',
            'agents:',
            '  - path: reviewer',
            '    description: Reviewer agent',
            '    targets:',
            '      cursor: .cursor/agents/reviewer.md',
        ]) . "\n");

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(registry: $registry, gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $items = $installer->listAvailableItems();

        self::assertSame(['alpha', 'zeta'], $items['skills']);
        self::assertSame(['a-rule', 'b-rule'], $items['rules']);
        self::assertSame(['reviewer'], $items['agents']);
    }

    public function testInstallTypedReturnsFailuresWhenBundlesAreMissing(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-missing-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills', 0775, true);
        mkdir($pkg . '/abilities/rules', 0775, true);
        mkdir($pkg . '/abilities/agents', 0775, true);
        $project = sys_get_temp_dir() . '/apm-inst-missing-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => ['missing-skill'],
            'rules' => ['missing-rule'],
            'agents' => ['missing-agent'],
        ], ['cursor']);

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Missing ability bundle: skill missing-skill', $output);
        self::assertStringContainsString('Missing ability bundle: rule missing-rule', $output);
        self::assertStringContainsString('Missing ability bundle: agent missing-agent', $output);
    }

    public function testUninstallTypedRemovesInstalledItemsAndReportsMissingOnKiro(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-uninstall-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);
        $project = sys_get_temp_dir() . '/apm-inst-uninstall-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/skills/graphify/sub', 0775, true);
        mkdir($project . '/.cursor/agents', 0775, true);
        mkdir($project . '/.cursor/rules/spec', 0775, true);
        mkdir($project . '/.kiro/steering/spec', 0775, true);
        file_put_contents($project . '/.cursor/skills/graphify/sub/file.txt', 'x');
        file_put_contents($project . '/.cursor/agents/code-reviewer.md', 'x');
        file_put_contents($project . '/.cursor/rules/spec/spec-goal.mdc', 'x');
        file_put_contents($project . '/.kiro/steering/spec/other.md', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->uninstallTyped([
            'skills' => ['graphify'],
            'rules' => ['spec-goal'],
            'agents' => ['code-reviewer'],
        ], ['cursor', 'kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Uninstalled skill graphify from cursor', $output);
        self::assertStringContainsString('Uninstalled rule spec-goal from cursor', $output);
        self::assertStringContainsString('Uninstalled agent code-reviewer from cursor', $output);
        self::assertStringContainsString('Steering spec-goal not found on kiro', $output);
        self::assertStringContainsString('Agent code-reviewer not found on kiro', $output);
        self::assertDirectoryDoesNotExist($project . '/.cursor/skills/graphify');
        self::assertFileDoesNotExist($project . '/.cursor/rules/spec/spec-goal.mdc');
        self::assertFileDoesNotExist($project . '/.cursor/agents/code-reviewer.md');
    }

    public function testInstallTypedFailsWhenAgentTargetDirectoryCannotBeCreated(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-agent-fail-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/agents', 0775, true);
        file_put_contents($pkg . '/abilities/agents/code-reviewer.cursor.md', "x\n");
        $project = sys_get_temp_dir() . '/apm-inst-agent-fail-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor', 0775, true);
        file_put_contents($project . '/.cursor/agents', "block dir creation\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        set_error_handler(static function (int $severity, string $message): bool {
            return str_contains($message, 'mkdir(): File exists');
        });
        try {
            $result = $installer->installTyped([
                'skills' => [],
                'rules' => [],
                'agents' => ['code-reviewer'],
            ], ['cursor']);
        } finally {
            restore_error_handler();
        }

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        self::assertStringContainsString('Install copy failed (agent code-reviewer -> cursor)', implode("\n", $result['lines']));
    }

    public function testInstallTypedFailsWhenRuleTargetDirectoryCannotBeCreated(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-rule-fail-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/rules/spec', 0775, true);
        file_put_contents($pkg . '/abilities/rules/spec/spec-goal.cursor.mdc', "x\n");
        $project = sys_get_temp_dir() . '/apm-inst-rule-fail-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/rules', 0775, true);
        file_put_contents($project . '/.cursor/rules/spec', "block dir creation\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        set_error_handler(static function (int $severity, string $message): bool {
            return str_contains($message, 'mkdir(): File exists');
        });
        try {
            $result = $installer->installTyped([
                'skills' => [],
                'rules' => ['spec-goal'],
                'agents' => [],
            ], ['cursor']);
        } finally {
            restore_error_handler();
        }

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        self::assertStringContainsString('Install copy failed (rule spec-goal -> cursor)', implode("\n", $result['lines']));
    }

    public function testInstallTypedGitignoreReportsSkipWhenTemplateHasNoMatches(): void
    {
        $project = sys_get_temp_dir() . '/apm-inst-gi-skip-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $pkg = sys_get_temp_dir() . '/apm-inst-gi-skip-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        file_put_contents($pkg . '/abilities/skills/graphify/SKILL.md', "x\n");
        // Template at packageRoot/.gitignore with non-matching blocks
        file_put_contents($pkg . '/.gitignore', implode("\n", [
            '## @apm:block ability=skill:other-skill target=kiro',
            '/.cache/other/',
            '## @apm:end',
        ]));
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify skill',
            '    targets:',
            '      cursor: abilities/skills/graphify',
        ]) . "\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(registry: $registry, gitIgnore: new GitIgnoreTemplateService(), packageRoot: $pkg, mirror: new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => ['graphify'],
            'rules' => [],
            'agents' => [],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('[skip] No matched .gitignore template blocks.', implode("\n", $result['lines']));
    }

    // ─── Hook type dispatch tests (Task 5.1) ──────────────────────────

    public function testInstallTypedDispatchesHookToKiroPlatform(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-hook-kiro-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-inst-hook-kiro-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        self::assertStringContainsString('check-write-length', $output);
        self::assertFileExists($project . '/.kiro/hooks/check-write-length.kiro.hook');
        self::assertSame(
            '{"name":"check-write-length","version":"1"}',
            (string) file_get_contents($project . '/.kiro/hooks/check-write-length.kiro.hook'),
        );
    }

    public function testInstallTypedDispatchesHookToCursorPlatform(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-hook-cursor-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks/check-write-length', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length/check-write-length.sh', '#!/bin/bash');
        file_put_contents($pkg . '/hooks/check-write-length/check-write-length.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/check-write-length/check-write-length.sh', 'matcher' => 'Write'],
            ],
        ]));

        $project = sys_get_temp_dir() . '/apm-inst-hook-cursor-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        self::assertStringContainsString('check-write-length', $output);
        self::assertFileExists($project . '/.cursor/hooks/check-write-length/check-write-length.sh');
        self::assertFileExists($project . '/.cursor/hooks.json');

        $hooksJson = json_decode((string) file_get_contents($project . '/.cursor/hooks.json'), true);
        self::assertSame(1, $hooksJson['version']);
        self::assertCount(1, $hooksJson['hooks']['preToolUse']);
        self::assertSame(
            '.cursor/hooks/check-write-length/check-write-length.sh',
            $hooksJson['hooks']['preToolUse'][0]['command'],
        );
    }

    public function testInstallTypedHookFailsWhenSourceNotExists(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-hook-missing-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        // No hook source file created

        $project = sys_get_temp_dir() . '/apm-inst-hook-missing-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['nonexistent-hook'],
        ], ['kiro']);

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[fail]', $output);
        self::assertStringContainsString('nonexistent-hook', $output);
    }

    public function testInstallTypedHookEmptyListDoesNotError(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-hook-empty-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);

        $project = sys_get_temp_dir() . '/apm-inst-hook-empty-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => [],
        ], ['kiro', 'cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
    }

    public function testInstallTypedDirectoryDuplicateDoesNotTriggerForHooks(): void
    {
        // Pre-create the hook target directory to simulate "already exists" scenario
        $pkg = sys_get_temp_dir() . '/apm-inst-hook-nodup-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks/check-write-length', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length/check-write-length.sh', '#!/bin/bash');
        file_put_contents($pkg . '/hooks/check-write-length/check-write-length.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/check-write-length/check-write-length.sh', 'matcher' => 'Write'],
            ],
        ]));
        // Also create Kiro source
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-inst-hook-nodup-proj-' . bin2hex(random_bytes(4));
        // Pre-create target directories (simulating already-installed state)
        mkdir($project . '/.cursor/hooks/check-write-length', 0775, true);
        file_put_contents($project . '/.cursor/hooks/check-write-length/check-write-length.sh', '#!/bin/bash old');
        mkdir($project . '/.kiro/hooks', 0775, true);
        file_put_contents($project . '/.kiro/hooks/check-write-length.kiro.hook', '{"old":"content"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro', 'cursor']);

        chdir($old);

        // Hook should succeed even though target already exists (no Directory_Duplicate)
        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringNotContainsString('Directory_Duplicate', $output);
        self::assertStringNotContainsString('conflict', $output);
        // Verify files were overwritten successfully
        self::assertSame(
            '{"name":"check-write-length","version":"1"}',
            (string) file_get_contents($project . '/.kiro/hooks/check-write-length.kiro.hook'),
        );
    }

    // ─── Hook uninstall dispatch tests (Task 5.2) ──────────────────────────

    public function testUninstallTypedDispatchesHookToKiroPlatform(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-kiro-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-uninst-hook-kiro-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.kiro/hooks', 0775, true);
        // Install the hook file first
        file_put_contents($project . '/.kiro/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        self::assertStringContainsString('check-write-length', $output);
        self::assertFileDoesNotExist($project . '/.kiro/hooks/check-write-length.kiro.hook');
    }

    public function testUninstallTypedDispatchesHookToCursorPlatform(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-cursor-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);

        $project = sys_get_temp_dir() . '/apm-uninst-hook-cursor-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/hooks/check-write-length', 0775, true);
        file_put_contents($project . '/.cursor/hooks/check-write-length/check-write-length.sh', '#!/bin/bash');
        file_put_contents($project . '/.cursor/hooks/check-write-length/check-write-length.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/check-write-length/check-write-length.sh', 'matcher' => 'Write'],
            ],
        ]));
        // Create hooks.json with the entry
        file_put_contents($project . '/.cursor/hooks.json', json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/check-write-length/check-write-length.sh', 'matcher' => 'Write'],
                ],
            ],
        ]));

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        self::assertStringContainsString('check-write-length', $output);
        self::assertDirectoryDoesNotExist($project . '/.cursor/hooks/check-write-length');
        // Verify hooks.json entry was removed
        $hooksJson = json_decode((string) file_get_contents($project . '/.cursor/hooks.json'), true);
        self::assertSame([], $hooksJson['hooks']['preToolUse']);
    }

    public function testUninstallTypedHookEmptyListDoesNotError(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-empty-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);

        $project = sys_get_temp_dir() . '/apm-uninst-hook-empty-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => [],
        ], ['kiro', 'cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
    }

    public function testUninstallTypedHookDriftWithoutForceAborts(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-drift-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-uninst-hook-drift-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.kiro/hooks', 0775, true);
        // Install a MODIFIED version (drift)
        file_put_contents($project . '/.kiro/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"2-modified"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        // No --force (default false)
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro']);

        chdir($old);

        // Should abort with exit_code 1
        self::assertSame(1, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('drift', $output);
        self::assertStringContainsString('--force', $output);
        // File should NOT be deleted
        self::assertFileExists($project . '/.kiro/hooks/check-write-length.kiro.hook');
    }

    public function testUninstallTypedHookDriftWithForceContinues(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-force-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-uninst-hook-force-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.kiro/hooks', 0775, true);
        // Install a MODIFIED version (drift)
        file_put_contents($project . '/.kiro/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"2-modified"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        // With --force = true
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro'], force: true);

        chdir($old);

        // Should succeed
        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        // File should be deleted
        self::assertFileDoesNotExist($project . '/.kiro/hooks/check-write-length.kiro.hook');
    }

    public function testUninstallTypedCursorHookNoDriftCheckDirectlyUninstalls(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-cursor-nodrift-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);

        $project = sys_get_temp_dir() . '/apm-uninst-hook-cursor-nodrift-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/hooks/my-hook', 0775, true);
        file_put_contents($project . '/.cursor/hooks/my-hook/my-hook.sh', '#!/bin/bash modified');
        file_put_contents($project . '/.cursor/hooks/my-hook/my-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/my-hook/my-hook.sh', 'matcher' => 'Write'],
            ],
        ]));
        file_put_contents($project . '/.cursor/hooks.json', json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/my-hook/my-hook.sh', 'matcher' => 'Write'],
                ],
            ],
        ]));

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        // Cursor has no drift concept, should uninstall directly without --force
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[ok]', $output);
        self::assertDirectoryDoesNotExist($project . '/.cursor/hooks/my-hook');
    }

    public function testUninstallTypedHookSkipsWhenTargetNotExists(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-uninst-hook-miss-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/check-write-length.kiro.hook', '{"name":"check-write-length","version":"1"}');

        $project = sys_get_temp_dir() . '/apm-uninst-hook-miss-proj-' . bin2hex(random_bytes(4));
        mkdir($project, 0775, true);
        // No hook installed

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write-length'],
        ], ['kiro', 'cursor']);

        chdir($old);

        // Should not error, just skip
        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip]', $output);
    }
}
