<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\SkillCheckCommand;
use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SkillCheckCommandTest extends TestCase
{
    private string $tmpDir;
    private string|false $oldCwd;
    private string|false $oldBaseline;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-skill-check-' . bin2hex(random_bytes(4));
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

    public function testSkillCheckRunsWithExplicitSkillAndTarget(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/skills/graphify', 0775, true);
        mkdir($workspace . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($baseline . '/abilities/skills/graphify/SKILL.md', "x\n");
        file_put_contents($workspace . '/.cursor/skills/graphify/SKILL.md', "x\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $cmd = new SkillCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['graphify'], '--target' => ['cursor']]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('skill', $tester->getDisplay());
    }

    public function testSkillCheckUsesDefaultSkillsWhenNoneProvided(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/skills/graphify', 0775, true);
        mkdir($workspace . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($baseline . '/abilities/skills/graphify/SKILL.md', "x\n");
        file_put_contents($workspace . '/.cursor/skills/graphify/SKILL.md', "x\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $cmd = new SkillCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertStringContainsString('skill', $tester->getDisplay());
    }

    public function testSkillCheckReturnsFailureOnUnknownTarget(): void
    {
        $workspace = $this->tmpDir . '/workspace';
        mkdir($workspace, 0775, true);
        chdir($workspace);

        $cmd = new SkillCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['graphify'], '--target' => ['unknown-ide']]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testSkillCheckReturnsExitCode2WhenMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/skills/graphify', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/abilities/skills/graphify/SKILL.md', "x\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $cmd = new SkillCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['graphify'], '--target' => ['cursor']]);

        self::assertSame(2, $exit);
        self::assertStringContainsString('miss', $tester->getDisplay());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }
        rmdir($dir);
    }
}
