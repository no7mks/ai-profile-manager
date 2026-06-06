<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\ShowStatusPresenter;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ShowStatusPresenterTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    public function testMapsUnchangedCheckStatusToInstalled(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-ok-rule', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        self::assertCount(1, $lines);
        self::assertStringContainsString('installed', $lines[0]);
        self::assertStringNotContainsString('not installed', $lines[0]);
        self::assertStringNotContainsString('local change', $lines[0]);
    }

    public function testMapsModifiedCheckStatusToInstalledWithLocalChange(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-modified', "base\n", "local edit\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        self::assertStringContainsString('installed with local change', $lines[0]);
    }

    public function testMapsMissingCheckStatusToNotInstalled(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-missing', "base\n", null);
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        self::assertStringContainsString('not installed', $lines[0]);
    }

    #[Group('deprecated-scope')]
    public function testUnknownStatusUsesProbeWhenPresent(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-unknown-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-unknown-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/probe-rule.mdc', "local\n");
        $registryPath = $this->writeRuleRegistry($baseline, 'probe-rule');

        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, null, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        self::assertStringContainsString('installed', $lines[0]);
        self::assertStringNotContainsString('not installed', $lines[0]);
    }

    #[Group('deprecated-scope')]
    public function testUnknownStatusUsesProbeWhenAbsent(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-unknown-miss-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-unknown-miss-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($workspace, 0775, true);
        $registryPath = $this->writeRuleRegistry($baseline, 'probe-miss');

        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, null, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        self::assertStringContainsString('not installed', $lines[0]);
    }

    #[Group('deprecated-scope')]
    public function testOutputFormatMatchesSpec(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('fmt-rule', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));

        // Format: {type}:{name}  {status}   {targets}
        self::assertMatchesRegularExpression('/^rule:fmt-rule  installed   cursor$/', $lines[0]);
    }

    #[Group('deprecated-scope')]
    public function testOutputHasNoScopeLabels(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-scope', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            null,
        ));

        $joined = implode("\n", $lines);
        self::assertStringNotContainsString('(user)', $joined);
        self::assertStringNotContainsString('(project)', $joined);
        self::assertStringNotContainsString('(user+project)', $joined);
    }

    #[Group('deprecated-scope')]
    public function testOutputHasNoDualScopeWarning(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-warn', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            null,
        ));

        $joined = implode("\n", $lines);
        self::assertStringNotContainsString('[warn]', $joined);
        self::assertStringNotContainsString('installed in both user and project', $joined);
    }

    public function testTargetsReadableTextListsRegistryTargets(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-tgt-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-tgt-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.cursor/skills/t', 0775, true);
        mkdir($baseline . '/.kiro/skills/t', 0775, true);
        mkdir($workspace . '/.cursor/skills/t', 0775, true);
        mkdir($workspace . '/.kiro/skills/t', 0775, true);
        foreach (['.cursor/skills/t/SKILL.md', '.kiro/skills/t/SKILL.md'] as $path) {
            file_put_contents($baseline . '/' . $path, "#\n");
            file_put_contents($workspace . '/' . $path, "#\n");
        }
        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
skills:
  - path: t
    description: t
    targets:
      cursor: .cursor/skills/t/
      kiro: .kiro/skills/t/
hooks: []
YAML;
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor', 'kiro'],
            'skill',
        ));

        self::assertStringContainsString('cursor, kiro', $lines[0]);
    }

    public function testRowsExcludePresetNames(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-preset', "base\n", "base\n");
        file_put_contents($registryPath, (string) file_get_contents($registryPath) . "\npresets:\n  - name: gitflow\n    description: x\n    includes:\n      - rule:no-preset\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(['cursor'], null));
        $joined = implode("\n", $lines);
        self::assertStringContainsString('no-preset', $joined);
        self::assertStringNotContainsString('gitflow', $joined);
    }

    public function testLinesDoNotUseUpdateWording(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-upd', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'rule',
        ));
        $joined = strtolower(implode("\n", $lines));
        self::assertStringNotContainsString('up to date', $joined);
        self::assertStringNotContainsString('changed', $joined);
    }

    public function testGitignorePresentWhenWorkspaceIsSymlink(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('symlink() is not available');
        }

        $real = sys_get_temp_dir() . '/apm-show-real-' . bin2hex(random_bytes(4));
        $link = sys_get_temp_dir() . '/apm-show-link-' . bin2hex(random_bytes(4));
        mkdir($real, 0775, true);
        if (!@symlink($real, $link)) {
            self::markTestSkipped('Could not create workspace symlink');
        }

        $baseline = sys_get_temp_dir() . '/apm-show-sym-base-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        file_put_contents($baseline . '/.gitignore', "## @apm:block ability=php target=*\n/node_modules/\n## @apm:end\n");
        file_put_contents($real . '/.gitignore', "# BEGIN apm-managed-gitignore v1\n/node_modules/\n# END apm-managed-gitignore v1\n");
        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
