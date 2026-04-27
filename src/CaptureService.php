<?php

declare(strict_types=1);

namespace AiProfileManager;

final class CaptureService
{
    public function __construct(private readonly CheckService $checker)
    {
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array{results: array<int, array{type: string, name: string, target: string, status: string}>, lines: array<int, string>, exit_code: int}
     */
    public function captureTyped(array $items, array $targets): array
    {
        $results = $this->checker->checkTyped($items, $targets);
        $lines = [];

        foreach ($results as $result) {
            if ($result['status'] === 'modified') {
                $lines[] = sprintf('[todo] %s %s captured (placeholder)', $result['type'], $result['name']);
            }
        }

        if ($lines === []) {
            $lines[] = '[todo] No modified items to capture (placeholder).';
        }

        return [
            'results' => $results,
            'lines' => $lines,
            'exit_code' => $this->checker->evaluateExitCode($results),
        ];
    }
}
