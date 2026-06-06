<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Config\DeployScope;

/**
 * Reports and optionally applies baseline updates for installed conventional abilities in project scope.
 */
class AbilityUpdateService
{
    private readonly InstallationProbe $probe;

    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
        private readonly CheckService $checkService = new CheckService(),
        ?InstallationProbe $probe = null,
        private readonly DeployRootResolver $rootResolver = new DeployRootResolver(),
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
        private readonly HookInstaller $hookInstaller = new HookInstaller(),
    ) {
        $this->probe = $probe ?? new InstallationProbe($registry, $this->rootResolver);
    }

    /**
     * 仅遍历 project scope 已安装 ability。
     * 输出格式: changed: {type}:{name} {target}（无 scope 标签）
     *
     * @return array{lines: list<string>, exit_code: int}
     */
    public function reportChanges(bool $force): array
    {
        $baseline = $this->baselineResolver->resolve();
        if ($baseline === null) {
            return [
                'lines' => ['[fail] Baseline not found. Install apm globally with Composer and ensure installed.json is readable.'],
                'exit_code' => 1,
            ];
        }

        $baselineRoot = $baseline['install_path'];
        $baselineRegistry = new AbilityRegistry($baselineRoot . '/abilities.yaml');
        $targets = AppConfig::DEFAULT_TARGETS;

        $items = $this->collectInstalledItems($targets);
        if ($this->isItemsEmpty($items)) {
            return [
                'lines' => ['All installed abilities are up to date.'],
                'exit_code' => 0,
            ];
        }

        $results = $this->checkService->checkTypedForScope($items, $targets, DeployScope::Project);
        $changed = [];
        foreach ($results as $result) {
            if ($result['status'] !== 'modified') {
                continue;
            }
            $changed[] = [
                'type' => $result['type'],
                'name' => $result['name'],
                'target' => $result['target'],
            ];
        }

        if ($changed === []) {
            return [
                'lines' => ['All installed abilities are up to date.'],
                'exit_code' => 0,
            ];
        }

        $lines = [];
        foreach ($changed as $entry) {
            $lines[] = sprintf(
                'changed: %s:%s %s',
                $entry['type'],
                $entry['name'],
                $entry['target'],
            );
        }

        if (!$force) {
            $lines[] = '';
            $lines[] = 'Run with --force to overwrite local changes from baseline.';

            return ['lines' => $lines, 'exit_code' => 0];
        }

        $exitCode = 0;
        foreach ($changed as $entry) {
            $applyLines = $this->applyOverwrite(
                $baselineRoot,
                $baselineRegistry,
                $entry['type'],
                $entry['name'],
                $entry['target'],
            );
            $lines = array_merge($lines, $applyLines);
            foreach ($applyLines as $line) {
                if (str_starts_with($line, '[fail]')) {
                    $exitCode = 1;
                }
            }
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @param array<int, string> $targets
     * @return array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>}
     */
    private function collectInstalledItems(array $targets): array
    {
        $parsed = $this->registry->parse();
        $items = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];

        foreach ($parsed['skills'] as $entry) {
            if ($this->isPresentOnAnyTarget('skill', $entry->path, $targets)) {
                $items['skills'][] = $entry->path;
            }
        }
        foreach ($parsed['rules'] as $entry) {
            if ($this->isPresentOnAnyTarget('rule', $entry->path, $targets)) {
                $items['rules'][] = $entry->path;
            }
        }
        foreach ($parsed['agents'] as $entry) {
            if ($this->isPresentOnAnyTarget('agent', $entry->path, $targets)) {
                $items['agents'][] = $entry->path;
            }
        }
        foreach ($parsed['hooks'] as $entry) {
            if ($this->isPresentOnAnyTarget('hook', $entry->path, $targets)) {
                $items['hooks'][] = $entry->path;
            }
        }

        return $items;
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>} $items
     */
    private function isItemsEmpty(array $items): bool
    {
        return $items['skills'] === []
            && $items['rules'] === []
            && $items['agents'] === []
            && $items['hooks'] === [];
    }

    /**
     * @param array<int, string> $targets
     */
    private function isPresentOnAnyTarget(
        string $type,
        string $name,
        array $targets,
    ): bool {
        foreach ($targets as $target) {
            if ($this->probe->isPresent($type, $name, $target, DeployScope::Project)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function applyOverwrite(
        string $baselineRoot,
        AbilityRegistry $baselineRegistry,
        string $type,
        string $name,
        string $target,
    ): array {
        $entry = $baselineRegistry->getEntry($type, $name);
        if ($entry === null || !isset($entry->targets[$target])) {
            return [sprintf('[fail] Missing registry entry for %s:%s on %s', $type, $name, $target)];
        }

        $relativePath = $entry->targets[$target];
        $workspaceRoot = $this->rootResolver->resolve(DeployScope::Project);

        if ($type === 'hook') {
            return $this->applyHookOverwrite($baselineRoot, $name, $target, $workspaceRoot);
        }

        $src = $baselineRoot . '/' . $relativePath;
        $dst = $this->rootResolver->absoluteTargetPath(DeployScope::Project, $relativePath);

        try {
            if ($type === 'skill') {
                if (!is_dir($src)) {
                    return [sprintf('[fail] Baseline skill missing: %s', $src)];
                }
                $this->mirror->mirrorDirectory($src, $dst);
            } else {
                if (!is_file($src)) {
                    return [sprintf('[fail] Baseline file missing: %s', $src)];
                }
                $this->mirror->ensureDirectory(dirname($dst));
                $this->mirror->copyFile($src, $dst, true);
            }
        } catch (\Throwable $e) {
            return [sprintf('[fail] Update %s:%s %s: %s', $type, $name, $target, $e->getMessage())];
        }

        return [sprintf('[ok] Updated %s:%s %s', $type, $name, $target)];
    }

    /**
     * @return list<string>
     */
    private function applyHookOverwrite(string $baselineRoot, string $hookName, string $target, string $workspaceRoot): array
    {
        if ($target === 'kiro') {
            $sourcePath = $baselineRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $workspaceRoot . '/.kiro/hooks/' . $hookName . '.kiro.hook';
            $result = $this->hookInstaller->installKiro($sourcePath, $targetPath);
        } else {
            $sourceDir = $baselineRoot . '/hooks/' . $hookName;
            $targetDir = $workspaceRoot . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $workspaceRoot . '/.cursor/hooks.json';
            $result = $this->hookInstaller->installCursor($sourceDir, $targetDir, $hookRegistryPath);
        }

        if ($result['status'] === 'fail') {
            return [sprintf('[fail] Hook %s -> %s: %s', $hookName, $target, $result['message'])];
        }

        return [sprintf('[ok] Updated hook %s -> %s', $hookName, $target)];
    }
}
