<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\ProjectInitializer;
use AiProfileManager\Service\ProjectProfileRenderer;
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

        self::assertFileExists($tmp . '/docs/state/README.md');
        self::assertFileExists($tmp . '/issues/README.md');
        self::assertFileExists($tmp . '/AGENTS.md');
        self::assertFileExists($tmp . '/PROJECT.md');
        self::assertFileExists($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileExists($tmp . '/.kiro/steering/kiro-scope.md');
        self::assertStringContainsString('## Full Test Command', (string) file_get_contents($tmp . '/PROJECT.md'));
        self::assertStringContainsString('- confirmed: UNKNOWN', (string) file_get_contents($tmp . '/PROJECT.md'));

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

    public function testInitWritesProvidedProjectProfile(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-init-profile-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $initializer = ProjectInitializer::fromPackageLayout();
        $profile = ProjectProfileRenderer::unknownProfile();
        $profile['full_test_command'] = [
            'detected' => 'npm test',
            'confirmed' => 'pnpm test:ci',
            'confidence' => 'high',
        ];
        $initializer->init($tmp, false, [], $profile);

        $project = (string) file_get_contents($tmp . '/PROJECT.md');
        self::assertStringContainsString('- detected: npm test', $project);
        self::assertStringContainsString('- confirmed: pnpm test:ci', $project);
    }
}
