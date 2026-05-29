<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class ProjectInitializerExtendedTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-projinit-ext-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testInitInstallsCursorScopeRule(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        mkdir($pkg . '/.cursor/rules', 0775, true);
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/.gitkeep', '');
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor scope\n");

        $target = $this->tmpDir . '/target';
        mkdir($target, 0775, true);

        $init = new ProjectInitializer($pkg, new DirectoryMirrorService());
        $lines = $init->init($target, false, ['cursor']);

        $output = implode("\n", $lines);
        self::assertStringContainsString('Installed Cursor scope rule', $output);
        self::assertFileExists($target . '/.cursor/rules/cursor-scope.mdc');
    }

    public function testInitInstallsKiroScopeRule(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/.gitkeep', '');
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro scope\n");

        $target = $this->tmpDir . '/target';
        mkdir($target, 0775, true);

        $init = new ProjectInitializer($pkg, new DirectoryMirrorService());
        $lines = $init->init($target, false, ['kiro']);

        $output = implode("\n", $lines);
        self::assertStringContainsString('Installed Kiro scope steering', $output);
        self::assertFileExists($target . '/.kiro/steering/kiro-scope.md');
    }

    public function testInitThrowsWhenScopeRuleExistsWithoutForce(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        mkdir($pkg . '/.cursor/rules', 0775, true);
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/.gitkeep', '');
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "scope\n");

        $target = $this->tmpDir . '/target';
        mkdir($target . '/.cursor/rules', 0775, true);
        file_put_contents($target . '/.cursor/rules/cursor-scope.mdc', "existing\n");

        $init = new ProjectInitializer($pkg, new DirectoryMirrorService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cursor-scope.mdc');
        $init->init($target, false, ['cursor']);
    }

    public function testInitThrowsWhenKiroScopeExistsWithoutForce(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/.gitkeep', '');
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "scope\n");

        $target = $this->tmpDir . '/target';
        mkdir($target . '/.kiro/steering', 0775, true);
        file_put_contents($target . '/.kiro/steering/kiro-scope.md', "existing\n");

        $init = new ProjectInitializer($pkg, new DirectoryMirrorService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kiro-scope.md');
        $init->init($target, false, ['kiro']);
    }

    public function testInitSkipsScopeRulesWhenNoTargets(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/.gitkeep', '');

        $target = $this->tmpDir . '/target';
        mkdir($target, 0775, true);

        $init = new ProjectInitializer($pkg, new DirectoryMirrorService());
        $lines = $init->init($target, false, []);

        $output = implode("\n", $lines);
        self::assertStringContainsString('Skipping scope rules', $output);
    }

    public function testFromPackageLayoutCreatesInstance(): void
    {
        $init = ProjectInitializer::fromPackageLayout();
        self::assertInstanceOf(ProjectInitializer::class, $init);
    }

}
