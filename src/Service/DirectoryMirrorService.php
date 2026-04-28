<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Recursive directory copy with overwrite (used by init scaffold and ability installer).
 */
final class DirectoryMirrorService
{
    public function mirrorDirectory(string $source, string $destination): void
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

    public function copyFile(string $source, string $destination, bool $force): void
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

    public function ensureDirectory(string $path): void
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
