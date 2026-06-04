<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;

/**
 * Validates deploy scope constraints for batch installs before any disk writes.
 */
final class ScopeGuard
{
    public function __construct(private readonly AbilityRegistry $registry) {}

    /**
     * @param list<string> $items type:path references (preset include format)
     */
    public function assertBatchAllowed(DeployScope $scope, array $items): void
    {
        if ($scope === DeployScope::Project) {
            return;
        }

        foreach ($items as $item) {
            $parsed = $this->parseTypedRef($item);
            if ($parsed === null) {
                continue;
            }

            if ($this->isProjectOnly($parsed['type'], $parsed['path'])) {
                throw InvalidScopeException::projectOnlyInUserScope($parsed['path']);
            }
        }
    }

    /**
     * @return array{type: string, path: string}|null
     */
    private function parseTypedRef(string $item): ?array
    {
        $colonPos = strpos($item, ':');
        if ($colonPos === false) {
            return null;
        }

        $type = substr($item, 0, $colonPos);
        $path = substr($item, $colonPos + 1);

        if ($type === '' || $path === '') {
            return null;
        }

        return ['type' => $type, 'path' => $path];
    }

    private function isProjectOnly(string $type, string $path): bool
    {
        if ($type === 'gitignore' || $type === 'prompt') {
            return true;
        }

        $entryType = match ($type) {
            'skill', 'rule', 'agent', 'hook' => $type,
            default => null,
        };

        if ($entryType === null) {
            return false;
        }

        $entry = $this->registry->getEntry($entryType, $path);
        if ($entry !== null) {
            return $entry->scopes === ['project'];
        }

        return in_array($path, $this->registry->projectOnlyPaths(), true);
    }
}
