<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\ComposerBaselineResolver;
use AiProfileManager\Service\GlobalInstallDetector;
use PHPUnit\Framework\TestCase;

final class GlobalInstallDetectorTest extends TestCase
{
    public function testDetectsGlobalVendorBinInvocation(): void
    {
        $home = sys_get_temp_dir() . '/apm-global-det-' . bin2hex(random_bytes(4));
        $globalBin = $home . '/.composer/vendor/bin/apm';
        mkdir(dirname($globalBin), 0775, true);
        touch($globalBin);

        $detector = new GlobalInstallDetector(composerHomesOverride: [$home . '/.composer']);

        self::assertTrue($detector->isGlobalInvocation($globalBin));
    }

    public function testRejectsProjectVendorBinInvocation(): void
    {
        $home = sys_get_temp_dir() . '/apm-proj-det-' . bin2hex(random_bytes(4));
        $projectBin = $home . '/my-repo/vendor/bin/apm';
        mkdir(dirname($projectBin), 0775, true);
        touch($projectBin);

        $detector = new GlobalInstallDetector(composerHomesOverride: [$home . '/.composer']);

        self::assertFalse($detector->isGlobalInvocation($projectBin));
    }

    public function testEmptyArgvIsNotGlobal(): void
    {
        $detector = new GlobalInstallDetector();

        self::assertFalse($detector->isGlobalInvocation(''));
    }
}
