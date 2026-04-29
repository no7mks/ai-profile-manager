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
        private readonly GitIgnoreTemplateService $gitIgnore = new GitIgnoreTemplateService(),
        private readonly ?string $templatePath = null,
        ?string $packageRoot = null,
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
    ) {
        $this->packageRoot = $packageRoot ?? PackagePaths::packageRoot();
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @param string|null $presetName
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function installTyped(array $items, array $targets, ?string $presetName = null): array
    {
        $lines = [];
        $lines[] = 'Installing profile items...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
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
        }

        $gitignoreResult = $this->installGitIgnore($items, $targets, $presetName);
        if ($gitignoreResult !== null) {
            $lines[] = $gitignoreResult;
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @param array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>} $items
     * @param array<int, string> $targets
     * @return array{lines: array<int, string>, exit_code: int}
     */
    public function uninstallTyped(array $items, array $targets): array
    {
        $lines = [];
        $lines[] = 'Uninstalling profile items...';
        $lines[] = 'Targets: ' . implode(', ', $targets);
        $lines[] = 'Skills: ' . $this->formatList($items['skills']);
        $lines[] = 'Rules: ' . $this->formatList($items['rules']);
        $lines[] = 'Agents: ' . $this->formatList($items['agents']);
        $lines[] = '';

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
        }

        return ['lines' => $lines, 'exit_code' => 0];
    }

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installAbilityBundle(string $type, string $name, string $target): array
    {
        if ($type === 'rule') {
            return $this->installRuleBundle($name, $target);
        }

        if ($type === 'agent') {
            return $this->installAgentFile($name, $target);
        }

        $src = $this->packageRoot . '/abilities/skills/' . $name;
        $dst = $this->resolveInstallTargetDir('skill', $name, $target);

        if (!is_dir($src)) {
            return [
                'lines' => [sprintf('[fail] Missing ability bundle: skill %s (expected %s)', $name, $src)],
                'failed' => true,
            ];
        }

        try {
            $this->mirror->mirrorDirectory($src, $dst);
        } catch (\Throwable $e) {
            return [
                'lines' => [sprintf('[fail] Install copy failed (skill %s -> %s): %s', $name, $target, $e->getMessage())],
                'failed' => true,
            ];
        }

        return [
            'lines' => [sprintf('[ok] Installed skill %s -> %s', $name, $target)],
            'failed' => false,
        ];
    }

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installAgentFile(string $name, string $target): array
    {
        $src = $this->packageRoot . '/abilities/agents/' . $name . '.' . $target . '.md';
        $dst = $this->resolveInstallTargetAgentFile($name, $target);

        if (!is_file($src)) {
            return [
                'lines' => [sprintf('[fail] Missing ability bundle: agent %s (expected %s)', $name, $src)],
                'failed' => true,
            ];
        }

        try {
            $this->mirror->ensureDirectory(dirname($dst));
            $this->mirror->copyFile($src, $dst, true);
        } catch (\Throwable $e) {
            return [
                'lines' => [sprintf('[fail] Install copy failed (agent %s -> %s): %s', $name, $target, $e->getMessage())],
                'failed' => true,
            ];
        }

        return [
            'lines' => [sprintf('[ok] Installed agent %s -> %s', $name, $target)],
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

    /**
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installRuleBundle(string $name, string $target): array
    {
        $sources = $this->findRuleSourceFiles($name, $target);
        if ($sources === []) {
            $hint = $target === 'cursor'
                ? $this->packageRoot . '/abilities/rules/**/' . $name . '.cursor.(mdc|md)'
                : $this->packageRoot . '/abilities/rules/**/' . $name . '.kiro.(md|mdc)';

            return [
                'lines' => [sprintf('[fail] Missing ability bundle: rule %s (expected under %s)', $name, $hint)],
                'failed' => true,
            ];
        }

        $byDest = [];
        foreach ($sources as $src) {
            $dst = $this->resolveInstallTargetRuleFile($src, $name, $target);
            $byDest[$dst][] = $src;
        }

        foreach ($byDest as $dst => $group) {
            $src = $this->pickPreferredRuleSource($group, $target);

            try {
                $this->mirror->copyFile($src, $dst, true);
            } catch (\Throwable $e) {
                return [
                    'lines' => [sprintf('[fail] Install copy failed (rule %s -> %s): %s', $name, $target, $e->getMessage())],
                    'failed' => true,
                ];
            }
        }

        $label = $target === 'kiro' ? 'steering' : 'rule';

        return [
            'lines' => [sprintf('[ok] Installed %s %s -> %s', $label, $name, $target)],
            'failed' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function findRuleSourceFiles(string $name, string $target): array
    {
        $suffixes = $target === 'cursor'
            ? ['.cursor.mdc', '.cursor.md']
            : ['.kiro.md', '.kiro.mdc'];
        $rulesRoot = $this->packageRoot . '/abilities/rules';
        if (!is_dir($rulesRoot)) {
            return [];
        }

        $sources = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rulesRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $basename = $fileInfo->getBasename();
            foreach ($suffixes as $suffix) {
                if ($basename === $name . $suffix) {
                    $sources[] = $fileInfo->getPathname();
                    break;
                }
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * @param array<int, string> $paths
     */
    private function pickPreferredRuleSource(array $paths, string $target): string
    {
        if (count($paths) === 1) {
            return $paths[0];
        }

        $rank = static function (string $p) use ($target): int {
            $b = basename($p);
            if ($target === 'cursor') {
                if (str_ends_with($b, '.cursor.mdc')) {
                    return 0;
                }

                return str_ends_with($b, '.cursor.md') ? 1 : 2;
            }
            if (str_ends_with($b, '.kiro.md')) {
                return 0;
            }

            return str_ends_with($b, '.kiro.mdc') ? 1 : 2;
        };

        usort($paths, static fn (string $a, string $b): int => $rank($a) <=> $rank($b));

        return $paths[0];
    }

    private function resolveInstallTargetDir(string $type, string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $base = $target === 'cursor' ? $cwd . '/.cursor' : $cwd . '/.kiro';

        return match ($type) {
            'skill' => $base . '/skills/' . $name,
            default => $cwd . '/abilities/unknown-items/' . $name . '/' . $target,
        };
    }

    private function resolveInstallTargetRuleFile(string $sourcePath, string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $normalized = str_replace('\\', '/', $sourcePath);
        $prefix = str_replace('\\', '/', $this->packageRoot . '/abilities/rules/');
        $category = '';
        if (str_starts_with($normalized, $prefix)) {
            $rest = substr($normalized, strlen($prefix));
            $segments = explode('/', $rest);
            array_pop($segments);
            if ($segments !== []) {
                $category = implode('/', $segments);
            }
        }

        if ($target === 'cursor') {
            $dir = $cwd . '/.cursor/rules' . ($category !== '' ? '/' . $category : '');

            return $dir . '/' . $name . '.mdc';
        }

        $dir = $cwd . '/.kiro/steering' . ($category !== '' ? '/' . $category : '');

        return $dir . '/' . $name . '.md';
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
        $templatePath = $this->templatePath ?? ((string) getcwd()) . '/abilities/gitignore/template.gitignore';
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
