<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\CleanupCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupCommandTest extends TestCase
{
    use RemovesDirTrait;
    use RestoresEnvTrait;

    private string $tmpDir;

    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-cleanup-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testDefinitionHasNameCleanupAndNoInappropriateOptions(): void
    {
        $pkg = $this->tmpDir . '/pkg-def';
        $this->writeDemoPackage($pkg);

        $command = new CleanupCommand($this->createInstaller($pkg));
        $definition = $command->getDefinition();

        self::assertSame('cleanup', $command->getName());
        self::assertFalse($definition->hasOption('scope'));
        self::assertFalse($definition->hasOption('target'));
        self::assertFalse($definition->hasOption('force'));
        self::assertFalse($definition->hasArgument('skills'));
        self::assertFalse($definition->hasArgument('preset'));
    }

    public function testRejectsUnknownScopeOption(): void
    {
        $pkg = $this->tmpDir . '/pkg-scope';
        $this->writeDemoPackage($pkg);
        $proj = $this->tmpDir . '/proj-scope';
        mkdir($proj, 0775, true);
        chdir($proj);

        $tester = new CommandTester(new CleanupCommand($this->createInstaller($pkg)));

        $this->expectException(ConsoleRuntimeException::class);
        $tester->execute(['--scope' => 'user']);
    }

    public function testCleanupRemovesProjectScopeSkillAndRule(): void
    {
        $pkg = $this->tmpDir . '/pkg-rem';
        $this->writeDemoPackage($pkg);
        $proj = $this->tmpDir . '/proj-rem';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = $this->createInstaller($pkg);
        $install = $installer->installTyped([
            'skills' => ['demo-skill'],
            'rules' => ['git:demo-rule'],
            'agents' => [],
        ], ['cursor']);
        self::assertSame(0, $install['exit_code']);

        self::assertDirectoryExists($proj . '/.cursor/skills/demo-skill');
        self::assertFileExists($proj . '/.cursor/rules/git/demo-rule.mdc');

        $tester = new CommandTester(new CleanupCommand($installer));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertDirectoryDoesNotExist($proj . '/.cursor/skills/demo-skill');
        self::assertFileDoesNotExist($proj . '/.cursor/rules/git/demo-rule.mdc');
        self::assertStringContainsString('Uninstalling project-scope conventional abilities', $tester->getDisplay());
    }

    public function testCleanupLeavesUserScopeCopyIntact(): void
    {
        $pkg = $this->tmpDir . '/pkg-user';
        $this->writeDemoPackage($pkg);
        $home = $this->tmpDir . '/home';
        mkdir($home, 0775, true);
        $this->withEnv('HOME', $home);

        $proj = $this->tmpDir . '/proj-user';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = $this->createInstaller($pkg);
        $installer->installTyped([
            'skills' => ['demo-skill'],
            'rules' => ['git:demo-rule'],
            'agents' => [],
        ], ['cursor']);

        mkdir($home . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($home . '/.cursor/skills/demo-skill/SKILL.md', 'user copy');
        mkdir($home . '/.cursor/rules/git', 0775, true);
        file_put_contents($home . '/.cursor/rules/git/demo-rule.mdc', 'user rule copy');

        $tester = new CommandTester(new CleanupCommand($installer));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($home . '/.cursor/skills/demo-skill/SKILL.md');
        self::assertSame('user copy', (string) file_get_contents($home . '/.cursor/skills/demo-skill/SKILL.md'));
        self::assertFileExists($home . '/.cursor/rules/git/demo-rule.mdc');
        self::assertSame('user rule copy', (string) file_get_contents($home . '/.cursor/rules/git/demo-rule.mdc'));
    }

    public function testCleanupDoesNotDeleteScaffoldOrProjectDocs(): void
    {
        $pkg = $this->tmpDir . '/pkg-scaffold';
        $this->writeDemoPackage($pkg);
        $proj = $this->tmpDir . '/proj-scaffold';
        mkdir($proj . '/docs/state', 0775, true);
        mkdir($proj . '/docs/manual', 0775, true);
        file_put_contents($proj . '/docs/README.md', "# Docs\n");
        file_put_contents($proj . '/docs/state/architecture.md', "# State\n");
        file_put_contents($proj . '/docs/manual/usage.md', "# Manual\n");
        file_put_contents($proj . '/AGENTS.md', "# Agents\n");
        file_put_contents($proj . '/PROJECT.md', "# Project\n");
        chdir($proj);

        $installer = $this->createInstaller($pkg);
        $installer->installTyped([
            'skills' => ['demo-skill'],
            'rules' => [],
            'agents' => [],
        ], ['cursor']);

        $tester = new CommandTester(new CleanupCommand($installer));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($proj . '/docs/README.md');
        self::assertFileExists($proj . '/docs/state/architecture.md');
        self::assertFileExists($proj . '/docs/manual/usage.md');
        self::assertFileExists($proj . '/AGENTS.md');
        self::assertFileExists($proj . '/PROJECT.md');
    }

    public function testCleanupSucceedsWhenRegistryEntriesAreAbsentOnDisk(): void
    {
        $pkg = $this->tmpDir . '/pkg-skip';
        $this->writeDemoPackage($pkg, includeExtraSkill: true);
        $proj = $this->tmpDir . '/proj-skip';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = $this->createInstaller($pkg);

        $tester = new CommandTester(new CleanupCommand($installer));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Uninstalling project-scope conventional abilities', $tester->getDisplay());
    }

    public function testSuccessOutputMentionsApmInitReinstallPath(): void
    {
        $pkg = $this->tmpDir . '/pkg-msg';
        $this->writeDemoPackage($pkg);
        $proj = $this->tmpDir . '/proj-msg';
        mkdir($proj, 0775, true);
        chdir($proj);

        $tester = new CommandTester(new CleanupCommand($this->createInstaller($pkg)));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertMatchesRegularExpression('#/apm\s+init#i', $tester->getDisplay());
    }

    public function testExecuteDelegatesToInstallerUninstallProjectScope(): void
    {
        $pkg = $this->tmpDir . '/pkg-delegate';
        $this->writeDemoPackage($pkg);
        $proj = $this->tmpDir . '/proj-delegate';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = $this->createInstaller($pkg);

        $tester = new CommandTester(new CleanupCommand($installer));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Uninstalling project-scope conventional abilities', $display);

        $direct = $installer->uninstallProjectScope();
        self::assertSame(0, $direct['exit_code']);
        self::assertStringContainsString('Uninstalling project-scope conventional abilities', implode("\n", $direct['lines']));
    }

    private function createInstaller(string $packageRoot): Installer
    {
        $registry = new AbilityRegistry($packageRoot . '/abilities.yaml');

        return new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $packageRoot,
            mirror: new DirectoryMirrorService(),
        );
    }

    private function writeDemoPackage(string $pkg, bool $includeExtraSkill = false): void
    {
        mkdir($pkg . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/demo-skill/SKILL.md', "demo skill\n");
        mkdir($pkg . '/.cursor/rules/git', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/git/demo-rule.mdc', "demo rule\n");

        $yaml = [
            'skills:',
            '  - path: demo-skill',
            '    description: Demo skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
        ];

        if ($includeExtraSkill) {
            $yaml[] = '  - path: never-installed';
            $yaml[] = '    description: Not on disk';
            $yaml[] = '    targets:';
            $yaml[] = '      cursor: .cursor/skills/never-installed';
        }

        $yaml = array_merge($yaml, [
            'rules:',
            '  - path: git:demo-rule',
            '    description: Demo rule',
            '    targets:',
            '      cursor: .cursor/rules/git/demo-rule.mdc',
            'agents: []',
            'hooks: []',
        ]);

        file_put_contents($pkg . '/abilities.yaml', implode("\n", $yaml) . "\n");
    }
}
