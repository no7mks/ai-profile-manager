<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandEdgeCasesTest extends TestCase
{
    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-cmd-edge-' . bin2hex(random_bytes(4));
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

    // ─── Unknown target paths ────────────────────────────────────────

    public function testAgentCheckRejectsUnknownTarget(): void
    {
        $cmd = new AgentCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testRuleCheckRejectsUnknownTarget(): void
    {
        $cmd = new RuleCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testSkillUninstallRejectsUnknownTarget(): void
    {
        $cmd = new SkillUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testPresetUninstallRejectsUnknownTarget(): void
    {
        chdir($this->tmpDir);
        $cmd = new PresetUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'x', '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testPresetUninstallRejectsUnknownPreset(): void
    {
        chdir($this->tmpDir);
        $cmd = new PresetUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'nonexistent', '--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    // ─── PresetAddAbility edge cases ─────────────────────────────────

    public function testPresetAddAbilityRejectsUnknownPreset(): void
    {
        chdir($this->tmpDir);
        $cmd = new PresetAddAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'nonexistent',
            'ability' => 'demo',
            '--skill' => true,
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testPresetAddAbilityRejectsNoTypeFlag(): void
    {
        mkdir($this->tmpDir . '/abilities', 0775, true);
        file_put_contents($this->tmpDir . '/abilities/_presets.json', json_encode([
            'my-preset' => ['skills' => [], 'rules' => [], 'agents' => []],
        ]));
        chdir($this->tmpDir);

        $cmd = new PresetAddAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'my-preset',
            'ability' => 'demo',
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Specify exactly one', $tester->getDisplay());
    }

    // ─── PresetRemoveAbility edge cases ──────────────────────────────

    public function testPresetRemoveAbilityRejectsUnknownPreset(): void
    {
        chdir($this->tmpDir);
        $cmd = new PresetRemoveAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'nonexistent',
            'ability' => 'demo',
            '--skill' => true,
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testPresetRemoveAbilityRejectsNoTypeFlag(): void
    {
        mkdir($this->tmpDir . '/abilities', 0775, true);
        file_put_contents($this->tmpDir . '/abilities/_presets.json', json_encode([
            'my-preset' => ['skills' => ['demo'], 'rules' => [], 'agents' => []],
        ]));
        chdir($this->tmpDir);

        $cmd = new PresetRemoveAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'my-preset',
            'ability' => 'demo',
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Specify exactly one', $tester->getDisplay());
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
