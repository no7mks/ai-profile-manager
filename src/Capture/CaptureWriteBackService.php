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
     *     base_ref: string,
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
            $target = $this->sanitizePathSegment((string) ($event['target'] ?? 'unknown'));
            $itemDir = $sourceDir . '/' . $typeDir . '/' . $name . '/' . $target;
            if (!is_dir($itemDir)) {
                mkdir($itemDir, 0775, true);
            }

            $files = $item['files'] ?? [];
            foreach ($files as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $relativePath = (string) ($file['path'] ?? '');
                $content = (string) ($file['content'] ?? '');
                if ($relativePath === '') {
                    continue;
                }

                $targetPath = $this->buildSafeTargetPath($itemDir, $relativePath);
                if ($targetPath === null) {
                    continue;
                }

                $targetParent = dirname($targetPath);
                if (!is_dir($targetParent)) {
                    mkdir($targetParent, 0775, true);
                }

                file_put_contents($targetPath, $content);
                $lines[] = 'Write-back item file: ' . $targetPath;
            }
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

    private function buildSafeTargetPath(string $baseDir, string $relativePath): ?string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $normalized = ltrim($normalized, '/');
        if ($normalized === '') {
            return null;
        }

        $segments = explode('/', $normalized);
        $safeSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $safeSegments[] = $segment;
        }

        if ($safeSegments === []) {
            return null;
        }

        return $baseDir . '/' . implode('/', $safeSegments);
    }
}
