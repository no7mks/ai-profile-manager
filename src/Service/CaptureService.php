<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class CaptureService
{
    public function __construct(private readonly CheckService $checker)
    {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array{
     *     results: array<int, array{type: string, name: string, target: string, status: string, content_hash: string}>,
     *     lines: array<int, string>,
     *     exit_code: int
     * }
     */
    public function captureTyped(array $items, array $targets): array
    {
        $results = array_map(
            fn (array $result): array => [
                'type' => $result['type'],
                'name' => $result['name'],
                'target' => $result['target'],
                'status' => $result['status'],
                'content_hash' => $this->buildContentHash($result),
            ],
            $this->checker->checkTyped($items, $targets)
        );
        $lines = [];

        foreach ($results as $result) {
            if ($result['status'] === 'modified') {
                $lines[] = sprintf('[todo] %s %s captured (placeholder)', $result['type'], $result['name']);
            }
        }

        if ($lines === []) {
            $lines[] = '[todo] No modified items to capture (placeholder).';
        }

        return [
            'results' => $results,
            'lines' => $lines,
            'exit_code' => $this->checker->evaluateExitCode($results),
        ];
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string, content_hash: string}> $results
     * @return array{
     *     schema_version: int,
     *     event_id: string,
     *     source_repo: string,
     *     source_commit: string,
     *     base_ref: string,
     *     captured_at: string,
     *     target: string,
     *     items: array<int, array{
     *         type: string,
     *         name: string,
     *         status: string,
     *         content_hash: string,
     *         files: array<int, array{path: string, content: string, patch: string}>
     *     }>
     * }
     */
    public function buildCaptureEvent(
        array $results,
        string $sourceRepo,
        string $sourceCommit,
        string $baseRef,
        string $eventId,
        string $capturedAt
    ): array {
        $target = $results === [] ? 'unknown' : $results[0]['target'];
        $eventId = $eventId !== '' ? $eventId : $this->generateUuidV4();

        return [
            'schema_version' => 1,
            'event_id' => $eventId,
            'source_repo' => $sourceRepo,
            'source_commit' => $sourceCommit,
            'base_ref' => $baseRef,
            'captured_at' => $capturedAt,
            'target' => $target,
            'items' => array_map(
                fn (array $result): array => [
                    'type' => $result['type'],
                    'name' => $result['name'],
                    'status' => $result['status'],
                    'content_hash' => $result['content_hash'],
                    'files' => $this->buildPlaceholderFiles($result),
                ],
                $results
            ),
        ];
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @param array{
     *     schema_version: int,
     *     event_id: string,
     *     source_repo: string,
     *     source_commit: string,
     *     base_ref: string,
     *     captured_at: string,
     *     target: string,
     *     items: array<int, array{type: string, name: string, status: string, content_hash: string}>
     * } $event
     */
    public function writeEventToEventsDir(array $event): string
    {
        $aipmHome = (string) (getenv('AIPM_HOME') ?: (rtrim((string) getenv('HOME'), '/') . '/.aipm'));
        $eventsDir = $aipmHome . '/events';
        if (!is_dir($eventsDir)) {
            mkdir($eventsDir, 0775, true);
        }

        $path = $eventsDir . '/' . $event['event_id'] . '.json';
        file_put_contents($path, json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $path;
    }

    /**
     * @param array{type: string, name: string, target: string, status: string} $result
     */
    private function buildContentHash(array $result): string
    {
        return hash('sha256', implode(':', [
            $result['type'],
            $result['name'],
            $result['target'],
            $result['status'],
        ]));
    }

    /**
     * @param array{type: string, name: string, target: string, status: string, content_hash: string} $result
     * @return array<int, array{path: string, content: string, patch: string}>
     */
    private function buildPlaceholderFiles(array $result): array
    {
        $defaultPath = 'ABILITY_PLACEHOLDER.txt';
        if ($result['type'] === 'skill' && $result['target'] === 'cursor') {
            $defaultPath = 'SKILL.md';
        }

        return [[
            'path' => $defaultPath,
            'content' => sprintf(
                "placeholder generated by capture\nname: %s\ntype: %s\ntarget: %s\nstatus: %s\n",
                $result['name'],
                $result['type'],
                $result['target'],
                $result['status']
            ),
            'patch' => sprintf(
                "--- a/%s\n+++ b/%s\n@@ -0,0 +1,5 @@\n+placeholder generated by capture\n+name: %s\n+type: %s\n+target: %s\n+status: %s\n",
                $defaultPath,
                $defaultPath,
                $result['name'],
                $result['type'],
                $result['target'],
                $result['status']
            ),
        ]];
    }
}
