<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Core\ConsoleRegistration;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

final class InstallAliasesTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-alias-' . bin2hex(random_bytes(4));
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

    /**
     * @return array{app: Application, pkg: string, proj: string}
     */
    private function appWithPresetFixture(): array
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/graphify/SKILL.md', "x\n");
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'presets:',
            '  - name: test-preset',
            '    description: test-preset',
            '    includes:',
            '      - skill:graphify',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $presetRegistry = new PresetRegistry($registry);
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $app = new Application();
        $app->setAutoExit(false);
        ConsoleRegistration::register($app, $installer, new CheckService(), $presetRegistry);

        return ['app' => $app, 'pkg' => $pkg, 'proj' => $proj];
    }

    public function testAddAliasInstallsPreset(): void
    {
        $fixture = $this->appWithPresetFixture();
        $tester = new ApplicationTester($fixture['app']);
        $exit = $tester->run([
            'command' => 'add',
            'preset' => 'test-preset',
            '--target' => ['cursor'],
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: test-preset', $tester->getDisplay());
        self::assertStringContainsString('Installed skill graphify', $tester->getDisplay());
    }

    public function testSkillRemoveAliasRunsUninstall(): void
    {
        $fixture = $this->appWithPresetFixture();
        $app = $fixture['app'];
        $installExit = (new ApplicationTester($app))->run([
            'command' => 'skill:add',
            'skills' => ['graphify'],
            '--target' => ['cursor'],
        ]);
        self::assertSame(Command::SUCCESS, $installExit);

        $tester = new ApplicationTester($app);
        $exit = $tester->run([
            'command' => 'skill:remove',
            'skills' => ['graphify'],
            '--target' => ['cursor'],
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Uninstalled skill graphify', $tester->getDisplay());
    }

    public function testAddWithoutTypePrefixFailsWithGuidance(): void
    {
        $fixture = $this->appWithPresetFixture();
        $tester = new ApplicationTester($fixture['app']);
        $exit = $tester->run([
            'command' => 'add',
            'preset' => 'myskill',
            '--target' => ['cursor'],
        ]);

        self::assertSame(Command::FAILURE, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('requires an explicit type prefix', $display);
        self::assertStringContainsString('apm add skill', $display);
        self::assertStringNotContainsString('Unknown preset', $display);
    }

    public function testCheckCommandsHaveNoInstallOrRemoveAliases(): void
    {
        $fixture = $this->appWithPresetFixture();
        $forbidden = ['add', 'remove', 'install', 'uninstall'];

        foreach (['check', 'skill:check', 'rule:check', 'agent:check'] as $name) {
            $cmd = $fixture['app']->find($name);
            foreach ($forbidden as $alias) {
                self::assertNotContains(
                    $alias,
                    $cmd->getAliases(),
                    sprintf('Command %s must not alias %s', $name, $alias)
                );
            }
        }
    }

    public function testInstallFamilyExposesAddAndRemoveSynonyms(): void
    {
        $fixture = $this->appWithPresetFixture();
        $app = $fixture['app'];

        self::assertContains('add', $app->find('install')->getAliases());
        self::assertContains('skill:add', $app->find('skill:install')->getAliases());
        self::assertContains('skill:remove', $app->find('skill:uninstall')->getAliases());
        self::assertContains('preset:remove', $app->find('preset:uninstall')->getAliases());
    }
}
