<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Config\PackagePaths;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('deprecated-scope')]
final class InstallScopeCommandsTest extends TestCase
{
    use RemovesDirTrait;
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-install-scope-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testDefaultProjectScopeInstallsUnderWorkspaceNotHome(): void
    {
        $home = $this->tmpDir . '/home';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($home, 0775, true);
        mkdir($workspace, 0775, true);
        $this->withEnv('HOME', $home);
        chdir($workspace);

        $installer = $this->packageInstaller();
        $tester = new CommandTester(new SkillInstallCommand($installer));
        $exit = $tester->execute(['skills' => ['apm'], '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($workspace . '/.cursor/skills/apm/SKILL.md');
        self::assertFileDoesNotExist($home . '/.cursor/skills/apm/SKILL.md');
    }

    public function testScopeUserIsRejectedOnSkillInstall(): void
    {
        $workspace = $this->tmpDir . '/workspace-user';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $installer = $this->packageInstaller();
        $tester = new CommandTester(new SkillInstallCommand($installer));
        $exit = $tester->execute(['skills' => ['apm'], '--target' => ['cursor'], '--scope' => 'user']);

        self::assertSame(Command::FAILURE, $exit);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('--scope option has been removed', $display);
        self::assertStringContainsString('project scope only', $display);
    }

    public function testScopeProjectIsRejectedOnRuleInstall(): void
    {
        $workspace = $this->tmpDir . '/workspace-po';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $installer = $this->packageInstaller();
        $tester = new CommandTester(new RuleInstallCommand($installer));
        $exit = $tester->execute(['rules' => ['cursor-scope'], '--target' => ['cursor'], '--scope' => 'project']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--scope option has been removed', $tester->getDisplay());
    }

    public function testArbitraryScopeValueIsRejected(): void
    {
        $workspace = $this->tmpDir . '/workspace-bogus';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $tester = new CommandTester(new SkillInstallCommand($this->packageInstaller()));
        $exit = $tester->execute(['skills' => ['apm'], '--scope' => 'bogus']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--scope option has been removed', $tester->getDisplay());
    }

    public function testScopeRejectedOnPresetInstall(): void
    {
        $workspace = $this->tmpDir . '/workspace-preset';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $pkg = PackagePaths::packageRoot();
        $registryPath = $pkg . '/abilities.yaml';
        $registry = new AbilityRegistry($registryPath);
        $presetRegistry = new PresetRegistry($registry);
        $installer = $this->packageInstaller();
        $cmd = new InstallCommand($installer, $presetRegistry);

        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['cursor'], '--scope' => 'user']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--scope option has been removed', $tester->getDisplay());
    }

    public function testScopeRejectedOnSkillUninstall(): void
    {
        $workspace = $this->tmpDir . '/workspace-un';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $installer = $this->packageInstaller();
        $uninstallTester = new CommandTester(new SkillUninstallCommand($installer, new CheckService()));
        $exit = $uninstallTester->execute([
            'skills' => ['apm'],
            '--target' => ['cursor'],
            '--scope' => 'user',
            '--force' => true,
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--scope option has been removed', $uninstallTester->getDisplay());
    }

    private function packageInstaller(): Installer
    {
        $pkg = PackagePaths::packageRoot();

        return new Installer(
            registry: new AbilityRegistry($pkg . '/abilities.yaml'),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
    }
}
