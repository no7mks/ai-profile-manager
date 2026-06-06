<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Resolves deploy root for project scope (workspace root).
 */
final class DeployRootResolver
{
    public function __construct(
        private readonly ?string $rootPath = null,
    ) {
    }

    public function resolve(): string
    {
        if ($this->rootPath !== null) {
            return $this->rootPath;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to resolve project root: working directory unavailable.');
        }

        $real = realpath($cwd);

        return $real !== false ? $real : $cwd;
    }

    public function absoluteTargetPath(string $relativeTarget): string
    {
        $root = $this->resolve();
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativeTarget, "/\\"));

        if ($normalized === '') {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR . $normalized;
    }
}
