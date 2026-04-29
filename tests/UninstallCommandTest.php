<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UninstallCommandTest extends TestCase
{
    public function testSkillUninstallRequiresForceWhenModified(): void
    {
        [$baseline, $workspace] = $this->prepareSkillFixture("base\n", "modified\n");
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new SkillUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['skills' => ['demo-skill'], '--target' => ['cursor']]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertFileExists($workspace . '/.cursor/skills/demo-skill/SKILL.md');
        self::assertStringContainsString('Re-run with --force', $tester->getDisplay());
    }

    public function testSkillUninstallWithForceDeletesModifiedItem(): void
    {
        [$baseline, $workspace] = $this->prepareSkillFixture("base\n", "modified\n");
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new SkillUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['skills' => ['demo-skill'], '--target' => ['cursor'], '--force' => true]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileDoesNotExist($workspace . '/.cursor/skills/demo-skill/SKILL.md');
    }

    public function testPresetUninstallRequiresForceWhenAnyItemModified(): void
    {
        [$baseline, $workspace] = $this->prepareSkillFixture("base\n", "modified\n");
        mkdir($workspace . '/abilities', 0775, true);
        file_put_contents(
            $workspace . '/abilities/_presets.json',
            json_encode([
                'custom-preset' => [
                    'skills' => ['demo-skill'],
                    'rules' => [],
                    'agents' => [],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new PresetUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['preset' => 'custom-preset', '--target' => ['cursor']]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Re-run with --force', $tester->getDisplay());
    }

    public function testRuleUninstallWithForceDeletesInstalledRule(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-uninstall-rule-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-uninstall-rule-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/abilities/rules/git/demo-rule.cursor.mdc', "rule-base\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-modified\n");
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new RuleUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['rules' => ['demo-rule'], '--target' => ['cursor'], '--force' => true]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileDoesNotExist($workspace . '/.cursor/rules/git/demo-rule.mdc');
    }

    public function testAgentUninstallWithForceDeletesInstalledAgent(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-uninstall-agent-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-uninstall-agent-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($baseline . '/abilities/agents/demo-agent.kiro.md', "agent-base\n");
        file_put_contents($workspace . '/.kiro/agents/demo-agent.md', "agent-modified\n");
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new AgentUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['agents' => ['demo-agent'], '--target' => ['kiro'], '--force' => true]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileDoesNotExist($workspace . '/.kiro/agents/demo-agent.md');
    }

    public function testRuleAndAgentUninstallRejectUnknownTarget(): void
    {
        $rule = new RuleUninstallCommand(new Installer(), new CheckService());
        $ruleTester = new CommandTester($rule);
        $ruleExit = $ruleTester->execute(['rules' => ['x'], '--target' => ['bad-target']]);
        self::assertSame(Command::FAILURE, $ruleExit);
        self::assertStringContainsString('Unknown targets', $ruleTester->getDisplay());

        $agent = new AgentUninstallCommand(new Installer(), new CheckService());
        $agentTester = new CommandTester($agent);
        $agentExit = $agentTester->execute(['agents' => ['x'], '--target' => ['bad-target']]);
        self::assertSame(Command::FAILURE, $agentExit);
        self::assertStringContainsString('Unknown targets', $agentTester->getDisplay());
    }

    public function testPresetUninstallWithForceRemovesMixedInstalledItems(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-uninstall-mixed-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-uninstall-mixed-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/demo-skill', 0775, true);
        mkdir($baseline . '/abilities/rules/git', 0775, true);
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/abilities', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo-skill/SKILL.md', "skill-base\n");
        file_put_contents($baseline . '/abilities/rules/git/demo-rule.cursor.mdc', "rule-base\n");
        file_put_contents($baseline . '/abilities/agents/demo-agent.cursor.md', "agent-base\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "skill-mod\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-mod\n");
        file_put_contents($workspace . '/.cursor/agents/demo-agent.md', "agent-mod\n");
        file_put_contents(
            $workspace . '/abilities/_presets.json',
            json_encode([
                'mixed-preset' => [
                    'skills' => ['demo-skill'],
                    'rules' => ['demo-rule'],
                    'agents' => ['demo-agent'],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $command = new PresetUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($command);
        $exit = $tester->execute(['preset' => 'mixed-preset', '--target' => ['cursor'], '--force' => true]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileDoesNotExist($workspace . '/.cursor/skills/demo-skill/SKILL.md');
        self::assertFileDoesNotExist($workspace . '/.cursor/rules/git/demo-rule.mdc');
        self::assertFileDoesNotExist($workspace . '/.cursor/agents/demo-agent.md');
    }

    /**
     * @return array{string, string}
     */
    private function prepareSkillFixture(string $baselineContent, string $installedContent): array
    {
        $baseline = sys_get_temp_dir() . '/apm-uninstall-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-uninstall-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo-skill/SKILL.md', $baselineContent);
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', $installedContent);

        return [$baseline, $workspace];
    }
}
