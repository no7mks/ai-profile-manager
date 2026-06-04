<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\BootstrapCommand;
use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class BootstrapCommandTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-bootstrap-cmd-' . bin2hex(random_bytes(4));
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

    public function testBootstrapCommandRejectsUnknownTarget(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $cmd = new BootstrapCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['bad-target']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testBootstrapInstallsScaffoldOnly(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $cmd = new BootstrapCommand($initializer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Installing scaffold', $display);
        self::assertStringContainsString('Scaffold installed', $display);
        self::assertStringContainsString('Bootstrap complete', $display);
        self::assertStringNotContainsString('Installing default preset', $display);
        self::assertStringNotContainsString('Installed skill', $display);

        self::assertFileExists($proj . '/docs/README.md');
        self::assertFileExists($proj . '/issues/README.md');
        self::assertFileExists($proj . '/AGENTS.md');
    }

    public function testBootstrapBlockedWhenScaffoldExists(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj . '/docs', 0775, true);
        file_put_contents($proj . '/docs/README.md', "existing\n");
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $cmd = new BootstrapCommand($initializer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--force', $tester->getDisplay());
    }

}
