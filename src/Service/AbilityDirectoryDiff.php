<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Diff two ability roots (baseline vs workspace) into file records suitable for CaptureChange items.
 */
final class AbilityDirectoryDiff
{
    /**
     * Compare two optional files by basename `path` (relative path segment).
     *
     * @return array<int, array{path: string, content: string, patch: string, deleted?: bool}>
     */
    public function diffOptionalFiles(?string $baselineFile, ?string $workspaceFile, string $relativePath): array
    {
        $bExists = $baselineFile !== null && is_file($baselineFile);
        $wExists = $workspaceFile !== null && is_file($workspaceFile);
        $bContent = $bExists ? (string) file_get_contents((string) $baselineFile) : '';
        $wContent = $wExists ? (string) file_get_contents((string) $workspaceFile) : '';

        if ($bContent === $wContent && $bExists === $wExists) {
            return [];
        }

        if ($bExists && !$wExists) {
            return [[
                'path' => $relativePath,
                'content' => '',
                'patch' => $this->buildDeletionPatch($relativePath, $bContent),
                'deleted' => true,
            ]];
        }

        if (!$bExists && $wExists) {
            return [[
                'path' => $relativePath,
                'content' => $wContent,
                'patch' => $this->buildAddPatch($relativePath, $wContent),
            ]];
        }

        return [[
            'path' => $relativePath,
            'content' => $wContent,
            'patch' => $this->buildModifyPatch($relativePath, $bContent, $wContent),
        ]];
    }

    /**
     * @return array<int, array{path: string, content: string, patch: string, deleted?: bool}>
     */
    public function diffDirectories(?string $baselineRoot, ?string $workspaceRoot): array
    {
        $baselineFiles = $baselineRoot !== null && is_dir($baselineRoot)
            ? $this->listRelativeFiles($baselineRoot)
            : [];
        $workspaceFiles = $workspaceRoot !== null && is_dir($workspaceRoot)
            ? $this->listRelativeFiles($workspaceRoot)
            : [];

        $allPaths = array_unique(array_merge(array_keys($baselineFiles), array_keys($workspaceFiles)));
        sort($allPaths);

        $out = [];
        foreach ($allPaths as $rel) {
            $bExists = isset($baselineFiles[$rel]);
            $wExists = isset($workspaceFiles[$rel]);

            $bContent = $bExists ? $baselineFiles[$rel] : '';
            $wContent = $wExists ? $workspaceFiles[$rel] : '';

            if ($bExists && !$wExists) {
                $out[] = [
                    'path' => $rel,
                    'content' => '',
                    'patch' => $this->buildDeletionPatch($rel, $bContent),
                    'deleted' => true,
                ];

                continue;
            }

            if (!$bExists && $wExists) {
                $out[] = [
                    'path' => $rel,
                    'content' => $wContent,
                    'patch' => $this->buildAddPatch($rel, $wContent),
                ];

                continue;
            }

            if ($bContent === $wContent) {
                continue;
            }

            $out[] = [
                'path' => $rel,
                'content' => $wContent,
                'patch' => $this->buildModifyPatch($rel, $bContent, $wContent),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function listRelativeFiles(string $root): array
    {
        $rootReal = realpath($root);
        if ($rootReal === false || !is_dir($rootReal)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $full = $fileInfo->getPathname();
            $relative = substr($full, strlen($rootReal) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $files[$relative] = (string) file_get_contents($full);
        }

        return $files;
    }

    private function buildAddPatch(string $relPath, string $content): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $content));
        $body = '';
        foreach ($lines as $line) {
            $body .= '+' . $line . "\n";
        }

        return "--- a/{$relPath}\n+++ b/{$relPath}\n@@ -0,0 +1," . (string) count($lines) . " @@\n" . $body;
    }

    private function buildDeletionPatch(string $relPath, string $oldContent): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $oldContent));
        $body = '';
        foreach ($lines as $line) {
            $body .= '-' . $line . "\n";
        }

        return "--- a/{$relPath}\n+++ /dev/null\n@@ -1," . (string) count($lines) . " +0,0 @@\n" . $body;
    }

    private function buildModifyPatch(string $relPath, string $oldContent, string $newContent): string
    {
        $oldLines = explode("\n", str_replace("\r\n", "\n", $oldContent));
        $newLines = explode("\n", str_replace("\r\n", "\n", $newContent));

        return "--- a/{$relPath}\n+++ b/{$relPath}\n@@ changes @@\n"
            . $this->minimalUnifiedStub($oldLines, $newLines);
    }

    /**
     * @param array<int, string> $oldLines
     * @param array<int, string> $newLines
     */
    private function minimalUnifiedStub(array $oldLines, array $newLines): string
    {
        $out = '';
        $max = max(count($oldLines), count($newLines));
        for ($i = 0; $i < $max; ++$i) {
            $o = $oldLines[$i] ?? '';
            $n = $newLines[$i] ?? '';
            if ($o === $n) {
                $out .= ' ' . $o . "\n";
            } else {
                if ($o !== '') {
                    $out .= '-' . $o . "\n";
                }

                if ($n !== '') {
                    $out .= '+' . $n . "\n";
                }
            }
        }

        return $out;
    }
}
