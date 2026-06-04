<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Core\ConsoleRegistration;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RemovesDirTrait;

/**
 * Tests for command error paths: invalid inputs, missing presets, and ConsoleRegistration with presetRegistry.
 */
final class CommandErrorPathsTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-cov-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── ConsoleRegistration with presetRegistry (lines 51-54) ────────

    public function testRegisterWithPresetRegistryPassesItToCommands(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, "skills: []\npresets: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $installer = new Installer(registry: $registry, packageRoot: $this->tmpDir);
        $checker = new CheckService();
        $presetRegistry = new PresetRegistry($registry);

        $app = new Application();
        ConsoleRegistration::register($app, $installer, $checker, $presetRegistry);

        // Verify commands are registered (the presetRegistry branch was exercised)
        self::assertTrue($app->has('install'));
        self::assertTrue($app->has('show'));
        self::assertTrue($app->has('preset:uninstall'));
        self::assertTrue($app->has('check'));
    }

    // ─── PresetCreateCommand: error path (duplicate name) ─────────────

    public function testPresetCreateCommandFailsOnDuplicateName(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, "skills: []\nrules: []\nagents: []\nhooks: []\npresets:\n  - name: existing\n    description: test\n    includes: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new PresetCreateCommand($presetRegistry);
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['name' => 'existing', '--skill' => ['apm']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    // ─── PresetDeleteCommand: error path (not found) ──────────────────

    public function testPresetDeleteCommandFailsOnNonexistentName(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, "skills: []\nrules: []\nagents: []\nhooks: []\npresets: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new PresetDeleteCommand($presetRegistry);
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['name' => 'definitely-not-a-real-preset-' . bin2hex(random_bytes(4))]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    // ─── PresetAddAbilityCommand: error path (no type flag) ───────────

    public function testPresetAddAbilityCommandFailsWithoutTypeFlag(): void
    {
        $cmd = new PresetAddAbilityCommand();
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['preset' => 'test', 'ability' => 'something']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Specify exactly one', $tester->getDisplay());
    }

    public function testPresetAddAbilityCommandFailsWithMultipleTypeFlags(): void
    {
        $cmd = new PresetAddAbilityCommand();
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['preset' => 'test', 'ability' => 'something', '--skill' => true, '--rule' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Specify exactly one', $tester->getDisplay());
    }

    public function testPresetAddAbilityCommandFailsWhenRegistryThrows(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, "skills: []\nrules: []\nagents: []\nhooks: []\npresets: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new PresetAddAbilityCommand($presetRegistry);
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        // Use a nonexistent ability to trigger RuntimeException from PresetRegistry
        $tester->execute(['preset' => 'nonexistent-preset', 'ability' => 'fake', '--skill' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // ─── PresetRemoveAbilityCommand: error path (no type flag) ────────

    public function testPresetRemoveAbilityCommandFailsWithoutTypeFlag(): void
    {
        $cmd = new PresetRemoveAbilityCommand();
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['preset' => 'test', 'ability' => 'something']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Specify exactly one', $tester->getDisplay());
    }

    public function testPresetRemoveAbilityCommandFailsWhenRegistryThrows(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, "skills: []\nrules: []\nagents: []\nhooks: []\npresets: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new PresetRemoveAbilityCommand($presetRegistry);
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        $tester->execute(['preset' => 'nonexistent-preset', 'ability' => 'fake', '--agent' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

}
