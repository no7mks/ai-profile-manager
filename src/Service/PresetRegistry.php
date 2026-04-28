<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\AppConfig;

/**
 * Preset definitions: merged from workspace abilities/_presets.json (authoritative when present) and AppConfig defaults.
 */
final class PresetRegistry
{
    public const PRESETS_RELATIVE_PATH = 'abilities/_presets.json';

    public function __construct(private readonly string $workspaceRoot)
    {
    }

    /**
     * When abilities/_presets.json exists it is authoritative; otherwise use AppConfig defaults.
     *
     * @return array<string, array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}>
     */
    public function allPresets(): array
    {
        $path = $this->workspaceRoot . '/' . self::PRESETS_RELATIVE_PATH;
        if (is_readable($path)) {
            $fromFile = $this->loadFromWorkspace();

            return $fromFile !== [] ? $fromFile : AppConfig::PRESET_ITEMS;
        }

        return AppConfig::PRESET_ITEMS;
    }

    /**
     * @return array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}|null
     */
    public function getPreset(string $name): ?array
    {
        $all = $this->allPresets();

        return $all[$name] ?? null;
    }

    /**
     * @param array<string, array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}> $presets
     */
    public function saveToWorkspace(array $presets): void
    {
        $path = $this->workspaceRoot . '/' . self::PRESETS_RELATIVE_PATH;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $json = json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($path, $json);
    }

    /**
     * @return array<string, array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}>
     */
    private function loadFromWorkspace(): array
    {
        $path = $this->workspaceRoot . '/' . self::PRESETS_RELATIVE_PATH;
        if (!is_readable($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $name => $spec) {
            if (!is_string($name) || !is_array($spec)) {
                continue;
            }

            $skills = isset($spec['skills']) && is_array($spec['skills']) ? array_values(array_filter($spec['skills'], 'is_string')) : [];
            $rules = isset($spec['rules']) && is_array($spec['rules']) ? array_values(array_filter($spec['rules'], 'is_string')) : [];
            $agents = isset($spec['agents']) && is_array($spec['agents']) ? array_values(array_filter($spec['agents'], 'is_string')) : [];

            $out[$name] = [
                'skills' => $skills,
                'rules' => $rules,
                'agents' => $agents,
            ];
        }

        return $out;
    }
}
