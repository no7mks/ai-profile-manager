<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;

/**
 * Resolves deploy roots for project (workspace) and user scopes.
 */
final class DeployRootResolver
{
    public function __construct(
        private readonly UserHomeResolver $userHomeResolver = new UserHomeResolver(),
    ) {
    }

    public function resolve(DeployScope $scope): string
    {
        return match ($scope) {
            DeployScope::Project => $this->projectRoot(),
            DeployScope::User => $this->userRoot(),
        };
    }

    public function parseScopeOption(?string $value): DeployScope
    {
        if ($value === null) {
            return DeployScope::Project;
        }

        return match ($value) {
            'project' => DeployScope::Project,
            'user' => DeployScope::User,
            default => throw InvalidScopeException::unknownScope($value),
        };
    }

    public function absoluteTargetPath(DeployScope $scope, string $relativeTarget): string
    {
        $root = $this->resolve($scope);
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativeTarget, "/\\"));

        if ($normalized === '') {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR . $normalized;
    }

    private function projectRoot(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to resolve project root: working directory unavailable.');
        }

        $real = realpath($cwd);

        return $real !== false ? $real : $cwd;
    }

    private function userRoot(): string
    {
        return $this->userHomeResolver->resolve();
    }
}
