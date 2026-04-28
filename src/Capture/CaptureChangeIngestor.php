<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

final class CaptureChangeIngestor
{
    public function __construct(
        private readonly CaptureChangeSchema $schema = new CaptureChangeSchema(),
        private readonly CaptureWriteBackService $writeBack = new CaptureWriteBackService(),
    ) {
    }

    /**
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function ingestChanges(
        ?string $changesDir = null,
        bool $writeBack = false
    ): array {
        $baseDir = (string) (getenv('AIPM_HOME') ?: (rtrim((string) getenv('HOME'), '/') . '/.aipm'));
        $changes = $changesDir ?? ($baseDir . '/changes');
        $processed = $baseDir . '/processed-changes';
        $failed = $baseDir . '/failed-changes';
        $auditPath = $baseDir . '/changes.jsonl';
        $indexPath = $baseDir . '/processed-change-ids.json';

        foreach ([$baseDir, $changes, $processed, $failed] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        $seen = [];
        if (is_file($indexPath)) {
            $decoded = json_decode((string) file_get_contents($indexPath), true);
            if (is_array($decoded)) {
                $seen = $decoded;
            }
        }

        $files = glob($changes . '/*.json') ?: [];
        sort($files);
        $lines = ['Changes dir: ' . $changes, 'Found changes: ' . (string) count($files)];
        $hasFailure = false;

        foreach ($files as $file) {
            $body = (string) file_get_contents($file);
            $payload = json_decode($body, true);
            if (!is_array($payload)) {
                $hasFailure = true;
                $lines[] = '[fail] Invalid JSON: ' . basename($file);
                rename($file, $failed . '/' . basename($file));
                continue;
            }

            $validation = $this->schema->validate($payload);
            if (!$validation['valid']) {
                $hasFailure = true;
                $lines[] = '[fail] Schema invalid: ' . basename($file);
                rename($file, $failed . '/' . basename($file));
                continue;
            }

            $changeKey = $payload['source_repo'] . '::' . $payload['change_id'];
            if (in_array($changeKey, $seen, true)) {
                $lines[] = '[skip] Duplicate change: ' . $changeKey;
                rename($file, $processed . '/' . basename($file));
                continue;
            }

            file_put_contents($auditPath, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
            if ($writeBack) {
                $lines = array_merge($lines, $this->writeBack->writeBack($payload));
            }

            $seen[] = $changeKey;
            file_put_contents($indexPath, json_encode(array_values(array_unique($seen)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            rename($file, $processed . '/' . basename($file));
            $lines[] = '[ok] Ingested change: ' . $changeKey;
        }

        return ['lines' => $lines, 'exit_code' => $hasFailure ? 1 : 0];
    }
}
