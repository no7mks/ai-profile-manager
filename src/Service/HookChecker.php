<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class HookChecker
{
    /**
     * 检查 Kiro 平台 hook 状态（文件内容对比）。
     *
     * @param string $sourcePath 源文件绝对路径
     * @param string $targetPath 已安装文件绝对路径
     * @return string "ok"|"drift"|"missing"
     */
    public function checkKiro(string $sourcePath, string $targetPath): string
    {
        if (!\is_file($targetPath)) {
            return 'missing';
        }

        $sourceContent = (string) file_get_contents($sourcePath);
        $targetContent = (string) file_get_contents($targetPath);

        return $sourceContent === $targetContent ? 'ok' : 'drift';
    }

    /**
     * 检查 Cursor 平台 hook 状态。
     * 三项检查：1) 目录存在；2) 入口脚本存在；3) hooks.json 中条目存在。
     *
     * @param string $targetDir hook 目标目录绝对路径（workspace/.cursor/hooks/<name>/）
     * @param string $hookRegistryPath hooks.json 绝对路径
     * @return string "ok"|"missing"
     */
    public function checkCursor(string $targetDir, string $hookRegistryPath): string
    {
        // Check 1: Directory exists
        if (!\is_dir($targetDir)) {
            return 'missing';
        }

        // Check 2: Entry script exists
        $name = basename($targetDir);
        $entryScript = "{$targetDir}/{$name}.sh";
        if (!\is_file($entryScript)) {
            return 'missing';
        }

        // Check 3: hooks.json entries exist
        $entryDeclarationPath = "{$targetDir}/{$name}.json";
        $entries = $this->readEntryDeclaration($entryDeclarationPath);

        if ($entries === []) {
            return 'ok';
        }

        if (!file_exists($hookRegistryPath)) {
            return 'missing';
        }

        $registry = $this->loadHookRegistry($hookRegistryPath);

        foreach ($entries as $eventType => $items) {
            foreach ($items as $entry) {
                $command = $entry['command'] ?? '';
                if (!$this->registryContainsEntry($registry, $eventType, $command)) {
                    return 'missing';
                }
            }
        }

        return 'ok';
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

        if (!\is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Load hooks.json registry.
     *
     * @return array{version: int, hooks: array<string, list<array<string, mixed>>>}
     */
    private function loadHookRegistry(string $path): array
    {
        $content = (string) file_get_contents($path);
        $data = json_decode($content, true);

        if (!\is_array($data)) {
            return ['version' => 1, 'hooks' => []];
        }

        if (!isset($data['hooks']) || !\is_array($data['hooks'])) {
            $data['hooks'] = [];
        }

        /** @var array{version: int, hooks: array<string, list<array<string, mixed>>>} $data */
        return $data;
    }

    /**
     * Check if registry contains a specific (event_type, command) entry.
     *
     * @param array{version: int, hooks: array<string, list<array<string, mixed>>>} $registry
     */
    private function registryContainsEntry(array $registry, string $eventType, string $command): bool
    {
        if (!isset($registry['hooks'][$eventType])) {
            return false;
        }

        foreach ($registry['hooks'][$eventType] as $existing) {
            if (($existing['command'] ?? '') === $command) {
                return true;
            }
        }

        return false;
    }
}
