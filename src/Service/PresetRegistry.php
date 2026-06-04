<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Preset definitions read from the presets section of abilities.yaml.
 */
final class PresetRegistry
{
    public function __construct(private readonly AbilityRegistry $registry)
    {
    }

    /**
     * @return list<array{name: string, description: string, includes: list<array{type: string, path: string}>}>
     */
    public function allPresets(): array
    {
        $data = $this->registry->parse();
        $presets = $data['presets'];

        return array_map(fn(array $preset) => $this->normalizePreset($preset), $presets);
    }

    /**
     * @return array{name: string, description: string, includes: list<array{type: string, path: string}>}|null
     */
    public function getPreset(string $name): ?array
    {
        $all = $this->allPresets();
        foreach ($all as $preset) {
            if ($preset['name'] === $name) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Create a new preset entry in abilities.yaml.
     *
     * @param string $name Preset name (must be unique)
     * @param string $description Preset description
     * @param list<string> $includes List of "type:path" strings
     */
    public function createPreset(string $name, string $description, array $includes): void
    {
        if ($this->getPreset($name) !== null) {
            throw new \RuntimeException("Preset '{$name}' already exists");
        }

        $data = $this->readRawYaml();
        if (!isset($data['presets']) || !\is_array($data['presets'])) {
            $data['presets'] = [];
        }

        $data['presets'][] = [
            'name' => $name,
            'description' => $description,
            'includes' => $includes,
        ];

        $this->writeRawYaml($data);
    }

    /**
     * Delete a preset entry from abilities.yaml.
     */
    public function deletePreset(string $name): void
    {
        $data = $this->readRawYaml();
        $presets = $data['presets'] ?? [];

        $filtered = array_values(array_filter($presets, fn($p) => ($p['name'] ?? '') !== $name));

        if (\count($filtered) === \count($presets)) {
            throw new \RuntimeException("Preset '{$name}' not found");
        }

        $data['presets'] = $filtered;
        $this->writeRawYaml($data);
    }

    /**
     * Add an ability reference to a preset's includes list.
     */
    public function addAbility(string $presetName, string $type, string $abilityPath): void
    {
        // Validate ability type
        $sectionKey = match ($type) {
            'skill' => 'skills',
            'rule' => 'rules',
            'agent' => 'agents',
            'hook' => 'hooks',
            'prompt' => 'prompts',
            default => throw new \RuntimeException("Invalid ability type: {$type}"),
        };

        // Validate ability exists in registry
        $parsed = $this->registry->parse();
        $exists = false;

        if ($type === 'prompt') {
            // Prompts use 'name' field instead of 'path'
            foreach ($parsed['prompts'] as $entry) {
                if (($entry['name'] ?? '') === $abilityPath) {
                    $exists = true;
                    break;
                }
            }
        } else {
            /** @var list<AbilityEntry> $entries */
            $entries = $parsed[$sectionKey];
            foreach ($entries as $entry) {
                if ($entry->path === $abilityPath) {
                    $exists = true;
                    break;
                }
            }
        }
        if (!$exists) {
            throw new \RuntimeException("Ability '{$type}:{$abilityPath}' not found in registry");
        }

        $data = $this->readRawYaml();
        $presets = &$data['presets'];

        $found = false;
        foreach ($presets as &$preset) {
            if (($preset['name'] ?? '') === $presetName) {
                $entry = $type . ':' . $abilityPath;
                if (!\in_array($entry, $preset['includes'] ?? [], true)) {
                    $preset['includes'][] = $entry;
                }
                $found = true;
                break;
            }
        }
        unset($preset);

        if (!$found) {
            throw new \RuntimeException("Preset '{$presetName}' not found");
        }

        $this->writeRawYaml($data);
    }

    /**
     * Remove an ability reference from a preset's includes list.
     */
    public function removeAbility(string $presetName, string $type, string $abilityPath): void
    {
        $data = $this->readRawYaml();
        $presets = &$data['presets'];

        $found = false;
        foreach ($presets as &$preset) {
            if (($preset['name'] ?? '') === $presetName) {
                $entry = $type . ':' . $abilityPath;
                $preset['includes'] = array_values(array_filter(
                    $preset['includes'] ?? [],
                    fn($item) => $item !== $entry
                ));
                $found = true;
                break;
            }
        }
        unset($preset);

        if (!$found) {
            throw new \RuntimeException("Preset '{$presetName}' not found");
        }

        $this->writeRawYaml($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function readRawYaml(): array
    {
        $path = $this->registry->getRegistryPath();
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read: {$path}");
        }

        return Yaml::parse($content) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeRawYaml(array $data): void
    {
        $path = $this->registry->getRegistryPath();
        $yaml = Yaml::dump($data, 4, 2);
        file_put_contents($path, $yaml);
    }

    /**
     * @param array<string, mixed> $preset
     * @return array{name: string, description: string, includes: list<array{type: string, path: string}>}
     */
    private function normalizePreset(array $preset): array
    {
        $includes = [];
        foreach (($preset['includes'] ?? []) as $entry) {
            if (!\is_string($entry)) {
                continue;
            }
            $colonPos = strpos($entry, ':');
            if ($colonPos === false) {
                continue;
            }
            $type = substr($entry, 0, $colonPos);
            $path = substr($entry, $colonPos + 1);
            $includes[] = ['type' => $type, 'path' => $path];
        }

        return [
            'name' => (string) ($preset['name'] ?? ''),
            'description' => (string) ($preset['description'] ?? ''),
            'includes' => $includes,
        ];
    }

    /**
     * Convert a preset's includes to the legacy typed format used by Installer/CheckService.
     *
     * @param array{name: string, description: string, includes: list<array{type: string, path: string}>} $preset
     * @return array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>, prompts: list<string>}
     */
    /**
     * @param array{name: string, description: string, includes: list<array{type: string, path: string}>} $preset
     * @return list<string>
     */
    public static function presetIncludesToBatchRefs(array $preset): array
    {
        $refs = [];
        foreach ($preset['includes'] as $include) {
            $refs[] = $include['type'] . ':' . $include['path'];
        }

        return $refs;
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>, prompts?: list<string>} $typed
     * @return list<string>
     */
    public static function typedSpecToBatchRefs(array $typed): array
    {
        $refs = [];
        foreach ($typed['skills'] as $path) {
            $refs[] = 'skill:' . $path;
        }
        foreach ($typed['rules'] as $path) {
            $refs[] = 'rule:' . $path;
        }
        foreach ($typed['agents'] as $path) {
            $refs[] = 'agent:' . $path;
        }
        foreach ($typed['hooks'] ?? [] as $path) {
            $refs[] = 'hook:' . $path;
        }
        foreach ($typed['prompts'] ?? [] as $path) {
            $refs[] = 'prompt:' . $path;
        }

        return $refs;
    }

    /**
     * @param array{name: string, description: string, includes: list<array{type: string, path: string}>} $preset
     * @return array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>, prompts: list<string>}
     */
    public static function toTypedSpec(array $preset): array
    {
        $result = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => [], 'prompts' => []];
        foreach ($preset['includes'] as $include) {
            $key = match ($include['type']) {
                'skill' => 'skills',
                'rule' => 'rules',
                'agent' => 'agents',
                'hook' => 'hooks',
                'prompt' => 'prompts',
                default => null,
            };
            if ($key !== null) {
                $result[$key][] = $include['path'];
            }
        }

        return $result;
    }

    /**
     * Validate all preset includes before any install writes.
     * Missing targets for a requested IDE align with Installer skip policy (not an error).
     *
     * @param array{name: string, description: string, includes: list<array{type: string, path: string}>} $preset
     * @param array<int, string> $targets
     * @return list<string>
     */
    public function validatePresetInstall(array $preset, array $targets): array
    {
        $errors = [];

        foreach ($preset['includes'] as $include) {
            $ref = $include['type'] . ':' . $include['path'];
            $type = $include['type'];
            $path = $include['path'];

            if (!\in_array($type, ['skill', 'rule', 'agent', 'hook', 'prompt'], true)) {
                $errors[] = "Invalid preset include (unknown type): {$ref}";
                continue;
            }

            if ($type === 'prompt') {
                if (!$this->promptExists($path)) {
                    $errors[] = "Ability '{$ref}' not found in registry";
                }
                continue;
            }

            if ($this->registry->getEntry($type, $path) === null) {
                $errors[] = "Ability '{$ref}' not found in registry";
            }
        }

        return $errors;
    }

    private function promptExists(string $name): bool
    {
        foreach ($this->registry->parse()['prompts'] as $prompt) {
            if (($prompt['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
}
