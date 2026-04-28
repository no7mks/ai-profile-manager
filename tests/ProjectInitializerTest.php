<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProjectInitializerTest extends TestCase
{
    public function testInitCopiesScaffoldAndBothScopeRules(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $initializer = ProjectInitializer::fromPackageLayout();
        $lines = $initializer->init($tmp, false, AppConfig::DEFAULT_TARGETS);

        self::assertFileExists($tmp . '/docs/README.md');
        self::assertDirectoryExists($tmp . '/docs/state');
        self::assertFileExists($tmp . '/issues/README.md');
        self::assertFileExists($tmp . '/AGENTS.md');
        self::assertFileExists($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileExists($tmp . '/.kiro/steering/kiro-scope.md');

        $joined = implode("\n", $lines);
        self::assertStringContainsString('Scaffold installed', $joined);
        self::assertStringContainsString('cursor-scope.mdc', $joined);
        self::assertStringContainsString('kiro-scope.md', $joined);
    }

    public function testInitWithEmptyTargetsSkipsScopeRules(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-sn-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $initializer = ProjectInitializer::fromPackageLayout();
        $initializer->init($tmp, false, []);

        self::assertFileDoesNotExist($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileDoesNotExist($tmp . '/.kiro/steering/kiro-scope.md');
    }

    public function testInitWithCursorTargetOnlyInstallsCursorScope(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-cur-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $initializer = ProjectInitializer::fromPackageLayout();
        $initializer->init($tmp, false, ['cursor']);

        self::assertFileExists($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileDoesNotExist($tmp . '/.kiro/steering/kiro-scope.md');
    }

    public function testInitFailsWhenScaffoldAlreadyPresentWithoutForce(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-dup-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/docs', 0775, true);

        $initializer = ProjectInitializer::fromPackageLayout();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--force');

        $initializer->init($tmp, false, []);
    }

    public function testInitCreatesMissingTargetDirectory(): void
    {
        $parent = sys_get_temp_dir() . '/aipm-init-nested-' . bin2hex(random_bytes(4));
        mkdir($parent, 0775, true);
        $nested = $parent . '/new-proj';

        $initializer = ProjectInitializer::fromPackageLayout();
        $initializer->init($nested, false, []);

        self::assertDirectoryExists($nested);
        self::assertFileExists($nested . '/AGENTS.md');
    }
}
