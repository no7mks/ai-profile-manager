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
}
