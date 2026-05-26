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

final class InstallCommandTest extends TestCase
{
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

    public function testInstallCommandInstallsPresetSuccessfully(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/abilities/skills/graphify', 0775, true);
        file_put_contents($pkg . '/abilities/skills/graphify/SKILL.md', "x\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj . '/abilities', 0775, true);
        file_put_contents($proj . '/abilities/_presets.json', json_encode([
            'test-preset' => ['skills' => ['graphify'], 'rules' => [], 'agents' => []],
        ]));
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new InstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'test-preset', '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: test-preset', $tester->getDisplay());
        self::assertStringContainsString('Installed skill graphify', $tester->getDisplay());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
