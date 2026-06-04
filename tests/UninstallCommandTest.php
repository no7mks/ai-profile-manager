<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;

final class UninstallCommandTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    public function testSkillUninstallRequiresForceWhenModified(): void
    {
        [$baseline, $workspace] = $this->prepareSkillFixture("base\n", "modified\n");
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $registry = new AbilityRegistry($baseline . '/abilities.yaml');
        $command = new SkillUninstallCommand(new Installer(registry: $registry), new CheckService());
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

        $registry = new AbilityRegistry($baseline . '/abilities.yaml');
        $command = new SkillUninstallCommand(new Installer(registry: $registry), new CheckService());
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

        // Create abilities.yaml at baseline for AbilityDiffService
        $baselineYaml = implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
        ]);
        file_put_contents($baseline . '/abilities.yaml', $baselineYaml . "\n");

        // Create abilities.yaml at workspace for PresetRegistry
        $workspaceYaml = implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
            'presets:',
            '  - name: custom-preset',
            '    description: custom-preset',
            '    includes:',
            '      - skill:demo-skill',
        ]);
        file_put_contents($workspace . '/abilities.yaml', $workspaceYaml . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $workspaceRegistry = new AbilityRegistry($workspace . '/abilities.yaml');
        $presetRegistry = new PresetRegistry($workspaceRegistry);
        $command = new PresetUninstallCommand(
            new Installer(registry: $workspaceRegistry),
            new CheckService(),
            $presetRegistry,
        );
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
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/demo-rule.mdc', "rule-base\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-modified\n");

        // abilities.yaml at baseline for AbilityDiffService
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'rules:',
            '  - path: demo-rule',
            '    description: demo-rule',
            '    targets:',
            '      cursor: .cursor/rules/git/demo-rule.mdc',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $registry = new AbilityRegistry($baseline . '/abilities.yaml');
        $command = new RuleUninstallCommand(new Installer(registry: $registry), new CheckService());
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
        mkdir($baseline . '/.kiro/agents', 0775, true);
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($baseline . '/.kiro/agents/demo-agent.md', "agent-base\n");
        file_put_contents($workspace . '/.kiro/agents/demo-agent.md', "agent-modified\n");

        // abilities.yaml at baseline for AbilityDiffService
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'agents:',
            '  - path: demo-agent',
            '    description: demo-agent',
            '    targets:',
            '      kiro: .kiro/agents/demo-agent.md',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $registry = new AbilityRegistry($baseline . '/abilities.yaml');
        $command = new AgentUninstallCommand(new Installer(registry: $registry), new CheckService());
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
        mkdir($baseline . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo-skill/SKILL.md', "skill-base\n");
        file_put_contents($baseline . '/.cursor/rules/git/demo-rule.mdc', "rule-base\n");
        file_put_contents($baseline . '/.cursor/agents/demo-agent.md', "agent-base\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "skill-mod\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-mod\n");
        file_put_contents($workspace . '/.cursor/agents/demo-agent.md', "agent-mod\n");

        // Create abilities.yaml at baseline for AbilityDiffService
        $baselineYaml = implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
            'rules:',
            '  - path: demo-rule',
            '    description: demo-rule',
            '    targets:',
            '      cursor: .cursor/rules/git/demo-rule.mdc',
            'agents:',
            '  - path: demo-agent',
            '    description: demo-agent',
            '    targets:',
            '      cursor: .cursor/agents/demo-agent.md',
        ]);
        file_put_contents($baseline . '/abilities.yaml', $baselineYaml . "\n");

        // Create abilities.yaml at workspace for PresetRegistry
        $workspaceYaml = implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
            'rules:',
            '  - path: demo-rule',
            '    description: demo-rule',
            '    targets:',
            '      cursor: .cursor/rules/git/demo-rule.mdc',
            'agents:',
            '  - path: demo-agent',
            '    description: demo-agent',
            '    targets:',
            '      cursor: .cursor/agents/demo-agent.md',
            'presets:',
            '  - name: mixed-preset',
            '    description: mixed-preset',
            '    includes:',
            '      - skill:demo-skill',
            '      - rule:demo-rule',
            '      - agent:demo-agent',
        ]);
        file_put_contents($workspace . '/abilities.yaml', $workspaceYaml . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $workspaceRegistry = new AbilityRegistry($workspace . '/abilities.yaml');
        $presetRegistry = new PresetRegistry($workspaceRegistry);
        $command = new PresetUninstallCommand(
            new Installer(registry: $workspaceRegistry),
            new CheckService(),
            $presetRegistry,
        );
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
        mkdir($baseline . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo-skill/SKILL.md', $baselineContent);
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', $installedContent);

        // abilities.yaml at baseline for AbilityDiffService
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
        ]) . "\n");

        return [$baseline, $workspace];
    }
}
