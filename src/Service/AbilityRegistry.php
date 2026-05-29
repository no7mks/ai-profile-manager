<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class AbilityRegistry
{
    /** @var array<string, string> section name => singular type name */
    private const SECTION_TYPE_MAP = [
        'rules' => 'rule',
        'agents' => 'agent',
        'skills' => 'skill',
        'hooks' => 'hook',
    ];

    public function __construct(private readonly string $registryPath) {}

    public function getRegistryPath(): string
    {
        return $this->registryPath;
    }

    /**
     * 解析 abilities.yaml，返回按类型分组的 ability 列表。
     *
     * @return array{
     *     rules: list<AbilityEntry>,
     *     agents: list<AbilityEntry>,
     *     skills: list<AbilityEntry>,
     *     hooks: list<AbilityEntry>,
     *     gitignore: list<array<string, mixed>>,
     *     presets: list<array<string, mixed>>,
     * }
     * @throws AbilityRegistryException
     */
    public function parse(): array
    {
        if (!is_file($this->registryPath)) {
            throw AbilityRegistryException::fileNotFound($this->registryPath);
        }

        $content = file_get_contents($this->registryPath);
        if ($content === false) {
            throw AbilityRegistryException::fileNotFound($this->registryPath);
        }

        try {
            $data = Yaml::parse($content);
        } catch (ParseException $e) {
            throw AbilityRegistryException::invalidYaml($this->registryPath, $e->getMessage());
        }

        if (!is_array($data)) {
            throw AbilityRegistryException::invalidYaml($this->registryPath, 'Root element must be a mapping');
        }

        $result = [
            'rules' => [],
            'agents' => [],
            'skills' => [],
            'hooks' => [],
            'gitignore' => [],
            'presets' => [],
            'prompts' => [],
        ];

        $errors = [];

        foreach (self::knownSections() as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                continue;
            }

            $entries = $data[$section];

            // gitignore, presets, and prompts have different structures, pass through as-is
            if ($section === 'gitignore' || $section === 'presets' || $section === 'prompts') {
                $result[$section] = $entries;
                continue;
            }

            $type = self::SECTION_TYPE_MAP[$section];

            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    $errors[] = "{$section}[{$index}]: entry must be a mapping";
                    continue;
                }

                $missing = [];
                if (!isset($entry['path']) || !is_string($entry['path'])) {
                    $missing[] = 'path';
                }
                if (!isset($entry['description']) || !is_string($entry['description'])) {
                    $missing[] = 'description';
                }
                if (!isset($entry['targets']) || !is_array($entry['targets']) || $entry['targets'] === []) {
                    $missing[] = 'targets';
                }

                if ($missing !== []) {
                    $entryId = $entry['path'] ?? $entry['description'] ?? "#{$index}";
                    $errors[] = "{$section}[{$entryId}]: missing required field(s): " . implode(', ', $missing);
                    continue;
                }

                $result[$section][] = new AbilityEntry(
                    path: $entry['path'],
                    description: $entry['description'],
                    targets: $entry['targets'],
                    type: $type,
                );
            }
        }

        if ($errors !== []) {
            throw AbilityRegistryException::validationErrors($errors);
        }

        /** @var array{rules: list<AbilityEntry>, agents: list<AbilityEntry>, skills: list<AbilityEntry>, hooks: list<AbilityEntry>, gitignore: list<array<string, mixed>>, presets: list<array<string, mixed>>} $result */
        return $result;
    }

    /**
     * 返回所有已知 section 名称。
     * @return list<string>
     */
    public static function knownSections(): array
    {
        return ['rules', 'agents', 'skills', 'hooks', 'gitignore', 'presets', 'prompts'];
    }
}
