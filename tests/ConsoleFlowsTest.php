<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\InitCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\UpdateCommand;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleFlowsTest extends TestCase
{
    public function testInstallCommandInstallsKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-flow-i-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new InstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['cursor']]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: gitflow', $tester->getDisplay());
    }

    public function testInstallCommandUnknownPresetFails(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-flow-i2-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new InstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'no-such-preset']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testInstallCommandUnknownTargetsFails(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-flow-itgt-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new InstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['not-a-target']]);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testCheckCommandRunsForKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-flow-ch-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new CheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['cursor']]);

        chdir($old);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Preset: gitflow', $tester->getDisplay());
    }

    public function testCaptureCommandRejectsUnknownTarget(): void
    {
        $capture = new CaptureService(new CheckService());
        $cmd = new CaptureCommand($capture);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['not-a-target']]);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testCaptureFullWorkspaceWithNoAbilitiesExitsSuccess(): void
    {
        $baseline = sys_get_temp_dir() . '/aipm-cap-bl-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/aipm-cap-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($ws, 0775, true);

        $oldBl = getenv('AIPM_BASELINE_ROOT');
        putenv('AIPM_BASELINE_ROOT=' . $baseline);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ws);

        $capture = new CaptureService(new CheckService());
        $cmd = new CaptureCommand($capture);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([]);

        chdir($old);
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Full workspace snapshot', $tester->getDisplay());
    }

    public function testSkillInstallUsesDefaultSkillsWhenArgumentEmpty(): void
    {
        $cmd = new SkillInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => [], '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('graphify', $tester->getDisplay());
    }

    public function testRuleInstallInstallsNamedRule(): void
    {
        $cmd = new RuleInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['spec-core'], '--target' => ['kiro']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('spec-core', $tester->getDisplay());
    }

    public function testAgentInstallInstallsNamedAgent(): void
    {
        $cmd = new AgentInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['gatekeeper'], '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('gatekeeper', $tester->getDisplay());
    }

    public function testUpdateCommandWritesKnowledgeBase(): void
    {
        $home = sys_get_temp_dir() . '/aipm-flow-up-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);

        $oldHome = getenv('HOME');
        try {
            putenv('HOME=' . $home);

            $cmd = new UpdateCommand(new KnowledgeBaseUpdater());
            $tester = new CommandTester($cmd);
            $exit = $tester->execute([]);
        } finally {
            if ($oldHome === false) {
                putenv('HOME');
            } else {
                putenv('HOME=' . $oldHome);
            }
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($home . '/.config/aipm/knowledge-base.json');
    }

    public function testPresetCreateFailsWhenPresetAlreadyExists(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-pc-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetCreateCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['name' => 'gitflow']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testPresetAddAbilityRequiresExactlyOneKindFlag(): void
    {
        $cmd = new PresetAddAbilityCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', 'ability' => 'x']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('exactly one', $tester->getDisplay());
    }

    public function testPresetDeleteFailsForUnknownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-pdel-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetDeleteCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['name' => 'nonexistent-preset']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    public function testInitCommandUnknownTargetFails(): void
    {
        $cmd = new InitCommand(ProjectInitializer::fromPackageLayout());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['not-a-target']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testInitCommandInstallsIntoPath(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-cli-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $cmd = new InitCommand(ProjectInitializer::fromPackageLayout());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['path' => $tmp]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($tmp . '/AGENTS.md');
        self::assertFileExists($tmp . '/PROJECT.md');
        foreach (AppConfig::DEFAULT_TARGETS as $t) {
            self::assertStringContainsString($t, $tester->getDisplay());
        }
    }
}
