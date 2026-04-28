<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;

final class PresetRegistryTest extends TestCase
{
    public function testAllPresetsFallsBackToAppConfigWhenManifestMissing(): void
    {
        $root = sys_get_temp_dir() . '/aipm-pr-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);

        $registry = new PresetRegistry($root);

        self::assertSame(AppConfig::PRESET_ITEMS, $registry->allPresets());
        self::assertSame(AppConfig::PRESET_ITEMS['gitflow'], $registry->getPreset('gitflow'));
        self::assertNull($registry->getPreset('nonexistent'));
    }

    public function testLoadFromWorkspaceOverridesAppConfig(): void
    {
        $root = sys_get_temp_dir() . '/aipm-pr2-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities', 0775, true);
        $manifest = [
            'custom' => [
                'skills' => ['s1'],
                'rules' => ['r1'],
                'agents' => [],
            ],
        ];
        file_put_contents($root . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, json_encode($manifest, JSON_UNESCAPED_SLASHES));

        $registry = new PresetRegistry($root);

        self::assertSame(['s1'], $registry->getPreset('custom')['skills']);
        self::assertSame(['r1'], $registry->getPreset('custom')['rules']);
    }

    public function testInvalidManifestJsonFallsBackToAppConfig(): void
    {
        $root = sys_get_temp_dir() . '/aipm-pr3-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities', 0775, true);
        file_put_contents($root . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, '{');

        $registry = new PresetRegistry($root);

        self::assertSame(AppConfig::PRESET_ITEMS, $registry->allPresets());
    }

    public function testSaveToWorkspaceRoundtrip(): void
    {
        $root = sys_get_temp_dir() . '/aipm-pr4-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);

        $registry = new PresetRegistry($root);
        $registry->saveToWorkspace([
            'roundtrip' => [
                'skills' => ['a'],
                'rules' => [],
                'agents' => ['b'],
            ],
        ]);

        $again = new PresetRegistry($root);
        self::assertSame(['a'], $again->getPreset('roundtrip')['skills']);
        self::assertSame(['b'], $again->getPreset('roundtrip')['agents']);
    }
}
