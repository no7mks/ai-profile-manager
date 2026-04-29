<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentCaptureCommand;
use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\RuleCaptureCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TypedCaptureCheckCommandsTest extends TestCase
{
    public function testRuleCheckRuns(): void
    {
        $cmd = new RuleCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['kiro-spec-steering'], '--target' => ['cursor']]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('rule', $tester->getDisplay());
    }

    public function testAgentCheckRuns(): void
    {
        $cmd = new AgentCheckCommand(new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['spec-gatekeeper'], '--target' => ['kiro']]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('agent', $tester->getDisplay());
    }

    public function testRuleCaptureUnknownTargetFails(): void
    {
        $cmd = new RuleCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['r'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testAgentCaptureBaselineMissingFails(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-no-apm-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['packages' => []]));

        $oldCh = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $cmd = new AgentCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['spec-gatekeeper'], '--target' => ['cursor']]);

        if ($oldCh === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldCh);
        }
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Composer baseline', $tester->getDisplay());
    }

    public function testRuleCaptureNoChangesVsBaseline(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-rcap-bl-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/apm-rcap-ws-' . bin2hex(random_bytes(4));
        $rule = 'demo-rule';
        mkdir($baseline . '/abilities/rules/' . $rule . '/cursor', 0775, true);
        mkdir($ws . '/abilities/rules/' . $rule . '/cursor', 0775, true);
        file_put_contents($baseline . '/abilities/rules/' . $rule . '/cursor/RULE.mdc', "x\n");
        file_put_contents($ws . '/abilities/rules/' . $rule . '/cursor/RULE.mdc', "x\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ws);

        $cmd = new RuleCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => [$rule], '--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(0, $exit);
    }
}
