<?php

declare(strict_types=1);

namespace AiProfileManager;

final class Installer
{
    /**
     * @param array<int, string> $abilities
     * @param array<int, string> $targets
     * @return array<int, string>
     */
    public function install(array $abilities, array $targets): array
    {
        $lines = [];
        $lines[] = 'Installing abilities...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Abilities: ' . implode(', ', $abilities);
        $lines[] = '';

        foreach ($targets as $target) {
            foreach ($abilities as $ability) {
                // Placeholder for actual per-target install logic.
                $lines[] = "[ok] Installed {$ability} -> {$target}";
            }
        }

        return $lines;
    }
}
