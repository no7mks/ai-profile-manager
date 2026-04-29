<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Command\CheckCommand;
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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleFlowsTest extends TestCase
{
    public function testInstallCommandInstallsKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-i-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/abilities/gitignore', 0775, true);
        file_put_contents($tmp . '/abilities/gitignore/template.gitignore', implode("\n", [
            '## @apm:block ability=gitflow target=*',
            '/.apm/gitflow/',
            '## @apm:end',
        ]));
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new InstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['cursor']]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: gitflow', $tester->getDisplay());
        self::assertFileExists($tmp . '/.gitignore');
        self::assertStringContainsString('/.apm/gitflow/', (string) file_get_contents($tmp . '/.gitignore'));
    }

    public function testInstallCommandUnknownPresetFails(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-i2-' . bin2hex(random_bytes(4));
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
        $tmp = sys_get_temp_dir() . '/apm-flow-itgt-' . bin2hex(random_bytes(4));
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

    public function testInstallCommandWithoutPresetRunsBootstrap(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-bootstrap-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new InstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($tmp . '/docs/README.md');
        self::assertDirectoryExists($tmp . '/docs/state');
        self::assertFileExists($tmp . '/issues/README.md');
        self::assertFileExists($tmp . '/AGENTS.md');
        self::assertFileExists($tmp . '/.cursor/skills/apm/SKILL.md');
        self::assertFileExists($tmp . '/.kiro/skills/apm/SKILL.md');
        self::assertStringContainsString("/apm init", $tester->getDisplay());
    }

    public function testCheckCommandRunsForKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-ch-' . bin2hex(random_bytes(4));
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
        $baseline = sys_get_temp_dir() . '/apm-cap-bl-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/apm-cap-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($ws, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ws);

        $capture = new CaptureService(new CheckService());
        $cmd = new CaptureCommand($capture);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Full workspace snapshot', $tester->getDisplay());
    }

    public function testSkillInstallUsesDefaultSkillsWhenArgumentEmpty(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-skill-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $cmd = new SkillInstallCommand(new Installer());
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['skills' => [], '--target' => ['cursor']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('graphify', $tester->getDisplay());
    }

    public function testRuleInstallInstallsNamedRule(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-rule-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $cmd = new RuleInstallCommand(new Installer());
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['rules' => ['spec-core'], '--target' => ['kiro']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('spec-core', $tester->getDisplay());
    }

    public function testAgentInstallInstallsNamedAgent(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-agent-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $cmd = new AgentInstallCommand(new Installer());
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['agents' => ['code-reviewer'], '--target' => ['cursor']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('code-reviewer', $tester->getDisplay());
    }

    public function testUpdateCommandWritesKnowledgeBase(): void
    {
        $home = sys_get_temp_dir() . '/apm-flow-up-' . bin2hex(random_bytes(4));
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
        self::assertFileExists($home . '/.config/apm/knowledge-base.json');
    }

    public function testPresetCreateFailsWhenPresetAlreadyExists(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pc-' . bin2hex(random_bytes(4));
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
        $tmp = sys_get_temp_dir() . '/apm-pdel-' . bin2hex(random_bytes(4));
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

}
