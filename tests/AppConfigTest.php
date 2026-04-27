<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\AppConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testDefaultAbilitiesAreKnown(): void
    {
        foreach (AppConfig::DEFAULT_ABILITIES as $ability) {
            self::assertContains($ability, AppConfig::KNOWN_ABILITIES);
        }
    }

    public function testDefaultTargetsAreKnown(): void
    {
        foreach (AppConfig::DEFAULT_TARGETS as $target) {
            self::assertContains($target, AppConfig::KNOWN_TARGETS);
        }
    }
}
