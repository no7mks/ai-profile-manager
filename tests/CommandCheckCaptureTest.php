<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureEventIngestor;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\IngestCaptureEventCommand;
use AiProfileManager\Command\SkillCaptureCommand;
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
        self::assertArrayHasKey('content_hash', $result['results'][0]);
    }

    public function testSkillCaptureWritesEventToEventsDirByDefault(): void
    {
        $tmpAipmHome = sys_get_temp_dir() . '/aipm-capture-test-' . bin2hex(random_bytes(4));
        mkdir($tmpAipmHome, 0775, true);
        putenv('AIPM_HOME=' . $tmpAipmHome);

        $command = new SkillCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'skills' => ['graphify'],
            '--target' => ['cursor'],
            '--source-repo' => 'acme/project',
            '--source-commit' => 'abc123',
            '--event-id' => '11111111-1111-4111-8111-111111111111',
            '--captured-at' => '2026-04-27T00:00:00Z',
        ]);

        putenv('AIPM_HOME');

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Event written to events dir', $output);
        $eventPath = $tmpAipmHome . '/events/11111111-1111-4111-8111-111111111111.json';
        self::assertFileExists($eventPath);
        $decoded = json_decode((string) file_get_contents($eventPath), true);
        self::assertIsArray($decoded);
        self::assertSame('unknown', $decoded['base_ref'] ?? null);
        self::assertIsArray($decoded['items'][0]['files'] ?? null);
        self::assertIsString($decoded['items'][0]['files'][0]['patch'] ?? null);
    }

    public function testIngestCaptureEventScansInboxAndIngestsEvent(): void
    {
        $payload = json_encode([
            'schema_version' => 1,
            'event_id' => '22222222-2222-4222-8222-222222222222',
            'source_repo' => 'acme/project',
            'source_commit' => 'abc123',
            'base_ref' => 'v1.2.3',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'items' => [[
                'type' => 'skill',
                'name' => 'graphify',
                'status' => 'unknown',
                'content_hash' => hash('sha256', 'x'),
                'files' => [[
                    'path' => 'SKILL.md',
                    'content' => "# graphify\n\nskill content\n",
                    'patch' => "--- a/SKILL.md\n+++ b/SKILL.md\n@@ -0,0 +1,3 @@\n+# graphify\n+\n+skill content\n",
                ]],
            ]],
        ], JSON_UNESCAPED_SLASHES);
        self::assertIsString($payload);

        $tmpConfigDir = sys_get_temp_dir() . '/aipm-test-' . bin2hex(random_bytes(4));
        mkdir($tmpConfigDir, 0775, true);
        $eventsDir = $tmpConfigDir . '/events';
        mkdir($eventsDir, 0775, true);
        putenv('AIPM_HOME=' . $tmpConfigDir);
        file_put_contents($eventsDir . '/22222222-2222-4222-8222-222222222222.json', $payload);
        $originalCwd = getcwd();
        self::assertNotFalse($originalCwd);
        chdir($tmpConfigDir);

        $command = new IngestCaptureEventCommand(new CaptureEventIngestor());
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--events-dir' => $eventsDir,
        ]);

        chdir($originalCwd);
        putenv('AIPM_HOME');

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Found events: 1', $tester->getDisplay());
        self::assertStringContainsString('[ok] Ingested event', $tester->getDisplay());
        self::assertFileExists($tmpConfigDir . '/abilities/skills/graphify/cursor/SKILL.md');
    }
}
