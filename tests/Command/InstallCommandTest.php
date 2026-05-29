<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class InstallCommandTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-install-cmd-' . bin2hex(random_bytes(4));
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

    public function testInstallCommandRejectsUnknownTarget(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $this->tmpDir,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new InstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'x', '--target' => ['bad-target']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testInstallCommandRejectsUnknownPreset(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $this->tmpDir,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new InstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'nonexistent-preset', '--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    public function testBootstrapInstallsDefaultPreset(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.cursor/skills/apm', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/apm/SKILL.md', "x\n");
        mkdir($pkg . '/.cursor/agents', 0775, true);
        file_put_contents($pkg . '/.cursor/agents/code-reviewer.md', "x\n");

        // scaffold sources required by ProjectInitializer
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        // scope rule for cursor target
        mkdir($pkg . '/.cursor/rules', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "x\n");
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: apm',
            '    description: apm skill',
            '    targets:',
            '      cursor: .cursor/skills/apm/',
            'agents:',
            '  - path: code-reviewer',
            '    description: code review agent',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
            'presets:',
            '  - name: default',
            '    description: Bootstrap defaults',
            '    includes:',
            '      - skill:apm',
            '      - agent:code-reviewer',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $presetRegistry = new \AiProfileManager\Service\PresetRegistry($registry);
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $initializer = new \AiProfileManager\Service\ProjectInitializer($pkg);
        $cmd = new InstallCommand($installer, $initializer, $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Installing default preset', $display);
        self::assertStringContainsString('Installed skill apm', $display);
        self::assertStringContainsString('Installed agent code-reviewer', $display);
    }

    public function testBootstrapFailsWhenDefaultPresetMissing(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        // scaffold sources required by ProjectInitializer
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        // scope rule for cursor target
        mkdir($pkg . '/.cursor/rules', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "x\n");

        // abilities.yaml without default preset
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills: []',
            'presets: []',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $presetRegistry = new \AiProfileManager\Service\PresetRegistry($registry);
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $initializer = new \AiProfileManager\Service\ProjectInitializer($pkg);
        $cmd = new InstallCommand($installer, $initializer, $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString("Preset 'default' not found", $tester->getDisplay());
    }

    public function testInstallCommandInstallsPresetSuccessfully(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/graphify/SKILL.md', "x\n");

        // Create abilities.yaml with skill and preset
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

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $presetRegistry = new \AiProfileManager\Service\PresetRegistry($registry);
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new InstallCommand($installer, presetRegistry: $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'test-preset', '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: test-preset', $tester->getDisplay());
        self::assertStringContainsString('Installed skill graphify', $tester->getDisplay());
    }

}
