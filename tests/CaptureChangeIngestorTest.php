<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureChangeIngestor;
use AiProfileManager\Capture\CaptureChangeSchema;
use AiProfileManager\Capture\CaptureWriteBackService;
use PHPUnit\Framework\TestCase;

final class CaptureChangeIngestorTest extends TestCase
{
    private function validPayload(string $changeId): array
    {
        return [
            'schema_version' => 2,
            'change_id' => $changeId,
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
        mkdir($base . '/changes', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        file_put_contents($base . '/changes/broken.json', '{');

        $ingestor = new CaptureChangeIngestor();
        $result = $ingestor->ingestChanges($base . '/changes', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        self::assertSame(1, $result['exit_code']);
        self::assertTrue(is_file($base . '/failed-changes/broken.json'));
        self::assertStringContainsString('[fail] Invalid JSON', implode("\n", $result['lines']));
    }

    public function testDuplicateChangeIsSkipped(): void
    {
        $base = sys_get_temp_dir() . '/aipm-ing2-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($base . '/changes', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        $changeId = '44444444-4444-4444-8444-444444444444';
        $json = json_encode($this->validPayload($changeId), JSON_UNESCAPED_SLASHES);
        file_put_contents($base . '/changes/a.json', $json);
        file_put_contents($base . '/changes/b.json', $json);

        $ingestor = new CaptureChangeIngestor();
        $result = $ingestor->ingestChanges($base . '/changes', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        $text = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip] Duplicate change', $text);
        self::assertSame(0, $result['exit_code']);
    }

    public function testSchemaInvalidPayloadMovesToFailed(): void
    {
        $base = sys_get_temp_dir() . '/aipm-ing3-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($base . '/changes', 0775, true);

        $old = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $base);

        file_put_contents($base . '/changes/bad-schema.json', json_encode([
            'schema_version' => 2,
            'change_id' => '77777777-7777-4777-8777-777777777777',
            'source_repo' => 'x',
            'source_commit' => 'y',
            'base_ref' => '',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'items' => [],
        ]));

        $ingestor = new CaptureChangeIngestor();
        $result = $ingestor->ingestChanges($base . '/changes', false);

        if ($old === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $old);
        }

        self::assertSame(1, $result['exit_code']);
        self::assertTrue(is_file($base . '/failed-changes/bad-schema.json'));
        self::assertStringContainsString('[fail] Schema invalid', implode("\n", $result['lines']));
    }

    public function testIngestWithWriteBackUsesExplicitDependencies(): void
    {
        $cfgDir = sys_get_temp_dir() . '/aipm-ing-wb-' . bin2hex(random_bytes(4));
        mkdir($cfgDir, 0775, true);
        mkdir($cfgDir . '/changes', 0775, true);

        $ws = sys_get_temp_dir() . '/aipm-ing-ws-' . bin2hex(random_bytes(4));
        mkdir($ws . '/abilities/skills/wbtest/cursor', 0775, true);

        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $cfgDir);

        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($ws);

        $payload = $this->validPayload('88888888-8888-4888-8888-888888888888');
        $payload['items'][0]['name'] = 'wbtest';
        file_put_contents($cfgDir . '/changes/wb.json', json_encode($payload, JSON_UNESCAPED_SLASHES));

        $ingestor = new CaptureChangeIngestor(new CaptureChangeSchema(), new CaptureWriteBackService());
        $result = $ingestor->ingestChanges($cfgDir . '/changes', true);

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
