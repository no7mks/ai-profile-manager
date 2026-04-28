<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * @phpstan-type Field array{detected: string, confirmed: string, confidence: 'high'|'medium'|'low'}
 * @phpstan-type Profile array{
 *   project_stack: Field,
 *   full_test_command: Field,
 *   build_command: Field,
 *   run_entry: Field,
 *   version_locations: Field,
 *   sensitive_files: Field
 * }
 */
final class ProjectProfileRenderer
{
    /**
     * @return Profile
     */
    public static function unknownProfile(): array
    {
        return [
            'project_stack' => self::unknownField(),
            'full_test_command' => self::unknownField(),
            'build_command' => self::unknownField(),
            'run_entry' => self::unknownField(),
            'version_locations' => self::unknownField(),
            'sensitive_files' => self::unknownField(),
        ];
    }

    /**
     * @param array<string, array{detected: string, confidence: 'high'|'medium'|'low'}> $detected
     * @param array<string, string> $confirmed
     * @return Profile
     */
    public function buildProfile(array $detected, array $confirmed): array
    {
        $profile = self::unknownProfile();
        foreach (array_keys($profile) as $key) {
            $detectedValue = $detected[$key]['detected'] ?? 'UNKNOWN';
            $confidence = $detected[$key]['confidence'] ?? 'low';
            $confirmedValue = $confirmed[$key] ?? $detectedValue;
            $profile[$key] = [
                'detected' => $detectedValue,
                'confirmed' => $confirmedValue,
                'confidence' => $confidence,
            ];
        }

        return $profile;
    }

    /**
     * @param Profile $profile
     */
    public function renderMarkdown(array $profile): string
    {
        return implode("\n", [
            '# Project Profile',
            '',
            '> 本文件由 `aipm init` 生成，用于提供项目上下文给 Agent。',
            '> Agent 应优先读取每项的 `confirmed`；若为 `UNKNOWN`，需先向用户确认。',
            '',
            '## Project Stack',
            '- detected: ' . $profile['project_stack']['detected'],
            '- confirmed: ' . $profile['project_stack']['confirmed'],
            '- confidence: ' . $profile['project_stack']['confidence'],
            '',
            '## Full Test Command',
            '- detected: ' . $profile['full_test_command']['detected'],
            '- confirmed: ' . $profile['full_test_command']['confirmed'],
            '- confidence: ' . $profile['full_test_command']['confidence'],
            '',
            '## Build Command',
            '- detected: ' . $profile['build_command']['detected'],
            '- confirmed: ' . $profile['build_command']['confirmed'],
            '- confidence: ' . $profile['build_command']['confidence'],
            '',
            '## Run Entry',
            '- detected: ' . $profile['run_entry']['detected'],
            '- confirmed: ' . $profile['run_entry']['confirmed'],
            '- confidence: ' . $profile['run_entry']['confidence'],
            '',
            '## Version Locations',
            '- detected: ' . $profile['version_locations']['detected'],
            '- confirmed: ' . $profile['version_locations']['confirmed'],
            '- confidence: ' . $profile['version_locations']['confidence'],
            '',
            '## Sensitive Files',
            '- detected: ' . $profile['sensitive_files']['detected'],
            '- confirmed: ' . $profile['sensitive_files']['confirmed'],
            '- confidence: ' . $profile['sensitive_files']['confidence'],
            '',
        ]);
    }

    /**
     * @return Field
     */
    private static function unknownField(): array
    {
        return [
            'detected' => 'UNKNOWN',
            'confirmed' => 'UNKNOWN',
            'confidence' => 'low',
        ];
    }
}
