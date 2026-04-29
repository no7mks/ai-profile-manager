<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CaptureService
{
    public function __construct(
        private readonly CheckService $checker,
        private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
        private readonly AbilityDirectoryDiff $directoryDiff = new AbilityDirectoryDiff(),
    ) {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array{
     *     results: array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}>,
     *     lines: array<int, string>,
     *     exit_code: int,
     *     baseline: array{package: string, version: string, install_path: string, reference?: string}|null
     * }
     */
    public function captureTyped(array $items, array $targets, string $workspaceRoot): array
    {
        $baseline = $this->baselineResolver->resolve();
        if ($baseline === null) {
            return [
                'results' => [],
                'lines' => ['[error] Could not resolve Composer baseline (global installed.json / package path).'],
                'exit_code' => 1,
                'baseline' => null,
            ];
        }

        $results = [];
        $lines = [];

        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $results[] = $this->diffAbility('skill', $name, $target, $baseline['install_path'], $workspaceRoot);
            }

            foreach ($items['rules'] as $name) {
                $results[] = $this->diffAbility('rule', $name, $target, $baseline['install_path'], $workspaceRoot);
            }

            foreach ($items['agents'] as $name) {
                $results[] = $this->diffAbility('agent', $name, $target, $baseline['install_path'], $workspaceRoot);
            }
        }

        foreach ($results as $result) {
            if ($result['status'] === 'modified') {
                $lines[] = sprintf('[capture] %s %s on %s (%d file change(s))', $result['type'], $result['name'], $result['target'], count($result['files']));
            }
        }

        if ($lines === []) {
            $lines[] = '[capture] No changes vs Composer baseline.';
        }

        $filtered = array_values(array_filter($results, fn (array $r): bool => $r['status'] === 'modified'));

        return [
            'results' => $filtered,
            'lines' => $lines,
            'exit_code' => $this->checker->evaluateExitCode($filtered),
            'baseline' => $baseline,
        ];
    }

    /**
     * Scan workspace abilities/* for subdirectory names.
     *
     * @return array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}
     */
    public function discoverWorkspaceAbilities(string $workspaceRoot): array
    {
        return [
            'skills' => $this->listSubdirNames($workspaceRoot . '/abilities/skills'),
            'rules' => $this->listSubdirNames($workspaceRoot . '/abilities/rules'),
            'agents' => $this->listAgentAbilityNames($workspaceRoot . '/abilities/agents'),
        ];
    }

    /**
     * Diff preset manifest file abilities/_presets.json vs baseline.
     *
     * @return array{
     *     result: array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}|null,
     *     baseline: array{package: string, version: string, install_path: string, reference?: string}|null
     * }
     */
    public function capturePresetManifestDiff(string $workspaceRoot): array
    {
        $baseline = $this->baselineResolver->resolve();
        if ($baseline === null) {
            return ['result' => null, 'baseline' => null];
        }

        $rel = PresetRegistry::PRESETS_RELATIVE_PATH;
        $bPath = $baseline['install_path'] . '/' . $rel;
        $wPath = $workspaceRoot . '/' . $rel;

        $files = $this->directoryDiff->diffOptionalFiles(
            is_file($bPath) ? $bPath : null,
            is_file($wPath) ? $wPath : null,
            $rel
        );

        if ($files === []) {
            return ['result' => null, 'baseline' => $baseline];
        }

        $contentHash = $this->hashFiles($files);
        $result = [
            'type' => 'preset',
            'name' => '_presets',
            'target' => 'repo',
            'status' => 'modified',
            'content_hash' => $contentHash,
            'files' => $files,
        ];

        return ['result' => $result, 'baseline' => $baseline];
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string, content_hash: string, files?: array<int, array<string, mixed>>}> $results
     * @param array{package: string, version: string, install_path: string, reference?: string}|null $baseline
     * @return array<string, mixed>
     */
    public function buildCaptureChange(
        array $results,
        string $sourceRepo,
        string $sourceCommit,
        string $baseRefIgnored,
        string $changeId,
        string $capturedAt,
        ?array $baseline,
    ): array {
        $target = $results === [] ? 'unknown' : $results[0]['target'];
        $changeId = $changeId !== '' ? $changeId : $this->generateUuidV4();

        $legacyBaseRef = '';
        if ($baseline !== null) {
            $legacyBaseRef = $baseline['reference'] ?? $baseline['version'];
        }

        if ($legacyBaseRef === '') {
            $legacyBaseRef = 'unknown';
        }

        $items = [];
        foreach ($results as $result) {
            $files = $result['files'] ?? [];
            if ($files === []) {
                continue;
            }

            $items[] = [
                'type' => $result['type'],
                'name' => $result['name'],
                'status' => $result['status'],
                'content_hash' => $result['content_hash'],
                'files' => $this->normalizeFilesForSchema($files),
            ];
        }

        $change = [
            'schema_version' => 2,
            'change_id' => $changeId,
            'source_repo' => $sourceRepo,
            'source_commit' => $sourceCommit,
            'base_ref' => $legacyBaseRef,
            'captured_at' => $capturedAt,
            'target' => $target,
            'items' => $items,
        ];

        if ($baseline !== null) {
            $b = [
                'package' => $baseline['package'],
                'version' => $baseline['version'],
                'install_path' => $baseline['install_path'],
            ];
            if (isset($baseline['reference'])) {
                $b['reference'] = $baseline['reference'];
            }

            $change['baseline'] = $b;
        }

        return $change;
    }

    /**
     * Persist capture change when baseline resolved and there is at least one changed item.
     *
     * @param array{
     *     results: array<int, mixed>,
     *     lines: array<int, string>,
     *     exit_code: int,
     *     baseline: array{package: string, version: string, install_path: string, reference?: string}|null
     * } $result
     * @return array{path: ?string, wrote: bool}
     */
    public function persistCaptureChange(
        array $result,
        string $sourceRepo,
        string $sourceCommit,
        string $changeId,
        string $capturedAt,
    ): array {
        if ($result['baseline'] === null || $result['results'] === []) {
            return ['path' => null, 'wrote' => false];
        }

        $change = $this->buildCaptureChange(
            $result['results'],
            $sourceRepo,
            $sourceCommit,
            '',
            $changeId,
            $capturedAt,
            $result['baseline'],
        );
        $path = $this->writeChangeToChangesDir($change);

        return ['path' => $path, 'wrote' => true];
    }

    /**
     * After mutating abilities/_presets.json, optionally write a change when the manifest differs from baseline.
     *
     * @return array{exit_code: int, path: ?string, baseline_missing: bool, unchanged: bool}
     */
    public function persistPresetManifestCapture(
        string $workspaceRoot,
        string $sourceRepo,
        string $sourceCommit,
        string $changeId,
        string $capturedAt,
    ): array {
        $diff = $this->capturePresetManifestDiff($workspaceRoot);
        if ($diff['baseline'] === null) {
            return ['exit_code' => 1, 'path' => null, 'baseline_missing' => true, 'unchanged' => false];
        }

        if ($diff['result'] === null) {
            return ['exit_code' => 0, 'path' => null, 'baseline_missing' => false, 'unchanged' => true];
        }

        $wrapped = [
            'results' => [$diff['result']],
            'lines' => [],
            'exit_code' => $this->checker->evaluateExitCode([$diff['result']]),
            'baseline' => $diff['baseline'],
        ];

        $persist = $this->persistCaptureChange(
            $wrapped,
            $sourceRepo,
            $sourceCommit,
            $changeId,
            $capturedAt,
        );

        return [
            'exit_code' => $wrapped['exit_code'],
            'path' => $persist['path'],
            'baseline_missing' => false,
            'unchanged' => false,
        ];
    }

    public function writeChangeToChangesDir(array $change): string
    {
        $apmHome = (string) (getenv('APM_HOME') ?: (rtrim((string) getenv('HOME'), '/') . '/.apm'));
        $changesDir = $apmHome . '/changes';
        if (!is_dir($changesDir)) {
            mkdir($changesDir, 0775, true);
        }

        $path = $changesDir . '/' . $change['change_id'] . '.json';
        file_put_contents($path, json_encode($change, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $path;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFilesForSchema(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $row = [
                'path' => (string) ($file['path'] ?? ''),
                'content' => (string) ($file['content'] ?? ''),
                'patch' => (string) ($file['patch'] ?? ''),
            ];
            if (!empty($file['deleted'])) {
                $row['deleted'] = true;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array{type: string, name: string, target: string, status: string, content_hash: string, files: array<int, array<string, mixed>>}
     */
    private function diffAbility(string $type, string $name, string $target, string $baselineRoot, string $workspaceRoot): array
    {
        if ($type === 'rule') {
            $relative = $this->resolveRuleRelativePath($workspaceRoot, $name, $target)
                ?? $this->resolveRuleRelativePath($baselineRoot, $name, $target)
                ?? ('rules/' . $name . ($target === 'cursor' ? '.cursor.mdc' : '.kiro.md'));
            $relative = preg_replace('/^abilities\//', '', $relative) ?? $relative;
            $baselineFile = is_file($baselineRoot . '/abilities/' . $relative) ? $baselineRoot . '/abilities/' . $relative : null;
            $workspaceFile = is_file($workspaceRoot . '/abilities/' . $relative) ? $workspaceRoot . '/abilities/' . $relative : null;
            $files = $this->directoryDiff->diffOptionalFiles($baselineFile, $workspaceFile, $relative);
            $status = $files === [] ? 'unchanged' : 'modified';

            return [
                'type' => $type,
                'name' => $name,
                'target' => $target,
                'status' => $status,
                'content_hash' => $this->hashFiles($files),
                'files' => $files,
            ];
        }

        if ($type === 'agent') {
            $relative = 'agents/' . $name . '.' . $target . '.md';
            $baselineFile = is_file($baselineRoot . '/abilities/' . $relative) ? $baselineRoot . '/abilities/' . $relative : null;
            $workspaceFile = is_file($workspaceRoot . '/abilities/' . $relative) ? $workspaceRoot . '/abilities/' . $relative : null;
            $files = $this->directoryDiff->diffOptionalFiles($baselineFile, $workspaceFile, $relative);
            $status = $files === [] ? 'unchanged' : 'modified';

            return [
                'type' => $type,
                'name' => $name,
                'target' => $target,
                'status' => $status,
                'content_hash' => $this->hashFiles($files),
                'files' => $files,
            ];
        }

        $sub = match ($type) {
            'skill' => 'abilities/skills',
            'rule' => 'abilities/rules',
            default => 'abilities/unknown-items',
        };

        $mid = '/' . $sub . '/' . $name;
        $bAbs = $baselineRoot . $mid;
        $wAbs = $workspaceRoot . $mid;

        $bDir = is_dir($bAbs) ? $bAbs : null;
        $wDir = is_dir($wAbs) ? $wAbs : null;

        $files = $this->directoryDiff->diffDirectories($bDir, $wDir);

        $status = $files === [] ? 'unchanged' : 'modified';

        return [
            'type' => $type,
            'name' => $name,
            'target' => $target,
            'status' => $status,
            'content_hash' => $this->hashFiles($files),
            'files' => $files,
        ];
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

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function hashFiles(array $files): string
    {
        $parts = [];
        foreach ($files as $f) {
            $deleted = !empty($f['deleted']);
            $parts[] = ($f['path'] ?? '') . "\0" . ($deleted ? '1' : '0') . "\0" . ($f['content'] ?? '');
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @return array<int, string>
     */
    private function listSubdirNames(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $names = [];
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($path . '/' . $entry)) {
                $names[] = $entry;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function listAgentAbilityNames(string $agentsRoot): array
    {
        if (!is_dir($agentsRoot)) {
            return [];
        }

        $names = [];
        foreach (scandir($agentsRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!is_file($agentsRoot . '/' . $entry)) {
                continue;
            }
            if (preg_match('/^(.+)\.(cursor|kiro)\.md$/', $entry, $m) === 1) {
                $names[] = $m[1];
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
