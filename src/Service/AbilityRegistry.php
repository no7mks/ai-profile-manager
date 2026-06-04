<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;
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

                $entryId = $entry['path'] ?? $entry['description'] ?? "#{$index}";

                if ($missing !== []) {
                    $errors[] = "{$section}[{$entryId}]: missing required field(s): " . implode(', ', $missing);
                    continue;
                }

                $scopesResult = self::resolveScopes($entry);
                if (isset($scopesResult['error'])) {
                    $errors[] = "{$section}[{$entryId}]: {$scopesResult['error']}";
                    continue;
                }

                $result[$section][] = new AbilityEntry(
                    path: $entry['path'],
                    description: $entry['description'],
                    targets: $entry['targets'],
                    type: $type,
                    scopes: $scopesResult['scopes'],
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

    public function globalSetupIncludes(): array
    {
        return $this->parseTypedIncludesFromSection('global-setup');
    }

    public function projectOnlyPaths(): array
    {
        $data = $this->parse();
        $paths = [];
        foreach (['rules', 'agents', 'skills', 'hooks'] as $section) {
            foreach ($data[$section] as $entry) {
                if ($entry->scopes === ['project']) {
                    $paths[] = $entry->path;
                }
            }
        }
        foreach ($data['gitignore'] as $gi) {
            if (is_string($gi['marker'] ?? null)) {
                $paths[] = $gi['marker'];
            }
        }
        foreach ($data['prompts'] as $prompt) {
            if (is_string($prompt['name'] ?? null)) {
                $paths[] = $prompt['name'];
            }
        }
        return $paths;
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

    /**
     * @param array<string, mixed> $entry
     * @return array{scopes: list<string>}|array{error: string}
     */
    private static function resolveScopes(array $entry): array
    {
        if (!isset($entry['scopes'])) {
            return ['scopes' => ['project']];
        }

        $scopes = $entry['scopes'];
        if (!is_array($scopes) || $scopes === []) {
            return ['error' => 'scopes must be a non-empty list'];
        }

        $allowed = implode(', ', array_map(static fn (DeployScope $s): string => $s->value, DeployScope::cases()));
        $resolved = [];

        foreach ($scopes as $scopeIndex => $scope) {
            if (!is_string($scope)) {
                return ['error' => "scopes[{$scopeIndex}] must be a string"];
            }

            if (DeployScope::tryFrom($scope) === null) {
                return ['error' => "invalid scope '{$scope}' in scopes (allowed: {$allowed})"];
            }

            $resolved[] = $scope;
        }

        return ['scopes' => $resolved];
    }

    /** @return list<array{type: string, path: string}> */
    private function parseTypedIncludesFromSection(string $sectionKey): array
    {
        $data = $this->readRawYaml();
        if (!is_array($data[$sectionKey] ?? null)) {
            return [];
        }
        if (!is_array($includes = $data[$sectionKey]['includes'] ?? null)) {
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

    /** @return array<string, mixed> */
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
}
