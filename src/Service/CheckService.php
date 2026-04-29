<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class CheckService
{
    public function __construct(
        private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
        private readonly AbilityDiffService $diffService = new AbilityDiffService(),
    ) {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string}>
     */
    public function checkTyped(array $items, array $targets): array
    {
        $baseline = $this->baselineResolver->resolve();
        if ($baseline === null) {
            return $this->buildUnknownResults($items, $targets);
        }

        $workspaceRoot = (string) getcwd();
        $detailed = $this->diffService->diffForInstalledTargets($items, $targets, $baseline['install_path'], $workspaceRoot);

        return array_map(
            static fn (array $item): array => [
                'type' => $item['type'],
                'name' => $item['name'],
                'target' => $item['target'],
                'status' => $item['status'],
            ],
            $detailed
        );
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     */
    public function evaluateExitCode(array $results): int
    {
        foreach ($results as $result) {
            if ($result['status'] === 'modified' || $result['status'] === 'missing') {
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

    public function hasModified(array $results): bool
    {
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'modified') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
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
        }

        return $results;
    }
}
