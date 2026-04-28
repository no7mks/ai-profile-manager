<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;

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
     * @return array{lines: array<int, string>, failed: bool}
     */
    private function installAbilityBundle(string $type, string $name, string $target): array
    {
        if ($type === 'rule') {
            return $this->installRuleBundle($name, $target);
        }

        $typeDir = match ($type) {
            'skill' => 'abilities/skills',
            'rule' => 'abilities/rules',
            'agent' => 'abilities/agents',
            default => 'abilities/unknown-items',
        };

        $src = $this->packageRoot . '/' . $typeDir . '/' . $name . '/' . $target;
        $dst = $this->resolveInstallTargetDir($type, $name, $target);

        if (!is_dir($src)) {
            return [
                'lines' => [sprintf('[fail] Missing ability bundle: %s %s (expected %s)', $type, $name, $src)],
                'failed' => true,
            ];
        }

        try {
            $this->mirror->mirrorDirectory($src, $dst);
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
    private function installRuleBundle(string $name, string $target): array
    {
        $suffix = $target === 'cursor' ? '.cursor.mdc' : '.kiro.md';
        $patternByCategory = $this->packageRoot . '/abilities/rules/*/' . $name . $suffix;
        $patternTopLevel = $this->packageRoot . '/abilities/rules/' . $name . $suffix;
        $sources = array_merge(glob($patternByCategory) ?: [], glob($patternTopLevel) ?: []);
        $sources = array_values(array_unique($sources));

        if ($sources === []) {
            return [
                'lines' => [sprintf('[fail] Missing ability bundle: rule %s (expected %s or %s)', $name, $patternByCategory, $patternTopLevel)],
                'failed' => true,
            ];
        }

        foreach ($sources as $src) {
            $dst = $this->resolveInstallTargetRuleFile($src, $name, $target);

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

    private function resolveInstallTargetDir(string $type, string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $base = $target === 'cursor' ? $cwd . '/.cursor' : $cwd . '/.kiro';

        return match ($type) {
            'skill' => $base . '/skills/' . $name,
            'agent' => $base . '/agents',
            default => $cwd . '/abilities/unknown-items/' . $name . '/' . $target,
        };
    }

    private function resolveInstallTargetRuleFile(string $sourcePath, string $name, string $target): string
    {
        $cwd = (string) getcwd();
        $suffix = $target === 'cursor' ? '.cursor.mdc' : '.kiro.md';
        $pattern = '#/abilities/rules/(.+)/' . preg_quote($name . $suffix, '#') . '$#';
        $category = '';
        if (preg_match($pattern, str_replace('\\', '/', $sourcePath), $m) === 1) {
            $category = $m[1];
        }

        if ($target === 'cursor') {
            $dir = $cwd . '/.cursor/rules' . ($category !== '' ? '/' . $category : '');

            return $dir . '/' . $name . '.mdc';
        }

        $dir = $cwd . '/.kiro/steering' . ($category !== '' ? '/' . $category : '');

        return $dir . '/' . $name . '.md';
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
