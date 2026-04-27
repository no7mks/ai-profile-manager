<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class CheckService
{
    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, array{type: string, name: string, target: string, status: string}>
     */
    public function checkTyped(array $items, array $targets): array
    {
        $results = [];

        foreach ($targets as $target) {
            $results = array_merge($results, $this->checkType($items['skills'], 'skill', $target));
            $results = array_merge($results, $this->checkType($items['rules'], 'rule', $target));
            $results = array_merge($results, $this->checkType($items['agents'], 'agent', $target));
        }

        return $results;
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

    /**
     * @param array<int, string> $names
     * @return array<int, array{type: string, name: string, target: string, status: string}>
     */
    private function checkType(array $names, string $type, string $target): array
    {
        $results = [];
        foreach ($names as $name) {
            // Placeholder status until real target comparison is implemented.
            $results[] = [
                'type' => $type,
                'name' => $name,
                'target' => $target,
                'status' => 'unknown',
            ];
        }

        return $results;
    }
}
