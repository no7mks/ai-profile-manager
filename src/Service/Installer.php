<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Installer
{
    private readonly string $packageRoot;

    private readonly InstallationProbe $installationProbe;

    public function __construct(
        private readonly AbilityRegistry $registry = new AbilityRegistry(__DIR__ . '/../../abilities.yaml'),
        private readonly HookInstaller $hookInstaller = new HookInstaller(),
        private readonly HookChecker $hookChecker = new HookChecker(),
        private readonly GitIgnoreTemplateService $gitIgnore = new GitIgnoreTemplateService(),
        ?string $packageRoot = null,
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
        private readonly DeployRootResolver $rootResolver = new DeployRootResolver(),
        ?InstallationProbe $installationProbe = null,
    ) {
        $this->packageRoot = $packageRoot ?? PackagePaths::packageRoot();
        $this->installationProbe = $installationProbe ?? new InstallationProbe($this->registry, $this->rootResolver);
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>, prompts?: list<string>} $items
     * @param array<int, string> $targets
     * @param string|null $presetName
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function installTyped(
        array $items,
        array $targets,
        ?string $presetName = null,
        bool $skipExisting = false,
    ): array
    {
        $hooks = $items['hooks'] ?? [];
        $prompts = $items['prompts'] ?? [];

        $lines = [];
        $lines[] = 'Installing profile items...';
        $lines[] = 'Scope: project';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
        $lines[] = 'Hooks: ' . $this->formatList($hooks);
        $lines[] = '';

        $exitCode = 0;

        foreach ($targets as $target) {
            foreach ($items['skills'] as $name) {
                if ($skipExisting && $this->isInstalledOnTarget('skill', $name, $target)) {
                    $lines[] = sprintf('[skip] skill:%s (%s)', $name, $target);
                    continue;
                }
                $r = $this->installAbilityBundle('skill', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            foreach ($items['rules'] as $name) {
                if ($skipExisting && $this->isInstalledOnTarget('rule', $name, $target)) {
                    $lines[] = sprintf('[skip] rule:%s (%s)', $name, $target);
                    continue;
                }
                $r = $this->installAbilityBundle('rule', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            foreach ($items['agents'] as $name) {
                if ($skipExisting && $this->isInstalledOnTarget('agent', $name, $target)) {
                    $lines[] = sprintf('[skip] agent:%s (%s)', $name, $target);
                    continue;
                }
                $r = $this->installAbilityBundle('agent', $name, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
            // hooks: dispatch to HookInstaller per target
            foreach ($hooks as $hookName) {
                if ($skipExisting && $this->isInstalledOnTarget('hook', $hookName, $target)) {
                    $lines[] = sprintf('[skip] hook:%s (%s)', $hookName, $target);
                    continue;
                }
                $r = $this->installHook($hookName, $target);
                $lines = array_merge($lines, $r['lines']);
                if ($r['failed']) {
                    $exitCode = 1;
                }
            }
        }

        $gitignoreResult = $this->installGitIgnore($items, $targets, $presetName);
        $lines[] = $gitignoreResult;

        // Prompts: output messages for agent to act on
        if ($prompts !== []) {
            $promptMessages = $this->resolvePromptMessages($prompts);
            if ($promptMessages !== []) {
                $lines[] = '';
                $lines[] = '[prompt] Post-install instructions:';
                foreach ($promptMessages as $message) {
                    $lines[] = $message;
                }
            }
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>} $items
     * @param array<int, string> $targets
     * @param bool $force 是否强制卸载（跳过 drift 检查）
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function uninstallTyped(
        array $items,
        array $targets,
        bool $force = false,
    ): array
    {
        $hooks = $items['hooks'] ?? [];

        $lines = [];
        $lines[] = 'Uninstalling profile items...';
        $lines[] = 'Scope: project';
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
     * Uninstall conventional abilities present under project scope (registry-driven paths).
     *
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function uninstallProjectScope(): array
    {
        $parsed = $this->registry->parse();

        $lines = [];
        $lines[] = 'Uninstalling project-scope conventional abilities...';
        $lines[] = '';

        $exitCode = 0;
        $sections = [
            'skills' => 'skill',
            'rules' => 'rule',
            'agents' => 'agent',
            'hooks' => 'hook',
        ];

        foreach ($sections as $section => $type) {
            foreach ($parsed[$section] as $entry) {
                foreach (array_keys($entry->targets) as $target) {
                    if (!$this->installationProbe->isPresent($type, $entry->path, $target)) {
                        continue;
                    }

                    if ($type === 'hook') {
                        $r = $this->uninstallHook($entry->path, $target, true);
                        $lines = array_merge($lines, $r['lines']);
                        if ($r['failed']) {
                            $exitCode = 1;
                        }
                        continue;
                    }

                    $lines = array_merge(
                        $lines,
                        match ($section) {
                            'skills' => $this->uninstallSkill($entry->path, $target),
                            'rules' => $this->uninstallRule($entry->path, $target),
                            'agents' => $this->uninstallAgent($entry->path, $target),
                            default => [],
                        },
                    );
                }
            }
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    public function registry(): AbilityRegistry
    {
        return $this->registry;
    }

    public function packageRoot(): string
    {
        return $this->packageRoot;
    }

    /**
     * @return array{skills: list<string>, rules: list<string>, agents: list<string>, hooks: list<string>}
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
            'skills' => $skills,
            'rules' => $rules,
            'agents' => $agents,
            'hooks' => $hooks,
        ];
    }

    public function isInstalledOnTarget(string $type, string $name, string $target): bool
    {
        return $this->installationProbe->isPresent($type, $name, $target);
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

        if ($section === null) {
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
     * 从 AbilityRegistry 解析目标路径（scope 根 + targets[target]）。
     */
    private function resolveDestPath(string $type, string $name, string $target): string
    {
        $entry = $this->registry->getEntry($type, $name);
        if ($entry !== null && isset($entry->targets[$target])) {
            return $this->rootResolver->absoluteTargetPath($entry->targets[$target]);
        }

        return $this->rootResolver->absoluteTargetPath($name);
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

        $dst = $this->resolveDestPath($type, $name, $target);

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

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installHook(string $hookName, string $target): array
    {
        $root = $this->rootResolver->resolve();

        if ($target === 'kiro') {
            $sourcePath = $this->packageRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $root . '/.kiro/hooks/' . $hookName . '.kiro.hook';

            $result = $this->hookInstaller->installKiro($sourcePath, $targetPath);
        } else {
            // cursor
            $sourceDir = $this->packageRoot . '/hooks/' . $hookName;
            $targetDir = $root . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $root . '/.cursor/hooks.json';

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
        $root = $this->rootResolver->resolve();

        if ($target === 'kiro') {
            $sourcePath = $this->packageRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $root . '/.kiro/hooks/' . $hookName . '.kiro.hook';

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
            $targetDir = $root . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $root . '/.cursor/hooks.json';

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
        $path = $this->resolveScopedTargetPath('skill', $name, $target);
        if ($path === null || !is_dir($path)) {
            return [sprintf('[miss] Skill %s not found on %s', $name, $target)];
        }

        $this->removeDirectory($path);

        return [sprintf('[ok] Uninstalled skill %s from %s', $name, $target)];
    }

    /**
     * @return array<int, string>
     */
    private function uninstallRule(string $name, string $target): array
    {
        $label = $target === 'kiro' ? 'Steering' : 'Rule';
        $okLabel = $target === 'kiro' ? 'steering' : 'rule';
        $path = $this->resolveScopedTargetPath('rule', $name, $target);
        if ($path === null || !is_file($path)) {
            return [sprintf('[miss] %s %s not found on %s', $label, $name, $target)];
        }

        unlink($path);

        return [sprintf('[ok] Uninstalled %s %s from %s', $okLabel, $name, $target)];
    }

    /**
     * @return array<int, string>
     */
    private function uninstallAgent(string $name, string $target): array
    {
        $path = $this->resolveScopedTargetPath('agent', $name, $target);
        if ($path === null || !is_file($path)) {
            return [sprintf('[miss] Agent %s not found on %s', $name, $target)];
        }
        unlink($path);

        return [sprintf('[ok] Uninstalled agent %s from %s', $name, $target)];
    }

    private function resolveScopedTargetPath(string $type, string $name, string $target): ?string
    {
        $entry = $this->registry->getEntry($type, $name);
        if ($entry === null) {
            return null;
        }

        $relative = $entry->targets[$target] ?? null;
        if ($relative === null) {
            return null;
        }

        return $this->rootResolver->absoluteTargetPath($relative);
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
     * @param list<string> $promptNames
     * @return list<string>
     */
    private function resolvePromptMessages(array $promptNames): array
    {
        $parsed = $this->registry->parse();
        $prompts = $parsed['prompts'];

        $messages = [];
        foreach ($promptNames as $name) {
            foreach ($prompts as $prompt) {
                if (($prompt['name'] ?? '') === $name) {
                    $message = $prompt['message'] ?? '';
                    $messages[] = trim((string) $message);
                    break;
                }
            }
        }

        return $messages;
    }

    /**
     * @param array<int, string> $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '(none)' : implode(', ', $items);
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>} $items
     * @param array<int, string> $targets
     */
    private function installGitIgnore(array $items, array $targets, ?string $presetName): string
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
