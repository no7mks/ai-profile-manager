<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class KnowledgeBaseUpdater
{
    /**
     * @param array<int, string> $skills
     * @param array<int, string> $rules
     * @param array<int, string> $agents
     * @param array<int, string> $presets
     * @param array<int, string> $targets
     */
    public function update(
        array $skills,
        array $rules,
        array $agents,
        array $presets,
        array $targets
    ): string {
        $baseDir = rtrim((string) getenv('HOME'), '/') . '/.config/apm';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $payload = [
            'schema_version' => 1,
            'updated_at' => gmdate(DATE_ATOM),
            'skills' => $skills,
            'rules' => $rules,
            'agents' => $agents,
            'presets' => $presets,
            'targets' => $targets,
            'notes' => 'Typed local knowledge base snapshot.',
        ];

        $path = $baseDir . '/knowledge-base.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $path;
    }
}
