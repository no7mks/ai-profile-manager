<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;
use RuntimeException;

/**
 * Copies the bundled project scaffold (docs, issues, AGENTS.md).
 */
final class ProjectInitializer
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
    ) {
    }

    public static function fromPackageLayout(): self
    {
        return new self(PackagePaths::packageRoot());
    }

    /**
     * @param array<int, string> $targets IDE targets (cursor, kiro).
     *
     * @return array<int, string>
     */
    public function init(string $targetDir, bool $force, array $targets): array
    {
        $lines = [];
        $this->prepareTargetDirectory($targetDir);
        $targetDir = $this->normalizedAbsolute($targetDir);

        $this->assertScaffoldSourcesPresent($this->packageRoot);

        if (!$force && $this->scaffoldTargetsExist($targetDir)) {
            throw new RuntimeException(
                'Target already contains docs/, issues/, or AGENTS.md. Pass --force to overwrite.'
            );
        }

        $lines[] = 'Installing scaffold (docs/, issues/, AGENTS.md)...';
        $this->mirror->mirrorDirectory($this->join($this->packageRoot, 'docs'), $this->join($targetDir, 'docs'));
        $this->mirror->mirrorDirectory($this->join($this->packageRoot, 'issues'), $this->join($targetDir, 'issues'));
        $this->mirror->copyFile(
            $this->join($this->packageRoot, 'AGENTS.md'),
            $this->join($targetDir, 'AGENTS.md'),
            $force
        );
        $lines[] = '[ok] Scaffold installed at ' . $targetDir;

        return $lines;
    }

    private function prepareTargetDirectory(string $targetDir): void
    {
        if (is_dir($targetDir)) {
            return;
        }
        if (file_exists($targetDir)) {
            throw new RuntimeException(sprintf('Path exists and is not a directory: %s', $targetDir));
        }
        $this->mirror->ensureDirectory($targetDir);
    }

    private function normalizedAbsolute(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException(sprintf('Not a directory: %s', $path));
        }

        return $resolved;
    }

    private function assertScaffoldSourcesPresent(string $packageRoot): void
    {
        foreach (['docs', 'issues', 'AGENTS.md'] as $leaf) {
            $p = $this->join($packageRoot, $leaf);
            if (!file_exists($p)) {
                throw new RuntimeException('Internal package layout error: ' . $leaf . ' missing.');
            }
        }
    }

    private function scaffoldTargetsExist(string $targetDir): bool
    {
        return file_exists($this->join($targetDir, 'docs'))
            || file_exists($this->join($targetDir, 'issues'))
            || file_exists($this->join($targetDir, 'AGENTS.md'));
    }

    private function join(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }

}
