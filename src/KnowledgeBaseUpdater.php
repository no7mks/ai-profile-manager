<?php

declare(strict_types=1);

namespace AiProfileManager;

final class KnowledgeBaseUpdater
{
    /**
     * @param array<int, string> $abilities
     * @param array<int, string> $targets
     */
    public function update(array $abilities, array $targets): string
    {
        $baseDir = rtrim((string) getenv('HOME'), '/') . '/.config/aipm';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $payload = [
            'schema_version' => 1,
            'updated_at' => gmdate(DATE_ATOM),
            'abilities' => $abilities,
            'targets' => $targets,
            'notes' => 'Initial local knowledge base snapshot.',
        ];

        $path = $baseDir . '/knowledge-base.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $path;
    }
}
