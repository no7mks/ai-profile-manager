<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TypedCheckCommandsTest extends TestCase
{
    public function testRuleCheckRuns(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-rcheck-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-rcheck-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.cursor/rules/spec', 0775, true);
        mkdir($workspace . '/.cursor/rules/spec', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/spec/kiro-spec-steering.mdc', "x\n");
        file_put_contents($workspace . '/.cursor/rules/spec/kiro-spec-steering.mdc', "x\n");

        // Create abilities.yaml in baseline root
        $yaml = <<<'YAML'
version: "1"
rules:
  - path: kiro-spec-steering
    description: test rule
    targets:
      cursor: .cursor/rules/spec/kiro-spec-steering.mdc
agents: []
skills: []
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $cmd = new RuleCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['kiro-spec-steering'], '--target' => ['cursor']]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(0, $exit);
        self::assertStringContainsString('rule', $tester->getDisplay());
    }

    public function testAgentCheckRuns(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-acheck-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-acheck-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.kiro/agents', 0775, true);
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($baseline . '/.kiro/agents/spec-gatekeeper.md', "x\n");
        file_put_contents($workspace . '/.kiro/agents/spec-gatekeeper.md', "x\n");

        // Create abilities.yaml in baseline root
        $yaml = <<<'YAML'
version: "1"
rules: []
agents:
  - path: spec-gatekeeper
    description: test agent
    targets:
      kiro: .kiro/agents/spec-gatekeeper.md
skills: []
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $cmd = new AgentCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['spec-gatekeeper'], '--target' => ['kiro']]);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(0, $exit);
        self::assertStringContainsString('agent', $tester->getDisplay());
    }
}
