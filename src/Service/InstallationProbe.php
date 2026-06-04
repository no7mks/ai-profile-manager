<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;

/**
 * Detects whether an ability is present on disk at its registry target path.
 */
final class InstallationProbe
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly DeployRootResolver $rootResolver,
    ) {}

    public function isPresent(string $type, string $name, string $target, DeployScope $scope): bool
    {
        $entry = $this->registry->getEntry($type, $name);
        if ($entry === null) {
            return false;
        }

        $relativePath = $entry->targets[$target] ?? null;
        if ($relativePath === null) {
            return false;
        }

        $absolutePath = $this->rootResolver->absoluteTargetPath($scope, $relativePath);

        if ($type === 'skill') {
            return is_dir($absolutePath);
        }

        if ($type === 'hook') {
            return is_file($absolutePath) || is_dir($absolutePath);
        }

        return is_file($absolutePath);
    }
}
