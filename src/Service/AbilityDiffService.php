<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AbilityDiffService
{
    public function __construct(
        private readonly AbilityDirectoryDiff $directoryDiff = new AbilityDirectoryDiff(),
    ) {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}>
     */
    public function diffForCapture(array $items, array $targets, string $baselineRoot, string $workspaceRoot): array
    {
        return $this->diffTyped($items, $targets, $baselineRoot, $workspaceRoot, false);
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}>
     */
    public function diffForInstalledTargets(array $items, array $targets, string $baselineRoot, string $workspaceRoot): array
    {
        return $this->diffTyped($items, $targets, $baselineRoot, $workspaceRoot, true);
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
    private function diffTyped(array $items, array $targets, string $baselineRoot, string $workspaceRoot, bool $installedLayout): array
    {
        $results = [];
        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $results[] = $this->diffSkill($name, $target, $baselineRoot, $workspaceRoot, $installedLayout);
            }
            foreach ($items['rules'] as $name) {
                $results[] = $this->diffRule($name, $target, $baselineRoot, $workspaceRoot, $installedLayout);
            }
            foreach ($items['agents'] as $name) {
                $results[] = $this->diffAgent($name, $target, $baselineRoot, $workspaceRoot, $installedLayout);
            }
        }

        return $results;
    }

    /**
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffSkill(string $name, string $target, string $baselineRoot, string $workspaceRoot, bool $installedLayout): array
    {
        $bDir = $baselineRoot . '/abilities/skills/' . $name;
        $wDir = $installedLayout
            ? $this->resolveInstalledSkillDir($workspaceRoot, $name, $target)
            : $workspaceRoot . '/abilities/skills/' . $name;
        $baselineExists = is_dir($bDir);
        $workspaceExists = is_dir($wDir);
        $files = $this->directoryDiff->diffDirectories($baselineExists ? $bDir : null, $workspaceExists ? $wDir : null);

        return [
            'type' => 'skill',
            'name' => $name,
            'target' => $target,
            'status' => $this->resolveStatus($files, $baselineExists, $workspaceExists, $installedLayout),
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
    }

    /**
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffAgent(string $name, string $target, string $baselineRoot, string $workspaceRoot, bool $installedLayout): array
    {
        $relative = 'agents/' . $name . '.' . $target . '.md';
        $bFile = $baselineRoot . '/abilities/' . $relative;
        $wFile = $installedLayout
            ? $this->resolveInstalledAgentFile($workspaceRoot, $name, $target)
            : $workspaceRoot . '/abilities/' . $relative;
        $baselineExists = is_file($bFile);
        $workspaceExists = is_file($wFile);
        $files = $this->directoryDiff->diffOptionalFiles($baselineExists ? $bFile : null, $workspaceExists ? $wFile : null, $relative);

        return [
            'type' => 'agent',
            'name' => $name,
            'target' => $target,
            'status' => $this->resolveStatus($files, $baselineExists, $workspaceExists, $installedLayout),
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
    }

    /**
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffRule(string $name, string $target, string $baselineRoot, string $workspaceRoot, bool $installedLayout): array
    {
        $baselineRelative = $this->resolveRuleRelativePath($baselineRoot, $name, $target);
        $workspaceRelative = $installedLayout
            ? $this->resolveInstalledRuleRelativePath($workspaceRoot, $name, $target)
            : $this->resolveRuleRelativePath($workspaceRoot, $name, $target);
        $relative = $baselineRelative ?? $workspaceRelative
            ?? ('rules/' . $name . ($target === 'cursor' ? '.cursor.mdc' : '.kiro.md'));
        $baselineFile = $baselineRelative === null ? null : $baselineRoot . '/abilities/' . $baselineRelative;
        $workspaceFile = $workspaceRelative === null
            ? null
            : ($installedLayout
                ? $workspaceRoot . '/' . $workspaceRelative
                : $workspaceRoot . '/abilities/' . $workspaceRelative);
        $baselineExists = $baselineFile !== null && is_file($baselineFile);
        $workspaceExists = $workspaceFile !== null && is_file($workspaceFile);
        $files = $this->directoryDiff->diffOptionalFiles($baselineExists ? $baselineFile : null, $workspaceExists ? $workspaceFile : null, $relative);

        return [
            'type' => 'rule',
            'name' => $name,
            'target' => $target,
            'status' => $this->resolveStatus($files, $baselineExists, $workspaceExists, $installedLayout),
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function resolveStatus(array $files, bool $baselineExists, bool $workspaceExists, bool $installedLayout): string
    {
        if (!$baselineExists) {
            return 'unknown';
        }
        if ($files === []) {
            return 'unchanged';
        }
        if ($installedLayout && !$workspaceExists) {
            return 'missing';
        }

        return 'modified';
    }

    private function resolveInstalledSkillDir(string $workspaceRoot, string $name, string $target): string
    {
        $base = $target === 'cursor' ? $workspaceRoot . '/.cursor' : $workspaceRoot . '/.kiro';

        return $base . '/skills/' . $name;
    }

    private function resolveInstalledAgentFile(string $workspaceRoot, string $name, string $target): string
    {
        $base = $target === 'cursor' ? $workspaceRoot . '/.cursor' : $workspaceRoot . '/.kiro';

        return $base . '/agents/' . $name . '.md';
    }

    private function resolveInstalledRuleRelativePath(string $workspaceRoot, string $name, string $target): ?string
    {
        $root = $target === 'cursor' ? $workspaceRoot . '/.cursor/rules' : $workspaceRoot . '/.kiro/steering';
        if (!is_dir($root)) {
            return null;
        }

        $suffix = $target === 'cursor' ? '.mdc' : '.md';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $candidates = [];
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getBasename() !== $name . $suffix) {
                continue;
            }
            $path = str_replace('\\', '/', $fileInfo->getPathname());
            $rootPrefix = rtrim(str_replace('\\', '/', $workspaceRoot), '/') . '/';
            if (!str_starts_with($path, $rootPrefix)) {
                continue;
            }
            $candidates[] = substr($path, strlen($rootPrefix));
        }

        if ($candidates === []) {
            return null;
        }
        sort($candidates);

        return $candidates[0];
    }

    private function resolveRuleRelativePath(string $root, string $name, string $target): ?string
    {
        $suffixes = $target === 'cursor'
            ? ['.cursor.mdc', '.cursor.md']
            : ['.kiro.md', '.kiro.mdc'];
        $rulesRoot = $root . '/abilities/rules';
        if (!is_dir($rulesRoot)) {
            return null;
        }

        $candidates = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rulesRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $basename = $fileInfo->getBasename();
            foreach ($suffixes as $suffix) {
                if ($basename === $name . $suffix) {
                    $candidates[] = $fileInfo->getPathname();
                    break;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $path = $this->pickPreferredRuleSourcePath($candidates, $target);

        return ltrim(substr($path, strlen($root . '/abilities/')), '/');
    }

    /**
     * @param array<int, string> $paths
     */
    private function pickPreferredRuleSourcePath(array $paths, string $target): string
    {
        if (count($paths) === 1) {
            return $paths[0];
        }

        $rank = static function (string $p) use ($target): int {
            $b = basename($p);
            if ($target === 'cursor') {
                if (str_ends_with($b, '.cursor.mdc')) {
                    return 0;
                }

                return str_ends_with($b, '.cursor.md') ? 1 : 2;
            }
            if (str_ends_with($b, '.kiro.md')) {
                return 0;
            }

            return str_ends_with($b, '.kiro.mdc') ? 1 : 2;
        };

        usort($paths, static fn (string $a, string $b): int => $rank($a) <=> $rank($b));

        return $paths[0];
    }
}
