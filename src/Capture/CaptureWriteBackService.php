<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

final class CaptureWriteBackService
{
    /**
     * @param array{
     *     event_id: string,
     *     source_repo: string,
     *     source_commit: string,
     *     captured_at: string
     * } $event
     * @return array<int, string>
     */
    public function writeBack(array $event): array
    {
        $lines = [];
        $sourceDir = $this->resolveSourceDir();
        $abilitiesDir = $sourceDir . '/abilities';
        if (!is_dir($abilitiesDir)) {
            mkdir($abilitiesDir, 0775, true);
        }

        $items = $event['items'] ?? [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $typeDir = match (($item['type'] ?? '')) {
                'skill' => 'abilities/skills',
                'rule' => 'abilities/rules',
                'agent' => 'abilities/agents',
                default => 'abilities/unknown-items',
            };
            $name = $this->sanitizePathSegment((string) ($item['name'] ?? 'unknown'));
            $itemDir = $sourceDir . '/' . $typeDir . '/' . $name;
            if (!is_dir($itemDir)) {
                mkdir($itemDir, 0775, true);
            }

            $recordPath = $itemDir . '/capture.json';
            $record = [
                'event_id' => $event['event_id'],
                'source_repo' => $event['source_repo'],
                'source_commit' => $event['source_commit'],
                'captured_at' => $event['captured_at'],
                'target' => $event['target'] ?? 'unknown',
                'type' => $item['type'] ?? 'unknown',
                'name' => $item['name'] ?? 'unknown',
                'status' => $item['status'] ?? 'unknown',
                'content_hash' => $item['content_hash'] ?? '',
            ];
            file_put_contents($recordPath, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            $lines[] = 'Write-back item file: ' . $recordPath;
        }

        return $lines;
    }

    private function resolveSourceDir(): string
    {
        return (string) getcwd();
    }

    private function sanitizePathSegment(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $value);

        return $sanitized === null || $sanitized === '' ? 'unknown' : $sanitized;
    }
}
