<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Bootstrap lifecycle — `apm bootstrap`.
 *
 * Validates scaffold-only initialization (docs/, issues/, AGENTS.md)
 * via real CLI process invocation.
 */
final class BootstrapLifecycleTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        mkdir($this->packageRoot . '/docs', 0775, true);
        file_put_contents($this->packageRoot . '/docs/README.md', "# Docs\n");
        mkdir($this->packageRoot . '/issues', 0775, true);
        file_put_contents($this->packageRoot . '/issues/README.md', "# Issues\n");
        file_put_contents($this->packageRoot . '/AGENTS.md', "# Agents\n");
    }

    public function testBootstrapInstallsScaffoldOnly(): void
    {
        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        $this->assertFileInWorkspace('docs/README.md');
        $this->assertFileInWorkspace('issues/README.md');
        $this->assertFileInWorkspace('AGENTS.md');

        self::assertStringContainsString('Installing scaffold', $r['stdout']);
        self::assertStringContainsString('Scaffold installed', $r['stdout']);
        self::assertStringContainsString('Bootstrap complete', $r['stdout']);
        self::assertStringNotContainsString('Installing default preset', $r['stdout']);
        self::assertStringNotContainsString('Installed skill', $r['stdout']);
    }

    public function testBootstrapSucceedsWhenNoTargetSpecified(): void
    {
        $r = $this->apm(['bootstrap']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");

        $this->assertFileInWorkspace('docs/README.md');
        $this->assertFileInWorkspace('issues/README.md');
        $this->assertFileInWorkspace('AGENTS.md');
    }

    public function testBootstrapBlockedWhenScaffoldExists(): void
    {
        mkdir($this->workspace . '/docs', 0775, true);
        file_put_contents($this->workspace . '/docs/README.md', "existing\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(1, $r['exit']);
        self::assertStringContainsString('--force', $r['stdout']);
    }

    public function testBootstrapForceOverwritesExistingScaffold(): void
    {
        mkdir($this->workspace . '/docs', 0775, true);
        file_put_contents($this->workspace . '/docs/README.md', "existing\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor', '--force']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        self::assertStringStartsWith("# Docs\n", $this->readWorkspaceFile('docs/README.md'));
        self::assertStringNotContainsString('existing', $this->readWorkspaceFile('docs/README.md'));
    }
}
