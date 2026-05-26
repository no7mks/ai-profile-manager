<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowCommandTest extends TestCase
{
    private string $tmpDir;
    private string|false $oldCwd;
    private string|false $oldBaseline;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-show-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
        $this->oldBaseline = getenv('APM_BASELINE_ROOT');
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        if ($this->oldBaseline === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $this->oldBaseline);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testShowCommandRejectsUnknownTarget(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $this->tmpDir,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testShowCommandRejectsUnknownType(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $this->tmpDir,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--type' => 'invalid-type']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown type', $tester->getDisplay());
    }

    public function testShowCommandFiltersTypeSuccessfully(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: demo',
            '    description: Demo skill',
            '    targets:',
            '      cursor: .cursor/skills/demo',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);
        putenv('APM_BASELINE_ROOT=' . $pkg);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--type' => 'skill', '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Skills', $tester->getDisplay());
        self::assertStringContainsString('demo', $tester->getDisplay());
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
