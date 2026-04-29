<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RuntimeException;

final class GitIgnoreTemplateService
{
    private const BEGIN_MARKER = '# BEGIN apm-managed-gitignore v1';
    private const END_MARKER = '# END apm-managed-gitignore v1';

    /**
     * @param array<int, string> $abilityKeys
     * @param array<int, string> $targets
     */
    public function renderManagedBlock(string $templatePath, array $abilityKeys, array $targets): string
    {
        if (!is_file($templatePath)) {
            return '';
        }

        $abilityMap = array_fill_keys($abilityKeys, true);
        $targetMap = array_fill_keys($targets, true);
        $lines = file($templatePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Failed to read template file: %s', $templatePath));
        }

        $rendered = [];
        $inBlock = false;
        $blockMatches = false;
        $blockPatterns = [];

        foreach ($lines as $line) {
            if (!$inBlock && preg_match('/^## @apm:block ability=([^ ]+) target=([^ ]+)$/', $line, $m) === 1) {
                $inBlock = true;
                $ability = $m[1];
                $target = $m[2];
                $blockMatches = isset($abilityMap[$ability]) && ($target === '*' || isset($targetMap[$target]));
                $blockPatterns = [];
                continue;
            }

            if ($inBlock && $line === '## @apm:end') {
                if ($blockMatches && $blockPatterns !== []) {
                    if ($rendered !== []) {
                        $rendered[] = '';
                    }
                    foreach ($blockPatterns as $pattern) {
                        $rendered[] = $pattern;
                    }
                }
                $inBlock = false;
                $blockMatches = false;
                $blockPatterns = [];
                continue;
            }

            if ($inBlock && $blockMatches) {
                if (trim($line) !== '') {
                    $blockPatterns[] = $line;
                }
            }
        }

        if ($inBlock) {
            throw new RuntimeException(sprintf('Unclosed apm block in template: %s', $templatePath));
        }

        return implode("\n", $rendered);
    }

    public function mergeManagedSection(string $gitignorePath, string $managedBody): void
    {
        if (trim($managedBody) === '') {
            return;
        }

        $managedSection = self::BEGIN_MARKER . "\n" . $managedBody . "\n" . self::END_MARKER;
        $current = is_file($gitignorePath) ? (string) file_get_contents($gitignorePath) : '';
        if ($current !== '' && !str_ends_with($current, "\n")) {
            $current .= "\n";
        }

        $pattern = '/^# BEGIN apm-managed-gitignore v1\n.*?\n# END apm-managed-gitignore v1\n?/ms';
        if (preg_match($pattern, $current) === 1) {
            $next = (string) preg_replace($pattern, $managedSection . "\n", $current, 1);
            file_put_contents($gitignorePath, $next);
            return;
        }

        if ($current !== '' && trim($current) !== '') {
            $current = rtrim($current, "\n") . "\n\n";
        }

        file_put_contents($gitignorePath, $current . $managedSection . "\n");
    }
}
