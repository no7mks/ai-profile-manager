<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Base class for E2E tests that invoke bin/apm as a real CLI process.
 *
 * Each test gets an isolated temp directory as the "workspace" (cwd for apm)
 * and a separate "package root" directory that mimics the installed package layout.
 * A thin wrapper script is generated to override PackagePaths::packageRoot().
 */
abstract class EndToEndTestCase extends TestCase
{
    /** Workspace directory where apm commands run (simulates user's project). */
    protected string $workspace;

    /** Package root with abilities/skills, abilities/rules, abilities/agents, hooks. */
    protected string $packageRoot;

    /** Path to the generated wrapper script. */
    private string $wrapperScript;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/apm-e2e-' . bin2hex(random_bytes(6));
        $this->workspace = $base . '/workspace';
        $this->packageRoot = $base . '/pkg';

        mkdir($this->workspace, 0775, true);
        mkdir($this->packageRoot, 0775, true);

        // Generate a wrapper script that uses the real autoloader but overrides package root
        $this->wrapperScript = $base . '/apm-e2e.php';
        $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = <<<PHP
#!/usr/bin/env php
<?php
declare(strict_types=1);

require '{$vendorAutoload}';

use AiProfileManager\Core\Application;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

\$packageRoot = getenv('APM_PACKAGE_ROOT') ?: __DIR__;
\$registryPath = \$packageRoot . '/abilities.yaml';
\$registry = file_exists(\$registryPath) ? new AbilityRegistry(\$registryPath) : null;
\$installerArgs = ['packageRoot' => \$packageRoot];
if (\$registry !== null) {
    \$installerArgs['registry'] = \$registry;
}
\$installer = new Installer(...\$installerArgs);
\$updater = new KnowledgeBaseUpdater();
\$app = Application::createSymfonyApplication(\$installer, \$updater);
exit(\$app->run(new ArgvInput(\$argv), new ConsoleOutput()));
PHP;
        file_put_contents($this->wrapperScript, $script);
        chmod($this->wrapperScript, 0755);
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->workspace));
    }

    /**
     * Run apm with given arguments in the workspace directory.
     *
     * @param array<int, string> $args CLI arguments (e.g. ['install', 'my-preset', '-t', 'cursor'])
     * @param array<string, string> $extraEnv Extra environment variables
     * @return array{exit: int, stdout: string, stderr: string}
     */
    protected function apm(array $args, array $extraEnv = []): array
    {
        $process = new Process(
            command: ['php', $this->wrapperScript, ...$args],
            cwd: $this->workspace,
            env: [
                'APM_PACKAGE_ROOT' => $this->packageRoot,
                'APM_BASELINE_ROOT' => $this->packageRoot,
                ...$extraEnv,
            ],
        );
        $process->run();

        return [
            'exit' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    // ─── Fixture helpers ─────────────────────────────────────────────

    protected function createSkillSource(string $name, string $content = "# Skill\n"): void
    {
        $dir = $this->packageRoot . '/abilities/skills/' . $name;
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/SKILL.md', $content);
    }

    protected function createRuleSource(string $name, string $category, string $target, string $content = "rule\n"): void
    {
        $dir = $this->packageRoot . '/abilities/rules/' . $category;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $suffix = $target === 'cursor' ? '.cursor.mdc' : '.kiro.md';
        file_put_contents($dir . '/' . $name . $suffix, $content);
    }

    protected function createAgentSource(string $name, string $target, string $content = "# Agent\n"): void
    {
        $dir = $this->packageRoot . '/abilities/agents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.' . $target . '.md', $content);
    }

    protected function createHookSourceKiro(string $name, string $content): void
    {
        $dir = $this->packageRoot . '/hooks';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.kiro.hook', $content);
    }

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    protected function createPresets(array $presets): void
    {
        $dir = $this->workspace . '/abilities';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir . '/_presets.json',
            json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    protected function createGitignoreTemplate(string $content): void
    {
        $dir = $this->workspace . '/abilities/gitignore';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/template.gitignore', $content);
    }

    protected function createAbilitiesYaml(string $content): void
    {
        file_put_contents($this->packageRoot . '/abilities.yaml', $content);
    }

    // ─── Assertion helpers ───────────────────────────────────────────

    protected function assertFileInWorkspace(string $relativePath): void
    {
        self::assertFileExists($this->workspace . '/' . $relativePath);
    }

    protected function assertFileNotInWorkspace(string $relativePath): void
    {
        self::assertFileDoesNotExist($this->workspace . '/' . $relativePath);
    }

    protected function readWorkspaceFile(string $relativePath): string
    {
        return (string) file_get_contents($this->workspace . '/' . $relativePath);
    }

    // ─── Cleanup ─────────────────────────────────────────────────────

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
