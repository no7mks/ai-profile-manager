<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class CheckService
{
    public function __construct(
        private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
        private readonly ?AbilityDiffService $diffService = null,
        private readonly HookChecker $hookChecker = new HookChecker(),
        private readonly DeployRootResolver $rootResolver = new DeployRootResolver(),
    ) {
    }

    private function createDiffService(string $baselineRoot): AbilityDiffService
    {
        if ($this->diffService !== null) {
            return $this->diffService;
        }

        $registryPath = $baselineRoot . '/abilities.yaml';

        return new AbilityDiffService(
            new AbilityDirectoryDiff(),
            new AbilityRegistry($registryPath),
        );
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string}>
     */
    public function checkTyped(array $items, array $targets): array
    {
        $baseline = $this->baselineResolver->resolve();
        if ($baseline === null) {
            return $this->buildUnknownResults($items, $targets);
        }

        $workspaceRoot = $this->rootResolver->resolve();
        $baselineRoot = $baseline['install_path'];

        // Process skills/rules/agents via AbilityDiffService
        $diffItems = [
            'skills' => $items['skills'],
            'rules' => $items['rules'],
            'agents' => $items['agents'],
        ];
        $detailed = $this->createDiffService($baselineRoot)->diffForInstalledTargets($diffItems, $targets, $baselineRoot, $workspaceRoot);

        $results = array_map(
            static fn (array $item): array => [
                'type' => $item['type'],
                'name' => $item['name'],
                'target' => $item['target'],
                'status' => $item['status'],
            ],
            $detailed
        );

        // Process hooks via HookChecker
        $hooks = $items['hooks'] ?? [];
        foreach ($targets as $target) {
            foreach ($hooks as $hookName) {
                $results[] = $this->checkHook($hookName, $target, $baselineRoot, $workspaceRoot);
            }
        }

        return $results;
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     */
    public function evaluateExitCode(array $results): int
    {
        foreach ($results as $result) {
            if ($result['status'] === 'modified' || $result['status'] === 'missing' || $result['status'] === 'no-baseline') {
                return 2;
            }
        }

        return 0;
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     * @return array<int, string>
     */
    public function renderResults(array $results): array
    {
        $lines = [];
        foreach ($results as $result) {
            $prefix = match ($result['status']) {
                'unchanged' => 'ok',
                'modified' => 'drift',
                'missing' => 'miss',
                'no-baseline' => 'nobl',
                'new' => 'new',
                default => 'todo',
            };
            $lines[] = sprintf(
                '[%s] %s %s %s on %s',
                $prefix,
                $result['type'],
                $result['name'],
                $result['status'],
                $result['target']
            );
        }

        return $lines;
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     */
    public function hasModified(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['status'] === 'modified') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string}>
     */
    private function buildUnknownResults(array $items, array $targets): array
    {
        $results = [];
        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $results[] = ['type' => 'skill', 'name' => $name, 'target' => $target, 'status' => 'unknown'];
            }
            foreach ($items['rules'] as $name) {
                $results[] = ['type' => 'rule', 'name' => $name, 'target' => $target, 'status' => 'unknown'];
            }
            foreach ($items['agents'] as $name) {
                $results[] = ['type' => 'agent', 'name' => $name, 'target' => $target, 'status' => 'unknown'];
            }
            foreach (($items['hooks'] ?? []) as $name) {
                $results[] = ['type' => 'hook', 'name' => $name, 'target' => $target, 'status' => 'unknown'];
            }
        }

        return $results;
    }

    /**
     * @return array{type: string, name: string, target: string, status: string}
     */
    private function checkHook(string $hookName, string $target, string $baselineRoot, string $workspaceRoot): array
    {
        $checkerStatus = match ($target) {
            'kiro' => $this->checkHookKiro($hookName, $baselineRoot, $workspaceRoot),
            'cursor' => $this->checkHookCursor($hookName, $workspaceRoot),
            default => 'missing',
        };

        return [
            'type' => 'hook',
            'name' => $hookName,
            'target' => $target,
            'status' => $this->mapHookStatus($checkerStatus),
        ];
    }

    private function checkHookKiro(string $hookName, string $baselineRoot, string $workspaceRoot): string
    {
        $sourcePath = $baselineRoot . '/hooks/' . $hookName . '.kiro.hook';
        $targetPath = $workspaceRoot . '/.kiro/hooks/' . $hookName . '.kiro.hook';

        return $this->hookChecker->checkKiro($sourcePath, $targetPath);
    }

    private function checkHookCursor(string $hookName, string $workspaceRoot): string
    {
        $targetDir = $workspaceRoot . '/.cursor/hooks/' . $hookName . '/';
        $hookRegistryPath = $workspaceRoot . '/.cursor/hooks.json';

        return $this->hookChecker->checkCursor($targetDir, $hookRegistryPath);
    }

    private function mapHookStatus(string $checkerStatus): string
    {
        return match ($checkerStatus) {
            'ok' => 'unchanged',
            'drift' => 'modified',
            'missing' => 'missing',
            default => 'unknown',
        };
    }
}
