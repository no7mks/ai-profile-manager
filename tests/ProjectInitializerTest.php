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
        $tmp = sys_get_temp_dir() . '/apm-init-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        // Create a fake package root with scaffold files at root level and scope rules
        $pkg = sys_get_temp_dir() . '/apm-init-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs/state', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");

        $initializer = new ProjectInitializer($pkg);
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
        $tmp = sys_get_temp_dir() . '/apm-init-sn-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $pkg = sys_get_temp_dir() . '/apm-init-sn-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs/state', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");

        $initializer = new ProjectInitializer($pkg);
        $initializer->init($tmp, false, []);

        self::assertFileDoesNotExist($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileDoesNotExist($tmp . '/.kiro/steering/kiro-scope.md');
    }

    public function testInitWithCursorTargetOnlyInstallsCursorScope(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-init-cur-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $pkg = sys_get_temp_dir() . '/apm-init-cur-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs/state', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");

        $initializer = new ProjectInitializer($pkg);
        $initializer->init($tmp, false, ['cursor']);

        self::assertFileExists($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileDoesNotExist($tmp . '/.kiro/steering/kiro-scope.md');
    }

    public function testInitFailsWhenScaffoldAlreadyPresentWithoutForce(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-init-dup-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/docs', 0775, true);

        $pkg = sys_get_temp_dir() . '/apm-init-dup-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs/state', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");

        $initializer = new ProjectInitializer($pkg);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--force');

        $initializer->init($tmp, false, []);
    }

    public function testInitCreatesMissingTargetDirectory(): void
    {
        $parent = sys_get_temp_dir() . '/apm-init-nested-' . bin2hex(random_bytes(4));
        mkdir($parent, 0775, true);
        $nested = $parent . '/new-proj';

        $pkg = sys_get_temp_dir() . '/apm-init-nested-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs/state', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");

        $initializer = new ProjectInitializer($pkg);
        $initializer->init($nested, false, []);

        self::assertDirectoryExists($nested);
        self::assertFileExists($nested . '/AGENTS.md');
    }

    public function testInitFailsWhenTargetPathIsAFile(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-init-file-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "x\n");
        file_put_contents($pkg . '/issues/README.md', "x\n");
        file_put_contents($pkg . '/AGENTS.md', "x\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "x\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "x\n");

        $target = sys_get_temp_dir() . '/apm-init-file-target-' . bin2hex(random_bytes(4));
        file_put_contents($target, "not a dir\n");

        $initializer = new ProjectInitializer($pkg);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a directory');

        $initializer->init($target, false, []);
    }

    public function testInitFailsWhenScopeRuleBundleMissing(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-init-miss-rule-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "x\n");
        file_put_contents($pkg . '/issues/README.md', "x\n");
        file_put_contents($pkg . '/AGENTS.md', "x\n");
        // Only create kiro scope, omit cursor scope
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "x\n");

        $target = sys_get_temp_dir() . '/apm-init-miss-rule-target-' . bin2hex(random_bytes(4));
        mkdir($target, 0775, true);

        $initializer = new ProjectInitializer($pkg);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cursor-scope bundle missing');

        $initializer->init($target, false, ['cursor']);
    }

    public function testInitFailsWhenScaffoldLeafMissing(): void
    {
        $pkg = sys_get_temp_dir() . '/apm-init-miss-scaffold-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "x\n");
        file_put_contents($pkg . '/AGENTS.md', "x\n");
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "x\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "x\n");

        $target = sys_get_temp_dir() . '/apm-init-miss-scaffold-target-' . bin2hex(random_bytes(4));
        mkdir($target, 0775, true);

        $initializer = new ProjectInitializer($pkg);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('issues missing');

        $initializer->init($target, false, []);
    }
}
