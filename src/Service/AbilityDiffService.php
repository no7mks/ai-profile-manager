<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class AbilityDiffService
{
    public function __construct(
        private readonly AbilityDirectoryDiff $directoryDiff,
        private readonly AbilityRegistry $registry,
    ) {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}>
     */
    public function diffForInstalledTargets(array $items, array $targets, string $baselineRoot, string $workspaceRoot): array
    {
        return $this->diffTyped($items, $targets, $baselineRoot, $workspaceRoot);
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    public function hashFiles(array $files): string
    {
        $parts = [];
        foreach ($files as $f) {
            $deleted = !empty($f['deleted']);
            $parts[] = ($f['path'] ?? '') . "\0" . ($deleted ? '1' : '0') . "\0" . ($f['content'] ?? '');
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}>
     */
    private function diffTyped(array $items, array $targets, string $baselineRoot, string $workspaceRoot): array
    {
        $parsed = $this->registry->parse();
        $baselineRootValid = is_dir($baselineRoot);

        $results = [];
        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $entry = $this->findEntry($parsed['skills'], $name);
                if ($entry === null || !isset($entry->targets[$target])) {
                    continue;
                }
                $results[] = $this->diffSkill($entry, $name, $target, $baselineRoot, $workspaceRoot, $baselineRootValid);
            }
            foreach ($items['rules'] as $name) {
                $entry = $this->findEntry($parsed['rules'], $name);
                if ($entry === null || !isset($entry->targets[$target])) {
                    continue;
                }
                $results[] = $this->diffFile($entry, 'rule', $name, $target, $baselineRoot, $workspaceRoot, $baselineRootValid);
            }
            foreach ($items['agents'] as $name) {
                $entry = $this->findEntry($parsed['agents'], $name);
                if ($entry === null || !isset($entry->targets[$target])) {
                    continue;
                }
                $results[] = $this->diffFile($entry, 'agent', $name, $target, $baselineRoot, $workspaceRoot, $baselineRootValid);
            }
        }

        return $results;
    }

    /**
     * Diff a skill (directory-based ability).
     *
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffSkill(AbilityEntry $entry, string $name, string $target, string $baselineRoot, string $workspaceRoot, bool $baselineRootValid): array
    {
        $relativePath = $entry->targets[$target];
        $bDir = $baselineRoot . '/' . $relativePath;
        $wDir = $workspaceRoot . '/' . $relativePath;
        $baselineExists = is_dir($bDir);
        $workspaceExists = is_dir($wDir);
        $files = $this->directoryDiff->diffDirectories($baselineExists ? $bDir : null, $workspaceExists ? $wDir : null);

        return [
            'type' => 'skill',
            'name' => $name,
            'target' => $target,
            'status' => $this->resolveStatus($files, $baselineExists, $workspaceExists, $baselineRootValid),
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
    }

    /**
     * Diff a file-based ability (agent or rule).
     *
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffFile(AbilityEntry $entry, string $type, string $name, string $target, string $baselineRoot, string $workspaceRoot, bool $baselineRootValid): array
    {
        $relativePath = $entry->targets[$target];
        $bFile = $baselineRoot . '/' . $relativePath;
        $wFile = $workspaceRoot . '/' . $relativePath;
        $baselineExists = is_file($bFile);
        $workspaceExists = is_file($wFile);
        $files = $this->directoryDiff->diffOptionalFiles($baselineExists ? $bFile : null, $workspaceExists ? $wFile : null, $relativePath);

        return [
            'type' => $type,
            'name' => $name,
            'target' => $target,
            'status' => $this->resolveStatus($files, $baselineExists, $workspaceExists, $baselineRootValid),
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
    }

    /**
     * @param list<AbilityEntry> $entries
     */
    private function findEntry(array $entries, string $name): ?AbilityEntry
    {
        foreach ($entries as $entry) {
            if ($entry->path === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function resolveStatus(array $files, bool $baselineExists, bool $workspaceExists, bool $baselineRootValid): string
    {
        if (!$baselineRootValid) {
            return 'no-baseline';
        }
        if (!$baselineExists) {
            return 'new';
        }
        if ($files === []) {
            return 'unchanged';
        }
        if (!$workspaceExists) {
            return 'missing';
        }

        return 'modified';
    }
}
