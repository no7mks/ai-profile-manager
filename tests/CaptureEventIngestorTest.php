<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureEventIngestor;
use AiProfileManager\Capture\CaptureEventSchema;
use AiProfileManager\Capture\CaptureWriteBackService;
use PHPUnit\Framework\TestCase;

final class CaptureEventIngestorTest extends TestCase
{
    private function validPayload(string $eventId): array
    {
        return [
            'schema_version' => 2,
            'event_id' => $eventId,
            'source_repo' => 'acme/p',
            'source_commit' => 'sha',
            'base_ref' => 'v1',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'baseline' => [
                'package' => 'no7mks/ai-profile-manager',
                'version' => '1.0.0',
                'install_path' => '/tmp/x',
            ],
            'items' => [[
                'type' => 'skill',
                'name' => 'n',
                'status' => 'modified',
                'content_hash' => 'h',
                'files' => [['path' => 'SKILL.md', 'content' => 'c', 'patch' => 'p']],
            ]],
        ];
    }

    public function testInvalidJsonMovesToFailedAndReturnsFailureExit(): void
    {
        $base = sys_get_temp_dir() . '/aipm-ing-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($base . '/events', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        file_put_contents($base . '/events/broken.json', '{');

        $ingestor = new CaptureEventIngestor();
        $result = $ingestor->ingestEvents($base . '/events', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        self::assertSame(1, $result['exit_code']);
        self::assertTrue(is_file($base . '/failed-events/broken.json'));
        self::assertStringContainsString('[fail] Invalid JSON', implode("\n", $result['lines']));
    }

    public function testDuplicateEventIsSkipped(): void
    {
        $base = sys_get_temp_dir() . '/aipm-ing2-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($base . '/events', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        $eventId = '44444444-4444-4444-8444-444444444444';
        $json = json_encode($this->validPayload($eventId), JSON_UNESCAPED_SLASHES);
        file_put_contents($base . '/events/a.json', $json);
        file_put_contents($base . '/events/b.json', $json);

        $ingestor = new CaptureEventIngestor();
        $result = $ingestor->ingestEvents($base . '/events', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        $text = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip] Duplicate event', $text);
        self::assertSame(0, $result['exit_code']);
    }

    public function testSchemaInvalidPayloadMovesToFailed(): void
    {
        $base = sys_get_temp_dir() . '/aipm-ing3-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($base . '/events', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        file_put_contents($base . '/events/bad-schema.json', json_encode([
            'schema_version' => 2,
            'event_id' => '77777777-7777-4777-8777-777777777777',
            'source_repo' => 'x',
            'source_commit' => 'y',
            'base_ref' => '',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'items' => [],
        ]));

        $ingestor = new CaptureEventIngestor();
        $result = $ingestor->ingestEvents($base . '/events', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        self::assertSame(1, $result['exit_code']);
        self::assertTrue(is_file($base . '/failed-events/bad-schema.json'));
        self::assertStringContainsString('[fail] Schema invalid', implode("\n", $result['lines']));
    }

    public function testIngestWithWriteBackUsesExplicitDependencies(): void
    {
        $cfgDir = sys_get_temp_dir() . '/aipm-ing-wb-' . bin2hex(random_bytes(4));
        mkdir($cfgDir, 0775, true);
        mkdir($cfgDir . '/events', 0775, true);

        $ws = sys_get_temp_dir() . '/aipm-ing-ws-' . bin2hex(random_bytes(4));
        mkdir($ws . '/abilities/skills/wbtest/cursor', 0775, true);

        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $cfgDir);

        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($ws);

        $payload = $this->validPayload('88888888-8888-4888-8888-888888888888');
        $payload['items'][0]['name'] = 'wbtest';
        file_put_contents($cfgDir . '/events/wb.json', json_encode($payload, JSON_UNESCAPED_SLASHES));

        $ingestor = new CaptureEventIngestor(new CaptureEventSchema(), new CaptureWriteBackService());
        $result = $ingestor->ingestEvents($cfgDir . '/events', true);

        chdir($oldCwd);
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $oldHome);
        }

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString('Write-back item file', implode("\n", $result['lines']));
        self::assertFileExists($ws . '/abilities/skills/wbtest/cursor/SKILL.md');
    }
}
