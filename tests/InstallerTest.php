<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
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

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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
        file_put_contents($pkg . '/abilities/rules/spec/spec-core.kiro.md', 'x');

        $proj = sys_get_temp_dir() . '/apm-inst-stp-' . bin2hex(random_bytes(4));
        mkdir($proj, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($proj);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => ['spec-core'],
            'agents' => [],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Installed steering spec-core -> kiro', $output);
        self::assertFileExists($proj . '/.kiro/steering/spec/spec-core.md');
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

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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
        mkdir($tmp . '/abilities/gitignore', 0775, true);
        file_put_contents($tmp . '/abilities/gitignore/template.gitignore', implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @apm:end',
        ]));

        $pkg = sys_get_temp_dir() . '/apm-installer-gi-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        file_put_contents($pkg . '/abilities/skills/graphify/SKILL.md', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        mkdir($pkg . '/abilities/rules/spec', 0775, true);
        mkdir($pkg . '/abilities/agents', 0775, true);
        file_put_contents($pkg . '/abilities/rules/spec/spec-core.cursor.mdc', 'x');
        file_put_contents($pkg . '/abilities/rules/spec/spec-core.kiro.md', 'x');
        file_put_contents($pkg . '/abilities/agents/code-reviewer.cursor.md', 'x');
        file_put_contents($pkg . '/abilities/agents/code-reviewer.kiro.md', 'x');

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        $items = $installer->listAvailableItems();

        self::assertSame(['graphify'], $items['skills']);
        self::assertSame(['spec-core'], $items['rules']);
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
        file_put_contents($project . '/.cursor/rules/spec/spec-core.mdc', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        self::assertTrue($installer->isInstalledOnTarget('skill', 'graphify', 'cursor'));
        self::assertTrue($installer->isInstalledOnTarget('agent', 'code-reviewer', 'cursor'));
        self::assertTrue($installer->isInstalledOnTarget('rule', 'spec-core', 'cursor'));
        self::assertFalse($installer->isInstalledOnTarget('skill', 'missing-skill', 'cursor'));

        chdir($old);
    }

    public function testIsInstalledOnTargetSupportsKiroAndUnknownType(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-is-installed-kiro-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);
        $project = sys_get_temp_dir() . '/apm-inst-is-installed-kiro-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.kiro/steering/spec', 0775, true);
        file_put_contents($project . '/.kiro/steering/spec/spec-core.md', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        self::assertTrue($installer->isInstalledOnTarget('rule', 'spec-core', 'kiro'));
        self::assertFalse($installer->isInstalledOnTarget('unknown', 'spec-core', 'kiro'));

        chdir($old);
    }

    public function testListAvailableItemsIgnoresInvalidAgentAndSortsNames(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-inst-list-sort-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/zeta', 0775, true);
        mkdir($pkg . '/abilities/skills/alpha', 0775, true);
        mkdir($pkg . '/abilities/rules/git', 0775, true);
        mkdir($pkg . '/abilities/agents', 0775, true);
        file_put_contents($pkg . '/abilities/rules/git/b-rule.cursor.mdc', 'x');
        file_put_contents($pkg . '/abilities/rules/git/a-rule.kiro.md', 'x');
        file_put_contents($pkg . '/abilities/agents/reviewer.cursor.md', 'x');
        file_put_contents($pkg . '/abilities/agents/README.txt', 'x');

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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
        file_put_contents($project . '/.cursor/rules/spec/spec-core.mdc', 'x');
        file_put_contents($project . '/.kiro/steering/spec/other.md', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        $result = $installer->uninstallTyped([
            'skills' => ['graphify'],
            'rules' => ['spec-core'],
            'agents' => ['code-reviewer'],
        ], ['cursor', 'kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Uninstalled skill graphify from cursor', $output);
        self::assertStringContainsString('Uninstalled rule spec-core from cursor', $output);
        self::assertStringContainsString('Uninstalled agent code-reviewer from cursor', $output);
        self::assertStringContainsString('Steering spec-core not found on kiro', $output);
        self::assertStringContainsString('Agent code-reviewer not found on kiro', $output);
        self::assertDirectoryDoesNotExist($project . '/.cursor/skills/graphify');
        self::assertFileDoesNotExist($project . '/.cursor/rules/spec/spec-core.mdc');
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

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
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
        file_put_contents($pkg . '/abilities/rules/spec/spec-core.cursor.mdc', "x\n");
        $project = sys_get_temp_dir() . '/apm-inst-rule-fail-proj-' . bin2hex(random_bytes(4));
        mkdir($project . '/.cursor/rules', 0775, true);
        file_put_contents($project . '/.cursor/rules/spec', "block dir creation\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        set_error_handler(static function (int $severity, string $message): bool {
            return str_contains($message, 'mkdir(): File exists');
        });
        try {
            $result = $installer->installTyped([
                'skills' => [],
                'rules' => ['spec-core'],
                'agents' => [],
            ], ['cursor']);
        } finally {
            restore_error_handler();
        }

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        self::assertStringContainsString('Install copy failed (rule spec-core -> cursor)', implode("\n", $result['lines']));
    }

    public function testInstallTypedGitignoreReportsSkipWhenTemplateHasNoMatches(): void
    {
        $project = sys_get_temp_dir() . '/apm-inst-gi-skip-' . bin2hex(random_bytes(4));
        mkdir($project . '/abilities/gitignore', 0775, true);
        file_put_contents($project . '/abilities/gitignore/template.gitignore', implode("\n", [
            '## @apm:block ability=skill:other-skill target=kiro',
            '/.cache/other/',
            '## @apm:end',
        ]));
        $pkg = sys_get_temp_dir() . '/apm-inst-gi-skip-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        file_put_contents($pkg . '/abilities/skills/graphify/SKILL.md', "x\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(new GitIgnoreTemplateService(), null, $pkg, new DirectoryMirrorService());
        $result = $installer->installTyped([
            'skills' => ['graphify'],
            'rules' => [],
            'agents' => [],
        ], ['cursor']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('[skip] No matched .gitignore template blocks.', implode("\n", $result['lines']));
    }
}
