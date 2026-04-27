<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class Installer
{
    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array<int, string>
     */
    public function installTyped(array $items, array $targets): array
    {
        $lines = [];
        $lines[] = 'Installing profile items...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
        $lines[] = '';

        foreach ($targets as $target) {
            $lines = array_merge($lines, $this->installType($items['skills'], 'skill', $target));
            $lines = array_merge($lines, $this->installType($items['rules'], 'rule', $target));
            $lines = array_merge($lines, $this->installType($items['agents'], 'agent', $target));
        }

        return $lines;
    }

    /**
     * @param array<int, string> $names
     * @return array<int, string>
     */
    private function installType(array $names, string $type, string $target): array
    {
        $lines = [];
        foreach ($names as $name) {
            $effectiveType = $type;
            if ($type === 'rule' && $target === 'kiro') {
                $effectiveType = 'steering';
            }

            // Placeholder for actual per-target install logic.
            $lines[] = "[ok] Installed {$effectiveType} {$name} -> {$target}";
        }

        return $lines;
    }

    /**
     * @param array<int, string> $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '(none)' : implode(', ', $items);
    }
}
