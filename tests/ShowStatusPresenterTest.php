<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\ShowStatusPresenter;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class ShowStatusPresenterTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    public function testMapsUnchangedCheckStatusToInstalled(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-ok-rule', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertCount(1, $rows);
        self::assertSame('installed', $rows[0]->status);
        self::assertStringNotContainsString('modified', $rows[0]->formatLine());
        self::assertStringNotContainsString('up to date', strtolower($rows[0]->formatLine()));
    }

    public function testMapsModifiedCheckStatusToInstalledWithLocalChange(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-modified', "base\n", "local edit\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertSame('installed with local change', $rows[0]->status);
    }

    public function testMapsMissingCheckStatusToNotInstalled(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('show-missing', "base\n", null);
        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertSame('not installed', $rows[0]->status);
    }

    public function testUnknownStatusUsesProbeWhenPresent(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-unknown-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-unknown-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/probe-rule.mdc', "local\n");
        $registryPath = $this->writeRuleRegistry($baseline, 'probe-rule');

        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, null, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertSame('installed', $rows[0]->status);
    }

    public function testUnknownStatusUsesProbeWhenAbsent(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-unknown-miss-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-unknown-miss-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline, 0775, true);
        mkdir($workspace, 0775, true);
        $registryPath = $this->writeRuleRegistry($baseline, 'probe-miss');

        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, null, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertSame('not installed', $rows[0]->status);
    }

    public function testDualScopeWarningWhenInstalledInUserAndProject(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-dual-base-' . bin2hex(random_bytes(4));
        $home = sys_get_temp_dir() . '/apm-show-dual-home-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-dual-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($home . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/dual-rule.mdc', "base\n");
        file_put_contents($home . '/.cursor/rules/git/dual-rule.mdc', "base\n");
        file_put_contents($workspace . '/.cursor/rules/git/dual-rule.mdc', "base\n");

        $yaml = <<<'YAML'
version: "1"
rules:
  - path: dual-rule
    description: dual scope rule
    scopes:
      - user
      - project
    targets:
      cursor: .cursor/rules/git/dual-rule.mdc
agents: []
skills: []
hooks: []
YAML;
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(null, ['cursor'], 'rule'), $home);

        self::assertTrue($rows[0]->dualScopeWarning);
        self::assertStringContainsString('[warn] installed in both user and project', $rows[0]->formatLine());
        self::assertStringContainsString('(user+project)', $rows[0]->formatLine());
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
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor', 'kiro'],
            'skill',
        ));

        self::assertSame('cursor, kiro', $rows[0]->targetsText);
    }

    public function testScopeFilterLimitsToUserScope(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-show-fu-base-' . bin2hex(random_bytes(4));
        $home = sys_get_temp_dir() . '/apm-show-fu-home-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-show-fu-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($home . '/.cursor/rules/git', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/f.mdc', "b\n");
        file_put_contents($home . '/.cursor/rules/git/f.mdc', "b\n");
        $yaml = <<<'YAML'
version: "1"
rules:
  - path: f
    description: f
    scopes: [user, project]
    targets:
      cursor: .cursor/rules/git/f.mdc
agents: []
skills: []
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);
        $presenter = $this->presenter($baseline . '/abilities.yaml', $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(DeployScope::User, ['cursor'], 'rule'), $home);

        self::assertSame('installed', $rows[0]->status);
        self::assertSame('(user)', $rows[0]->scopeLabel);
        self::assertFalse($rows[0]->dualScopeWarning);
    }

    public function testScopeFilterLimitsToProjectScope(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('proj-only', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));

        self::assertSame('(project)', $rows[0]->scopeLabel);
    }

    public function testRowsExcludePresetNames(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-preset', "base\n", "base\n");
        file_put_contents($registryPath, (string) file_get_contents($registryPath) . "\npresets:\n  - name: gitflow\n    description: x\n    includes:\n      - rule:no-preset\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(null, ['cursor'], null));
        $names = array_map(static fn ($r) => $r->name, $rows);
        self::assertContains('no-preset', $names);
        self::assertNotContains('gitflow', $names);
    }

    public function testLinesDoNotUseUpdateWording(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('no-upd', "base\n", "base\n");
        $presenter = $this->presenter($registryPath, $baseline);
        $lines = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->lines(
            DeployScope::Project,
            ['cursor'],
            'rule',
        ));
        $joined = strtolower(implode("\n", $lines));
        self::assertStringNotContainsString('up to date', $joined);
        self::assertStringNotContainsString('changed', $joined);
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
        $rows = $this->runInWorkspace($workspace, $baseline, static fn (): array => $presenter->rows(DeployScope::Project, ['cursor'], null));
        $keys = array_map(static fn ($r) => $r->type . ':' . $r->name, $rows);
        self::assertContains('gitignore:php', $keys);
        self::assertContains('prompt:my:prompt', $keys);
    }

    private function presenter(string $registryPath, string $packageRoot): ShowStatusPresenter
    {
        $registry = new AbilityRegistry($registryPath);

        return new ShowStatusPresenter(
            $registry,
            new CheckService(),
            new InstallationProbe($registry, new DeployRootResolver()),
            $packageRoot,
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
