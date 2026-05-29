<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use AiProfileManager\Tests\Support\RemovesDirTrait;

/**
 * Base class for E2E tests that invoke bin/apm as a real CLI process.
 *
 * Each test gets an isolated temp directory as the "workspace" (cwd for apm)
 * and a separate "package root" directory that mimics the installed package layout.
 * A thin wrapper script is generated to override PackagePaths::packageRoot().
 */
abstract class EndToEndTestCase extends TestCase
{
    use RemovesDirTrait;

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
use AiProfileManager\Service\PresetRegistry;
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
\$presetRegistry = \$registry !== null ? new PresetRegistry(\$registry) : null;
\$app = Application::createSymfonyApplication(\$installer, \$updater, \$presetRegistry);
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

    /**
     * Create a skill source in the package root (new layout: directly at target path).
     * Per PRP-001, skills live at .<target>/skills/<name>/ in the package root.
     */
    protected function createSkillSource(string $name, string $content = "# Skill\n"): void
    {
        // New layout: source IS the target path within the package root
        foreach (['cursor', 'kiro'] as $target) {
            $prefix = $target === 'cursor' ? '.cursor' : '.kiro';
            $dir = "{$this->packageRoot}/{$prefix}/skills/{$name}";
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents("{$dir}/SKILL.md", $content);
        }
    }

    /**
     * Create a rule source in the package root (new layout: directly at target path).
     * Per PRP-001, rules live at .cursor/rules/<category>/<name>.mdc or .kiro/steering/<category>/<name>.md.
     */
    protected function createRuleSource(string $name, string $category, string $target, string $content = "rule\n"): void
    {
        if ($target === 'cursor') {
            $dir = "{$this->packageRoot}/.cursor/rules/{$category}";
            $file = "{$name}.mdc";
        } else {
            $dir = "{$this->packageRoot}/.kiro/steering/{$category}";
            $file = "{$name}.md";
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents("{$dir}/{$file}", $content);
    }

    /**
     * Create an agent source in the package root (new layout: directly at target path).
     * Per PRP-001, agents live at .<target>/agents/<name>.md.
     */
    protected function createAgentSource(string $name, string $target, string $content = "# Agent\n"): void
    {
        $prefix = $target === 'cursor' ? '.cursor' : '.kiro';
        $dir = "{$this->packageRoot}/{$prefix}/agents";
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents("{$dir}/{$name}.md", $content);
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
     * Create presets in abilities.yaml (appends/merges presets section).
     *
     * Accepts the legacy typed format used by E2E tests:
     *   ['preset-name' => ['skills' => [...], 'rules' => [...], 'agents' => [...]]]
     * and converts to the abilities.yaml presets section format:
     *   presets:
     *     - name: preset-name
     *       description: ''
     *       includes:
     *         - skill:skill-name
     *         - rule:rule-name
     *
     * @param array<string, array<string, mixed>> $presets
     */
    protected function createPresets(array $presets): void
    {
        $yamlPath = $this->packageRoot . '/abilities.yaml';

        // Read existing abilities.yaml content if present
        $data = [];
        if (file_exists($yamlPath)) {
            $content = file_get_contents($yamlPath);
            if ($content !== false && $content !== '') {
                $data = \Symfony\Component\Yaml\Yaml::parse($content) ?? [];
            }
        }

        // Convert legacy typed format to abilities.yaml presets section
        $yamlPresets = [];
        foreach ($presets as $name => $spec) {
            $includes = [];
            foreach (['skills' => 'skill', 'rules' => 'rule', 'agents' => 'agent', 'hooks' => 'hook'] as $key => $type) {
                foreach (($spec[$key] ?? []) as $path) {
                    $includes[] = "{$type}:{$path}";
                }
            }
            $yamlPresets[] = [
                'name' => $name,
                'description' => $spec['description'] ?? '',
                'includes' => $includes,
            ];
        }

        $data['presets'] = $yamlPresets;

        file_put_contents($yamlPath, \Symfony\Component\Yaml\Yaml::dump($data, 4, 2));
    }

    protected function createGitignoreTemplate(string $content): void
    {
        file_put_contents($this->packageRoot . '/.gitignore', $content);
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

}
