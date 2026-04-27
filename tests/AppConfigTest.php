<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\AppConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testDefaultSkillsAreNotEmpty(): void
    {
        self::assertNotEmpty(AppConfig::DEFAULT_SKILLS);
    }

    public function testKnownPresetsHavePresetItems(): void
    {
        foreach (AppConfig::KNOWN_PRESETS as $preset) {
            self::assertArrayHasKey($preset, AppConfig::PRESET_ITEMS);
        }
    }

    public function testPresetItemsUseStringLists(): void
    {
        foreach (AppConfig::PRESET_ITEMS as $items) {
            self::assertIsArray($items['skills']);
            self::assertIsArray($items['rules']);
            self::assertIsArray($items['agents']);

            foreach ($items['skills'] as $skill) {
                self::assertIsString($skill);
            }
            foreach ($items['rules'] as $rule) {
                self::assertIsString($rule);
            }
            foreach ($items['agents'] as $agent) {
                self::assertIsString($agent);
            }
        }
    }

    public function testDefaultTargetsAreKnown(): void
    {
        foreach (AppConfig::DEFAULT_TARGETS as $target) {
            self::assertContains($target, AppConfig::KNOWN_TARGETS);
        }
    }
}
