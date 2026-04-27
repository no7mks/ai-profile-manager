<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\CaptureService;
use AiProfileManager\CheckService;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\SkillCheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandCheckCaptureTest extends TestCase
{
    public function testPresetCheckFailsForUnknownPreset(): void
    {
        $command = new CheckCommand(new CheckService());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['preset' => 'unknown-preset']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    public function testSkillCheckFailsForUnknownTarget(): void
    {
        $command = new SkillCheckCommand(new CheckService());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['skills' => ['graphify'], '--target' => ['invalid-target']]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testCaptureServicePlaceholderReturnsNoModifiedMessage(): void
    {
        $service = new CaptureService(new CheckService());
        $result = $service->captureTyped([
            'skills' => ['graphify'],
            'rules' => [],
            'agents' => [],
        ], ['cursor']);

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('No modified items to capture', implode("\n", $result['lines']));
    }
}
