<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Installer
{
    private readonly string $packageRoot;

    public function __construct(
        private readonly AbilityRegistry $registry = new AbilityRegistry(__DIR__ . '/../../abilities.yaml'),
        private readonly HookInstaller $hookInstaller = new HookInstaller(),
        private readonly HookChecker $hookChecker = new HookChecker(),
        private readonly GitIgnoreTemplateService $gitIgnore = new GitIgnoreTemplateService(),
        ?string $packageRoot = null,
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
    ) {
        $this->packageRoot = $packageRoot ?? PackagePaths::packageRoot();
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>, hooks?: array<int, string>} $items
     * @param array<int, string> $targets
     * @param string|null $presetName
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function installTyped(array $items, array $targets, ?string $presetName = null): array
    {
        $hooks = $items['hooks'] ?? [];

        $lines = [];
        $lines[] = 'Installing profile items...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
        $lines[] = 'Hooks: ' . $this->formatList($hooks);
        $lines[] = '';

        $exitCode = 0;

        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $r = $this->installAbilityBundle('skill', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            foreach ($items['rules'] as $name) {
                $r = $this->installAbilityBundle('rule', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            foreach ($items['agents'] as $name) {
                $r = $this->installAbilityBundle('agent', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            // hooks: dispatch to HookInstaller per target
            foreach ($hooks as $hookName) {
                $r = $this->installHook($hookName, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
        }

        $gitignoreResult = $this->installGitIgnore($items, $targets, $presetName);
        if ($gitignoreResult !== null) {
            $lines[] = $gitignoreResult;
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>, hooks?: array<int, string>} $items
     * @param array<int, string> $targets
     * @param bool $force 是否强制卸载（跳过 drift 检查）
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function uninstallTyped(array $items, array $targets, bool $force = false): array
    {
        $hooks = $items['hooks'] ?? [];

        $lines = [];
        $lines[] = 'Uninstalling profile items...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
        $lines[] = 'Hooks: ' . $this->formatList($hooks);
        $lines[] = '';

        $exitCode = 0;

        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                $lines = array_merge($lines, $this->uninstallSkill($name, $target));
            }
            foreach ($items['rules'] as $name) {
                $lines = array_merge($lines, $this->uninstallRule($name, $target));
            }
            foreach ($items['agents'] as $name) {
                $lines = array_merge($lines, $this->uninstallAgent($name, $target));
            }
            // hooks: dispatch to HookInstaller per target with drift check
            foreach ($hooks as $hookName) {
                $r = $this->uninstallHook($hookName, $target, $force);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @return array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>, hooks: array<int, string>}
     */
    public function listAvailableItems(): array
    {
        $parsed = $this->registry->parse();

        $skills = array_map(static fn (AbilityEntry $e): string => $e->path, $parsed['skills']);
        $rules = array_map(static fn (AbilityEntry $e): string => $e->path, $parsed['rules']);
        $agents = array_map(static fn (AbilityEntry $e): string => $e->path, $parsed['agents']);
        $hooks = array_map(static fn (AbilityEntry $e): string => $e->path, $parsed['hooks']);

        sort($skills);
        sort($rules);
        sort($agents);
        sort($hooks);

        return [
            'skills' => array_values($skills),
            'rules' => array_values($rules),
            'agents' => array_values($agents),
            'hooks' => array_values($hooks),
        ];
    }

    public function isInstalledOnTarget(string $type, string $name, string $target): bool
    {
        if ($type === 'skill') {
            return is_dir($this->resolveInstallTargetDir('skill', $name, $target));
        }
        if ($type === 'agent') {
            return is_file($this->resolveInstallTargetAgentFile($name, $target));
        }
        if ($type !== 'rule') {
            return false;
        }

        $root = $target === 'cursor'
            ? (string) getcwd() . '/.cursor/rules'
            : (string) getcwd() . '/.kiro/steering';
        $suffix = $target === 'cursor' ? '.mdc' : '.md';
        if (!is_dir($root)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getBasename() === $name . $suffix) {
                return true;
            }
        }

        return false;
    }

    /**
     * 从 AbilityRegistry 解析源路径。
     * 返回 $packageRoot/<targets[target]> 或 null（entry 不存在或 targets 不含 target）。
     */
    private function resolveSourcePath(string $type, string $name, string $target): ?string
    {
        $parsed = $this->registry->parse();
        $section = match ($type) {
            'skill' => 'skills',
            'agent' => 'agents',
            'rule' => 'rules',
            default => null,
        };

        if ($section === null || !isset($parsed[$section])) {
            return null;
        }

        $entry = null;
        foreach ($parsed[$section] as $e) {
            if ($e->path === $name) {
                $entry = $e;
                break;
            }
        }

        if ($entry === null || !isset($entry->targets[$target])) {
            return null;
        }

        return $this->packageRoot . '/' . $entry->targets[$target];
    }

    /**
     * 从 AbilityRegistry 解析目标路径。
     * 返回 $workspace/<targets[target]>。
     */
    private function resolveDestPath(string $type, string $name, string $target, string $workspace): string
    {
        $parsed = $this->registry->parse();
        $section = match ($type) {
            'skill' => 'skills',
            'agent' => 'agents',
            'rule' => 'rules',
            default => '',
        };

        foreach ($parsed[$section] as $e) {
            if ($e->path === $name) {
                return $workspace . '/' . $e->targets[$target];
            }
        }

        // Fallback — should not be reached when called after resolveSourcePath succeeds
        return $workspace . '/' . $name;
    }

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installAbilityBundle(string $type, string $name, string $target): array
    {
        $src = $this->resolveSourcePath($type, $name, $target);

        // Entry 不存在或 targets 不含请求的 target → 静默跳过
        if ($src === null) {
            return ['lines' => [], 'failed' => false];
        }

        $dst = $this->resolveDestPath($type, $name, $target, (string) getcwd());

        // 检查源路径是否存在
        $sourceExists = $type === 'skill' ? is_dir($src) : is_file($src);
        if (!$sourceExists) {
            return [
                'lines' => [sprintf('[fail] Missing ability: %s %s (expected %s)', $type, $name, $src)],
                'failed' => true,
            ];
        }

        // 复制
        try {
            if ($type === 'skill') {
                $this->mirror->mirrorDirectory($src, $dst);
            } else {
                $this->mirror->ensureDirectory(dirname($dst));
                $this->mirror->copyFile($src, $dst, true);
            }
        } catch (\Throwable $e) {
            return [
                'lines' => [sprintf('[fail] Install copy failed (%s %s -> %s): %s', $type, $name, $target, $e->getMessage())],
                'failed' => true,
            ];
        }

        $label = ($type === 'rule' && $target === 'kiro') ? 'steering' : $type;

        return [
            'lines' => [sprintf('[ok] Installed %s %s -> %s', $label, $name, $target)],
            'failed' => false,
        ];
    }

    private function resolveInstallTargetAgentFile(string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $base = $target === 'cursor' ? $cwd . '/.cursor' : $cwd . '/.kiro';

        return $base . '/agents/' . $name . '.md';
    }

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installHook(string $hookName, string $target): array
    {
        $cwd = (string) getcwd();

        if ($target === 'kiro') {
            $sourcePath = $this->packageRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $cwd . '/.kiro/hooks/' . $hookName . '.kiro.hook';

            $result = $this->hookInstaller->installKiro($sourcePath, $targetPath);
        } else {
            // cursor
            $sourceDir = $this->packageRoot . '/hooks/' . $hookName;
            $targetDir = $cwd . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $cwd . '/.cursor/hooks.json';

            $result = $this->hookInstaller->installCursor($sourceDir, $targetDir, $hookRegistryPath);
        }

        if ($result['status'] === 'fail') {
            return [
                'lines' => [sprintf('[fail] Hook %s -> %s: %s', $hookName, $target, $result['message'])],
                'failed' => true,
            ];
        }

        return [
            'lines' => [sprintf('[ok] Installed hook %s -> %s', $hookName, $target)],
            'failed' => false,
        ];
    }

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function uninstallHook(string $hookName, string $target, bool $force): array
    {
        $cwd = (string) getcwd();

        if ($target === 'kiro') {
            $sourcePath = $this->packageRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $cwd . '/.kiro/hooks/' . $hookName . '.kiro.hook';

            // Drift check for Kiro platform
            $checkResult = $this->hookChecker->checkKiro($sourcePath, $targetPath);
            if ($checkResult === 'drift' && !$force) {
                return [
                    'lines' => [sprintf('[fail] Hook %s has drift on %s, use --force to override', $hookName, $target)],
                    'failed' => true,
                ];
            }
            if ($checkResult === 'missing') {
                return [
                    'lines' => [sprintf('[skip] Hook %s not found on %s', $hookName, $target)],
                    'failed' => false,
                ];
            }

            $result = $this->hookInstaller->uninstallKiro($targetPath);
        } else {
            // Cursor: no drift concept, uninstall directly
            $targetDir = $cwd . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $cwd . '/.cursor/hooks.json';

            $result = $this->hookInstaller->uninstallCursor($targetDir, $hookRegistryPath);
        }

        if ($result['status'] === 'fail') {
            return [
                'lines' => [sprintf('[fail] Hook %s -> %s: %s', $hookName, $target, $result['message'])],
                'failed' => true,
            ];
        }

        if ($result['status'] === 'skip') {
            return [
                'lines' => [sprintf('[skip] Hook %s not found on %s', $hookName, $target)],
                'failed' => false,
            ];
        }

        return [
            'lines' => [sprintf('[ok] Uninstalled hook %s from %s', $hookName, $target)],
            'failed' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function uninstallSkill(string $name, string $target): array
    {
        $dir = $this->resolveInstallTargetDir('skill', $name, $target);
        if (!is_dir($dir)) {
            return [sprintf('[miss] Skill %s not found on %s', $name, $target)];
        }

        $this->removeDirectory($dir);

        return [sprintf('[ok] Uninstalled skill %s from %s', $name, $target)];
    }

    /**
     * @return array<int, string>
     */
    private function uninstallRule(string $name, string $target): array
    {
        $root = $target === 'cursor'
            ? (string) getcwd() . '/.cursor/rules'
            : (string) getcwd() . '/.kiro/steering';
        $suffix = $target === 'cursor' ? '.mdc' : '.md';
        if (!is_dir($root)) {
            return [sprintf('[miss] %s %s not found on %s', $target === 'kiro' ? 'Steering' : 'Rule', $name, $target)];
        }

        $removed = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getBasename() !== $name . $suffix) {
                continue;
            }
            $path = $fileInfo->getPathname();
            if (is_file($path) && unlink($path)) {
                $removed[] = $path;
            }
        }
        if ($removed === []) {
            return [sprintf('[miss] %s %s not found on %s', $target === 'kiro' ? 'Steering' : 'Rule', $name, $target)];
        }

        return [sprintf('[ok] Uninstalled %s %s from %s', $target === 'kiro' ? 'steering' : 'rule', $name, $target)];
    }

    /**
     * @return array<int, string>
     */
    private function uninstallAgent(string $name, string $target): array
    {
        $file = $this->resolveInstallTargetAgentFile($name, $target);
        if (!is_file($file)) {
            return [sprintf('[miss] Agent %s not found on %s', $name, $target)];
        }
        unlink($file);

        return [sprintf('[ok] Uninstalled agent %s from %s', $name, $target)];
    }

    private function resolveInstallTargetDir(string $type, string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $base = $target === 'cursor' ? $cwd . '/.cursor' : $cwd . '/.kiro';

        return $base . '/skills/' . $name;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
                continue;
            }
            unlink($fileInfo->getPathname());
        }
        rmdir($dir);
    }

    /**
     * @param array<int, string> $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '(none)' : implode(', ', $items);
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     */
    private function installGitIgnore(array $items, array $targets, ?string $presetName): ?string
    {
        $templatePath = $this->packageRoot . '/.gitignore';
        $abilityKeys = [];
        foreach ($items['skills'] as $name) {
            $abilityKeys[] = 'skill:' . $name;
        }
        foreach ($items['rules'] as $name) {
            $abilityKeys[] = 'rule:' . $name;
        }
        foreach ($items['agents'] as $name) {
            $abilityKeys[] = 'agent:' . $name;
        }
        if ($presetName !== null && $presetName !== '') {
            $abilityKeys[] = $presetName;
        }
        $abilityKeys = array_values(array_unique($abilityKeys));

        $managedBody = $this->gitIgnore->renderManagedBlock($templatePath, $abilityKeys, $targets);
        if (trim($managedBody) === '') {
            return '[skip] No matched .gitignore template blocks.';
        }

        $gitignorePath = ((string) getcwd()) . '/.gitignore';
        $this->gitIgnore->mergeManagedSection($gitignorePath, $managedBody);

        return '[ok] Updated .gitignore managed section.';
    }
}
