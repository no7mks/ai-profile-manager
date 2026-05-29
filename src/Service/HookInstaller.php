<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class HookInstaller
{
    public function __construct(
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
    ) {}

    /**
     * 安装 hook 到 Kiro 平台（文件复制）。
     *
     * @param string $sourcePath hook 源文件绝对路径
     * @param string $targetPath 目标文件绝对路径
     * @return array{status: string, message: string}
     */
    public function installKiro(string $sourcePath, string $targetPath): array
    {
        if (!is_file($sourcePath)) {
            return ['status' => 'fail', 'message' => "Source file not found: {$sourcePath}"];
        }

        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            $this->mirror->ensureDirectory($parentDir);
        }

        if (!copy($sourcePath, $targetPath)) {
            return ['status' => 'fail', 'message' => "Failed to copy file to: {$targetPath}"];
        }

        return ['status' => 'ok', 'message' => "Installed hook to: {$targetPath}"];
    }

    /**
     * 从 Kiro 平台卸载 hook（删除文件）。
     *
     * @param string $targetPath 目标文件绝对路径
     * @return array{status: string, message: string}
     */
    public function uninstallKiro(string $targetPath): array
    {
        if (!is_file($targetPath)) {
            return ['status' => 'skip', 'message' => "Hook file not found, skipped: {$targetPath}"];
        }

        if (!unlink($targetPath)) {
            return ['status' => 'fail', 'message' => "Failed to delete hook file: {$targetPath}"];
        }

        return ['status' => 'ok', 'message' => "Uninstalled hook from: {$targetPath}"];
    }

    /**
     * 安装 hook 到 Cursor 平台。
     * 两步操作：1) 递归复制 hook 目录；2) 读取 <name>.json 并 merge 条目到 hooks.json。
     *
     * @return array{status: string, message: string}
     * @throws HookRegistryException hooks.json 存在但 JSON 无效时抛出
     */
    public function installCursor(
        string $sourceDir,
        string $targetDir,
        string $hookRegistryPath,
    ): array {
        if (!is_dir($sourceDir)) {
            return ['status' => 'fail', 'message' => "Source directory not found: {$sourceDir}"];
        }

        // Step 1: Recursive copy sourceDir → targetDir
        $this->mirror->mirrorDirectory($sourceDir, $targetDir);

        // Step 2: Read <name>.json from targetDir
        $name = basename($targetDir);
        $entryFilePath = $targetDir . '/' . $name . '.json';
        $entries = $this->readEntryDeclaration($entryFilePath);

        // Step 3: Deep merge into hooks.json
        $registry = $this->loadHookRegistry($hookRegistryPath);
        $this->mergeEntries($registry, $entries);
        $this->writeHookRegistry($hookRegistryPath, $registry);

        return ['status' => 'ok', 'message' => "Installed cursor hook: {$name}"];
    }

    /**
     * 从 Cursor 平台卸载 hook。
     * 两步操作：1) 读取 <name>.json 并从 hooks.json 移除匹配条目；2) 删除整个 hook 目录。
     *
     * @return array{status: string, message: string}
     * @throws HookRegistryException hooks.json 存在但 JSON 无效时抛出
     */
    public function uninstallCursor(
        string $targetDir,
        string $hookRegistryPath,
    ): array {
        if (!is_dir($targetDir)) {
            return ['status' => 'skip', 'message' => "Target directory not found: {$targetDir}"];
        }

        // Step 1: Read <name>.json from targetDir
        $name = basename($targetDir);
        $entryFilePath = $targetDir . '/' . $name . '.json';
        $entries = $this->readEntryDeclaration($entryFilePath);

        // Step 2: Remove matching entries from hooks.json
        if (file_exists($hookRegistryPath)) {
            $registry = $this->loadHookRegistry($hookRegistryPath);
            $this->removeEntries($registry, $entries);
            $this->writeHookRegistry($hookRegistryPath, $registry);
        }

        // Step 3: Delete entire targetDir
        $this->removeDirectory($targetDir);

        return ['status' => 'ok', 'message' => "Uninstalled cursor hook: {$name}"];
    }

    /**
     * Read the entry declaration JSON file (<name>.json).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function readEntryDeclaration(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Load hooks.json registry. Creates initial structure if file doesn't exist.
     *
     * @return array{version: int, hooks: array<string, list<array<string, mixed>>>}
     * @throws HookRegistryException
     */
    private function loadHookRegistry(string $path): array
    {
        if (!file_exists($path)) {
            return ['version' => 1, 'hooks' => []];
        }

        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw HookRegistryException::invalidJson($path);
        }

        if (!isset($data['hooks']) || !is_array($data['hooks'])) {
            $data['hooks'] = [];
        }

        /** @var array{version: int, hooks: array<string, list<array<string, mixed>>>} $data */
        return $data;
    }

    /**
     * Deep merge entries into registry (deduplicate by command field).
     *
     * @param array{version: int, hooks: array<string, list<array<string, mixed>>>} $registry
     * @param array<string, list<array<string, mixed>>> $entries
     */
    private function mergeEntries(array &$registry, array $entries): void
    {
        foreach ($entries as $eventType => $items) {
            if (!isset($registry['hooks'][$eventType])) {
                $registry['hooks'][$eventType] = [];
            }

            foreach ($items as $newEntry) {
                $command = $newEntry['command'] ?? '';
                $exists = false;

                foreach ($registry['hooks'][$eventType] as $existing) {
                    if (($existing['command'] ?? '') === $command) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $registry['hooks'][$eventType][] = $newEntry;
                }
            }
        }
    }

    /**
     * Remove matching entries from registry by command field.
     *
     * @param array{version: int, hooks: array<string, list<array<string, mixed>>>} $registry
     * @param array<string, list<array<string, mixed>>> $entries
     */
    private function removeEntries(array &$registry, array $entries): void
    {
        foreach ($entries as $eventType => $items) {
            if (!isset($registry['hooks'][$eventType])) {
                continue;
            }

            $commandsToRemove = [];
            foreach ($items as $entry) {
                if (isset($entry['command'])) {
                    $commandsToRemove[] = $entry['command'];
                }
            }

            $registry['hooks'][$eventType] = array_values(
                array_filter(
                    $registry['hooks'][$eventType],
                    static fn(array $existing): bool => !in_array($existing['command'] ?? '', $commandsToRemove, true),
                ),
            );
        }
    }

    /**
     * Write registry data to hooks.json.
     *
     * @param array{version: int, hooks: array<string, list<array<string, mixed>>>} $registry
     */
    private function writeHookRegistry(string $path, array $registry): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->mirror->ensureDirectory($dir);
        }

        file_put_contents(
            $path,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($dir);
    }
}
