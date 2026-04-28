<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureEventIngestor;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Command\AgentCaptureCommand;
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
        $tmpBaseline = sys_get_temp_dir() . '/aipm-bl-' . bin2hex(random_bytes(4));
        mkdir($tmpBaseline . '/abilities/skills/graphify/cursor', 0775, true);
        file_put_contents($tmpBaseline . '/abilities/skills/graphify/cursor/SKILL.md', "same\n");

        putenv('AIPM_BASELINE_ROOT=' . $tmpBaseline);

        $service = new CaptureService(new CheckService());
        $workspace = $tmpBaseline;
        $result = $service->captureTyped([
            'skills' => ['graphify'],
            'rules' => [],
            'agents' => [],
        ], ['cursor'], $workspace);

        putenv('AIPM_BASELINE_ROOT');

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('No changes vs Composer baseline', implode("\n", $result['lines']));
        self::assertSame([], $result['results']);
        self::assertNotNull($result['baseline']);
    }

    public function testSkillCaptureWritesEventToEventsDirByDefault(): void
    {
        $tmpBaseline = sys_get_temp_dir() . '/aipm-cap-base-' . bin2hex(random_bytes(4));
        $tmpWorkspace = sys_get_temp_dir() . '/aipm-cap-ws-' . bin2hex(random_bytes(4));
        mkdir($tmpBaseline . '/abilities/skills/graphify/cursor', 0775, true);
        mkdir($tmpWorkspace . '/abilities/skills/graphify/cursor', 0775, true);
        file_put_contents($tmpBaseline . '/abilities/skills/graphify/cursor/SKILL.md', "baseline\n");
        file_put_contents($tmpWorkspace . '/abilities/skills/graphify/cursor/SKILL.md', "workspace\n");

        $tmpAipmHome = sys_get_temp_dir() . '/aipm-capture-test-' . bin2hex(random_bytes(4));
        mkdir($tmpAipmHome, 0775, true);
        putenv('AIPM_HOME=' . $tmpAipmHome);
        putenv('AIPM_BASELINE_ROOT=' . $tmpBaseline);

        $originalCwd = getcwd();
        self::assertNotFalse($originalCwd);
        chdir($tmpWorkspace);

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

        chdir($originalCwd);
        putenv('AIPM_HOME');
        putenv('AIPM_BASELINE_ROOT');

        self::assertSame(2, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Event written to events dir', $output);
        $eventPath = $tmpAipmHome . '/events/11111111-1111-4111-8111-111111111111.json';
        self::assertFileExists($eventPath);
        $decoded = json_decode((string) file_get_contents($eventPath), true);
        self::assertIsArray($decoded);
        self::assertSame(2, $decoded['schema_version'] ?? null);
        self::assertIsArray($decoded['baseline'] ?? null);
        self::assertSame('no7mks/ai-profile-manager', $decoded['baseline']['package'] ?? null);
        self::assertIsArray($decoded['items'][0]['files'] ?? null);
        self::assertIsString($decoded['items'][0]['files'][0]['patch'] ?? null);
    }

    public function testSkillCaptureGeneratesUuidEventFileWhenEventIdOmitted(): void
    {
        $tmpBaseline = sys_get_temp_dir() . '/aipm-cap-id-' . bin2hex(random_bytes(4));
        $tmpWorkspace = sys_get_temp_dir() . '/aipm-cap-id-ws-' . bin2hex(random_bytes(4));
        mkdir($tmpBaseline . '/abilities/skills/graphify/cursor', 0775, true);
        mkdir($tmpWorkspace . '/abilities/skills/graphify/cursor', 0775, true);
        file_put_contents($tmpBaseline . '/abilities/skills/graphify/cursor/SKILL.md', "b\n");
        file_put_contents($tmpWorkspace . '/abilities/skills/graphify/cursor/SKILL.md', "w\n");

        $tmpAipmHome = sys_get_temp_dir() . '/aipm-cap-id-home-' . bin2hex(random_bytes(4));
        mkdir($tmpAipmHome, 0775, true);
        putenv('AIPM_HOME=' . $tmpAipmHome);
        putenv('AIPM_BASELINE_ROOT=' . $tmpBaseline);

        $originalCwd = getcwd();
        self::assertNotFalse($originalCwd);
        chdir($tmpWorkspace);

        $command = new SkillCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'skills' => ['graphify'],
            '--target' => ['cursor'],
            '--source-repo' => 'acme/project',
            '--source-commit' => 'abc123',
            '--captured-at' => '2026-04-27T00:00:00Z',
        ]);

        chdir($originalCwd);
        putenv('AIPM_HOME');
        putenv('AIPM_BASELINE_ROOT');

        self::assertSame(2, $exitCode);
        $files = glob($tmpAipmHome . '/events/*.json') ?: [];
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.json$/i',
            basename((string) $files[0]),
        );
    }

    public function testAgentCaptureWritesEventWhenAgentDiffersFromBaseline(): void
    {
        $name = 'gatekeeper';
        $tmpBaseline = sys_get_temp_dir() . '/aipm-ag-bl-' . bin2hex(random_bytes(4));
        $tmpWorkspace = sys_get_temp_dir() . '/aipm-ag-ws-' . bin2hex(random_bytes(4));
        mkdir($tmpBaseline . '/abilities/agents/' . $name . '/cursor', 0775, true);
        mkdir($tmpWorkspace . '/abilities/agents/' . $name . '/cursor', 0775, true);
        file_put_contents($tmpBaseline . '/abilities/agents/' . $name . '/cursor/agent.md', "b\n");
        file_put_contents($tmpWorkspace . '/abilities/agents/' . $name . '/cursor/agent.md', "w\n");

        $tmpAipmHome = sys_get_temp_dir() . '/aipm-ag-home-' . bin2hex(random_bytes(4));
        mkdir($tmpAipmHome, 0775, true);
        putenv('AIPM_HOME=' . $tmpAipmHome);
        putenv('AIPM_BASELINE_ROOT=' . $tmpBaseline);

        $originalCwd = getcwd();
        self::assertNotFalse($originalCwd);
        chdir($tmpWorkspace);

        $command = new AgentCaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'agents' => [$name],
            '--target' => ['cursor'],
            '--source-repo' => 'acme/project',
            '--source-commit' => 'abc123',
            '--event-id' => '99999999-9999-4999-8999-999999999999',
        ]);

        chdir($originalCwd);
        putenv('AIPM_HOME');
        putenv('AIPM_BASELINE_ROOT');

        self::assertSame(2, $exitCode);
        self::assertFileExists($tmpAipmHome . '/events/99999999-9999-4999-8999-999999999999.json');
        $decoded = json_decode((string) file_get_contents($tmpAipmHome . '/events/99999999-9999-4999-8999-999999999999.json'), true);
        self::assertSame('agent', $decoded['items'][0]['type'] ?? null);
    }

    public function testIngestCaptureEventScansInboxAndIngestsEvent(): void
    {
        $payload = json_encode([
            'schema_version' => 2,
            'event_id' => '22222222-2222-4222-8222-222222222222',
            'source_repo' => 'acme/project',
            'source_commit' => 'abc123',
            'base_ref' => 'v1.2.3',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'baseline' => [
                'package' => 'no7mks/ai-profile-manager',
                'version' => '1.0.0',
                'install_path' => '/tmp/composer/vendor/no7mks/ai-profile-manager',
            ],
            'items' => [[
                'type' => 'skill',
                'name' => 'graphify',
                'status' => 'modified',
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
