<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    public function testInstallTypedIncludesTypeSpecificOutput(): void
    {
        $installer = new Installer();

        $lines = $installer->installTyped([
            'skills' => ['graphify'],
            'rules' => ['guardrail-core'],
            'agents' => ['gatekeeper'],
        ], ['cursor']);

        $output = implode("\n", $lines);
        self::assertStringContainsString('Installed skill graphify -> cursor', $output);
        self::assertStringContainsString('Installed rule guardrail-core -> cursor', $output);
        self::assertStringContainsString('Installed agent gatekeeper -> cursor', $output);
    }

    public function testRulesAreRenderedAsSteeringOnKiro(): void
    {
        $installer = new Installer();

        $lines = $installer->installTyped([
            'skills' => [],
            'rules' => ['spec-core'],
            'agents' => [],
        ], ['kiro']);

        $output = implode("\n", $lines);
        self::assertStringContainsString('Installed steering spec-core -> kiro', $output);
    }
}
