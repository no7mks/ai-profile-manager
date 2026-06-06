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
     *     prompts: list<array<string, mixed>>,
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

        // Fail-fast: reject legacy 'global-setup' top-level key
        if (array_key_exists('global-setup', $data)) {
            throw InvalidScopeException::legacyGlobalSetupKey();
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

                // Fail-fast: reject legacy 'scopes' field
                if (array_key_exists('scopes', $entry)) {
                    throw InvalidScopeException::legacyScopesField($entry['path'] ?? "#{$index}");
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

                $entryId = $entry['path'] ?? $entry['description'] ?? "#{$index}";

                if ($missing !== []) {
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

        /** @var array{rules: list<AbilityEntry>, agents: list<AbilityEntry>, skills: list<AbilityEntry>, hooks: list<AbilityEntry>, gitignore: list<array<string, mixed>>, presets: list<array<string, mixed>>, prompts: list<array<string, mixed>>} $result */
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

    /**
     * 读取 bootstrap.includes 列表。
     * 从 bootstrap section 的 includes 字段解析 type:path 条目。
     * bootstrap section 不存在或 includes 为空时返回空数组。
     *
     * @return list<array{type: string, path: string}>
     */
    public function bootstrapIncludes(): array
    {
        $data = $this->readRawYaml();
        if (!is_array($data['bootstrap'] ?? null)) {
            return [];
        }
        if (!is_array($includes = $data['bootstrap']['includes'] ?? null)) {
            return [];
        }
        $result = [];
        foreach ($includes as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $colonPos = strpos($entry, ':');
            if ($colonPos === false) {
                continue;
            }
            $type = substr($entry, 0, $colonPos);
            $path = substr($entry, $colonPos + 1);
            if ($type !== '' && $path !== '') {
                $result[] = ['type' => $type, 'path' => $path];
            }
        }
        return $result;
    }

    /**
     * 读取并解析 YAML 文件，返回原始数组数据。
     *
     * @return array<string, mixed>
     * @throws AbilityRegistryException
     */
    private function readRawYaml(): array
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

        return $data;
    }

    /**
     * 校验 bootstrap.includes 中所有引用在 registry 中存在。
     * 任一引用不存在则抛出异常（fail-fast）。
     *
     * @param list<array{type: string, path: string}> $includes
     * @throws AbilityRegistryException 当引用的 ability 不存在时
     */
    public function validateBootstrapIncludes(array $includes): void
    {
        foreach ($includes as $include) {
            $entry = $this->getEntry($include['type'], $include['path']);
            if ($entry === null) {
                throw AbilityRegistryException::invalidBootstrapReference($include['type'], $include['path']);
            }
        }
    }

    public function getEntry(string $type, string $path): ?AbilityEntry
    {
        $section = match ($type) {
            'rule' => 'rules', 'agent' => 'agents', 'skill' => 'skills', 'hook' => 'hooks',
            default => null,
        };
        if ($section === null) {
            return null;
        }
        foreach ($this->parse()[$section] as $entry) {
            if ($entry->path === $path && $entry->type === $type) {
                return $entry;
            }
        }
        return null;
    }
}