skills: []
hooks: []
gitignore:
  - marker: php
    description: PHP
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);
        $presenter = $this->presenter($baseline . '/abilities.yaml', $baseline);
        $lines = $this->runInWorkspace($link, $baseline, static fn (): array => $presenter->lines(
            ['cursor'],
            'gitignore',
        ));

        self::assertCount(1, $lines);
        self::assertStringContainsString('installed', $lines[0]);
        self::assertStringNotContainsString('not installed', $lines[0]);
    }

    public function testEnumeratesGitignoreAndPrompt(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-mix-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-mix-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.gitignore', "## @apm:block ability=php target=*\n/node_modules/\n## @apm:end\n");
        file_put_contents($workspace . '/.gitignore', "# BEGIN apm-managed-gitignore v1\n/node_modules/\n# END apm-managed-gitignore v1\n");
        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
skills: []
hooks: []
gitignore:
  - marker: php
    description: PHP
prompts:
  - name: my:prompt
    message: Hi.
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);
        $presenter = $this->presenter($baseline . '/abilities.yaml', $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(['cursor'], null));
        $joined = implode("\n", $lines);
        self::assertStringContainsString('gitignore:php', $joined);
        self::assertStringContainsString('prompt:my:prompt', $joined);
    }

    #[Group('deprecated-scope')]
    public function testLinesMethodHasNoScopeFilterParameter(): void
    {
        // Verify the new interface: lines(array $targets, ?string $typeFilter)
        $reflection = new \ReflectionMethod(ShowStatusPresenter::class, 'lines');
        $params = $reflection->getParameters();

        self::assertCount(2, $params);
        self::assertSame('targets', $params[0]->getName());
        self::assertSame('typeFilter', $params[1]->getName());
    }

    private function presenter(string $registryPath, string $packageRoot): ShowStatusPresenter
    {
        $registry = new AbilityRegistry($registryPath);
        $rootResolver = new DeployRootResolver();

        return new ShowStatusPresenter(
            $registry,
            new CheckService(),
            new InstallationProbe($registry, $rootResolver),
            $packageRoot,
            $rootResolver,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function createBaselineWithRule(string $name, string $baselineContent, ?string $workspaceContent): array
    {
        $baseline = sys_get_temp_dir() . '/apm-show-rule-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-rule-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/' . $name . '.mdc', $baselineContent);
        if ($workspaceContent !== null) {
            mkdir($workspace . '/.cursor/rules/git', 0775, true);
            file_put_contents($workspace . '/.cursor/rules/git/' . $name . '.mdc', $workspaceContent);
        } else {
            mkdir($workspace, 0775, true);
        }

        return [$baseline, $workspace, $this->writeRuleRegistry($baseline, $name)];
    }

    private function writeRuleRegistry(string $baseline, string $name): string
    {
        $yaml = <<<YAML
version: "1"
rules:
  - path: {$name}
    description: test rule
    targets:
      cursor: .cursor/rules/git/{$name}.mdc
agents: []
skills: []
hooks: []
YAML;
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        return $registryPath;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function runInWorkspace(string $workspace, ?string $baselineRoot, callable $fn, ?string $home = null): mixed
    {
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv($baselineRoot !== null ? 'APM_BASELINE_ROOT=' . $baselineRoot : 'APM_BASELINE_ROOT');
        if ($home !== null) {
            $this->withEnv('HOME', $home);
        }
        chdir($workspace);
        try {
            return $fn();
        } finally {
            if ($oldBl === false) {
                putenv('APM_BASELINE_ROOT');
            } else {
                putenv('APM_BASELINE_ROOT=' . $oldBl);
            }
        }
    }
}
