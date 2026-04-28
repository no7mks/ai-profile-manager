<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

final class CaptureWriteBackService
{
    /**
     * @param array{
     *     change_id: string,
     *     source_repo: string,
     *     source_commit: string,
     *     base_ref: string,
     *     captured_at: string,
     *     items?: array<int, mixed>
     * } $change
     * @return array<int, string>
     */
    public function writeBack(array $change): array
    {
        $lines = [];
        $sourceDir = $this->resolveSourceDir();
        $abilitiesDir = $sourceDir . '/abilities';
        if (!is_dir($abilitiesDir)) {
            mkdir($abilitiesDir, 0775, true);
        }

        $items = $change['items'] ?? [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemType = (string) ($item['type'] ?? '');
            $name = $this->sanitizePathSegment((string) ($item['name'] ?? 'unknown'));
            $target = $this->sanitizePathSegment((string) ($change['target'] ?? 'unknown'));

            $files = $item['files'] ?? [];
            foreach ($files as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $relativePath = (string) ($file['path'] ?? '');
                $content = (string) ($file['content'] ?? '');
                $deleted = !empty($file['deleted']);

                if ($relativePath === '') {
                    continue;
                }

                if ($itemType === 'preset') {
                    $targetPath = $this->buildSafeTargetPath($sourceDir, $relativePath);
                } elseif ($itemType === 'rule') {
                    // Rules are stored as abilities/rules/<category>/<name>.<target-ext>
                    // and the event payload path is rooted at abilities/ (e.g. rules/git/foo.cursor.mdc).
                    $targetPath = $this->buildSafeTargetPath($sourceDir . '/abilities', $relativePath);
                } else {
                    $typeDir = match ($itemType) {
                        'skill' => 'abilities/skills',
                        'agent' => 'abilities/agents',
                        default => 'abilities/unknown-items',
                    };
                    $itemDir = $itemType === 'skill'
                        ? $sourceDir . '/' . $typeDir . '/' . $name
                        : $sourceDir . '/' . $typeDir . '/' . $name . '/' . $target;
                    if (!is_dir($itemDir)) {
                        mkdir($itemDir, 0775, true);
                    }

                    $targetPath = $this->buildSafeTargetPath($itemDir, $relativePath);
                }

                if ($targetPath === null) {
                    continue;
                }

                if ($deleted) {
                    if (is_file($targetPath)) {
                        unlink($targetPath);
                        $lines[] = 'Delete item file: ' . $targetPath;
                    }

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
