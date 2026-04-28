<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Copies the bundled project scaffold (docs, issues, AGENTS.md, PROJECT.md) and optional platform scope rules.
 */
final class ProjectInitializer
{
    public function __construct(private readonly string $packageRoot)
    {
    }

    public static function fromPackageLayout(): self
    {
        return new self(PackagePaths::packageRoot());
    }

    /**
     * @param array<int, string> $targets IDE targets (cursor, kiro). Empty means skip installing scope rules.
     *
     * @return array<int, string>
     */
    public function init(string $targetDir, bool $force, array $targets): array
    {
        $lines = [];
        $this->prepareTargetDirectory($targetDir);
        $targetDir = $this->normalizedAbsolute($targetDir);

        $scaffoldRoot = $this->join($this->packageRoot, 'scaffold');
        $this->assertPathExists($scaffoldRoot, 'Internal package layout error: scaffold directory missing.');

        $this->assertScaffoldSourcesPresent($scaffoldRoot);

        if (!$force && $this->scaffoldTargetsExist($targetDir)) {
            throw new RuntimeException(
                'Target already contains docs/, issues/, AGENTS.md, or PROJECT.md. Pass --force to overwrite.'
            );
        }

        $lines[] = 'Installing scaffold (docs/, issues/, AGENTS.md, PROJECT.md)...';
        $this->mirrorDirectory($this->join($scaffoldRoot, 'docs'), $this->join($targetDir, 'docs'));
        $this->mirrorDirectory($this->join($scaffoldRoot, 'issues'), $this->join($targetDir, 'issues'));
        $this->copyFile(
            $this->join($scaffoldRoot, 'AGENTS.md'),
            $this->join($targetDir, 'AGENTS.md'),
            $force
        );
        $this->copyFile(
            $this->join($scaffoldRoot, 'PROJECT.md'),
            $this->join($targetDir, 'PROJECT.md'),
            $force
        );
        $lines[] = '[ok] Scaffold installed at ' . $targetDir;

        $lines = array_merge($lines, $this->installScopeRules($targetDir, $force, $targets));

        return $lines;
    }

    /**
     * @param array<int, string> $targets
     *
     * @return array<int, string>
     */
    private function installScopeRules(string $targetDir, bool $force, array $targets): array
    {
        if ($targets === []) {
            return ['Skipping scope rules (no targets).'];
        }

        $lines = [];
        $lines[] = 'Installing scope rules for targets: ' . implode(', ', $targets);
        $rulesRoot = $this->join($this->packageRoot, 'abilities', 'rules');
        $this->assertPathExists($rulesRoot, 'Internal package layout error: abilities/rules missing.');

        if (in_array('cursor', $targets, true)) {
            $src = $this->join($rulesRoot, 'cursor-scope.cursor.mdc');
            $dst = $this->join($targetDir, '.cursor', 'rules', 'cursor-scope.mdc');
            $this->assertPathExists($src, 'Internal package layout error: cursor-scope.cursor.mdc missing.');
            if (!$force && is_file($dst)) {
                throw new RuntimeException(
                    'Target already has .cursor/rules/cursor-scope.mdc. Pass --force to overwrite.'
                );
            }
            $this->ensureDirectory(dirname($dst));
            $this->copyFile($src, $dst, true);
            $lines[] = '[ok] Installed Cursor scope rule -> .cursor/rules/cursor-scope.mdc';
        }

        if (in_array('kiro', $targets, true)) {
            $src = $this->join($rulesRoot, 'kiro-scope.kiro.md');
            $dst = $this->join($targetDir, '.kiro', 'steering', 'kiro-scope.md');
            $this->assertPathExists($src, 'Internal package layout error: kiro-scope.kiro.md missing.');
            if (!$force && is_file($dst)) {
                throw new RuntimeException(
                    'Target already has .kiro/steering/kiro-scope.md. Pass --force to overwrite.'
                );
            }
            $this->ensureDirectory(dirname($dst));
            $this->copyFile($src, $dst, true);
            $lines[] = '[ok] Installed Kiro scope steering -> .kiro/steering/kiro-scope.md';
        }

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
        $this->ensureDirectory($targetDir);
    }

    private function normalizedAbsolute(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException(sprintf('Not a directory: %s', $path));
        }

        return $resolved;
    }

    private function assertPathExists(string $path, string $message): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertScaffoldSourcesPresent(string $scaffoldRoot): void
    {
        foreach (['docs', 'issues', 'AGENTS.md', 'PROJECT.md'] as $leaf) {
            $p = $this->join($scaffoldRoot, $leaf);
            if (!file_exists($p)) {
                throw new RuntimeException('Internal package layout error: scaffold/' . $leaf . ' missing.');
            }
        }
    }

    private function scaffoldTargetsExist(string $targetDir): bool
    {
        return file_exists($this->join($targetDir, 'docs'))
            || file_exists($this->join($targetDir, 'issues'))
            || file_exists($this->join($targetDir, 'AGENTS.md'))
            || file_exists($this->join($targetDir, 'PROJECT.md'));
    }

    private function mirrorDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new RuntimeException(sprintf('Source is not a directory: %s', $source));
        }
        $this->ensureDirectory($destination);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destPath = $this->join($destination, $relative);
            if ($item->isDir()) {
                $this->ensureDirectory($destPath);

                continue;
            }
            $this->ensureDirectory(dirname($destPath));
            $this->copyFile($item->getPathname(), $destPath, true);
        }
    }

    private function copyFile(string $source, string $destination, bool $force): void
    {
        if (!$force && file_exists($destination)) {
            throw new RuntimeException(
                sprintf('Target already has %s. Pass --force to overwrite.', basename($destination))
            );
        }
        $parent = dirname($destination);
        if (!is_dir($parent)) {
            $this->ensureDirectory($parent);
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException(sprintf('Failed to copy to %s', $destination));
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $path));
        }
    }

    private function join(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
