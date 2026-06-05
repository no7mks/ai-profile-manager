<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\ShowStatusPresenter;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowCommandTest extends TestCase
{
    use RemovesDirTrait;
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-show-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testShowCommandRejectsUnknownTarget(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $tester = new CommandTester($this->command($proj));
        $exit = $tester->execute(['--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testShowCommandRejectsUnknownType(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $tester = new CommandTester($this->command($proj));
        $exit = $tester->execute(['--type' => 'invalid-type']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown type', $tester->getDisplay());
        self::assertStringContainsString('prompt', $tester->getDisplay());
        self::assertStringNotContainsString('preset', $tester->getDisplay());
    }

    #[Group('deprecated-scope')]
    public function testShowCommandRejectsInvalidScope(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $tester = new CommandTester($this->command($proj));
        $exit = $tester->execute(['--scope' => 'bogus']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Invalid deploy scope', $tester->getDisplay());
    }

    public function testShowCommandFiltersTypeWithPresenterLineFormat(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo',
            '    description: Demo skill',
            '    targets:',
            '      cursor: .cursor/skills/demo',
            'rules: []',
            'agents: []',
            'hooks: []',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        $this->withEnv('APM_BASELINE_ROOT', $pkg);
        chdir($proj);

        $tester = new CommandTester($this->command($pkg, $pkg . '/abilities.yaml'));
        $exit = $tester->execute(['--type' => 'skill', '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('skill:demo', $display);
        self::assertStringContainsString('not installed', $display);
        self::assertStringNotContainsString('Skills', $display);
        self::assertStringNotContainsString('presets:', $display);
        self::assertStringNotContainsString('[installed]', $display);
    }

    public function testShowCommandScopeProjectFilter(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('scope-proj', "base\n", "base\n");
        $this->withEnv('APM_BASELINE_ROOT', $baseline);
        chdir($workspace);

        $tester = new CommandTester($this->command($baseline, $registryPath));
        $exit = $tester->execute(['--scope' => 'project', '--target' => ['cursor'], '--type' => 'rule']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('rule:scope-proj  installed (project)', $tester->getDisplay());
    }

    #[Group('deprecated-scope')]
    public function testShowCommandScopeUserFilter(): void
    {
        $baseline = $this->tmpDir . '/user-scope-base';
        $home = $this->tmpDir . '/user-scope-home';
        $workspace = $this->tmpDir . '/user-scope-ws';
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($home . '/.cursor/rules/git', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/u.mdc', "b\n");
        file_put_contents($home . '/.cursor/rules/git/u.mdc', "b\n");
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, <<<'YAML'
version: "1"
rules:
  - path: u
    description: u
    scopes: [user, project]
    targets:
      cursor: .cursor/rules/git/u.mdc
agents: []
skills: []
hooks: []
YAML);

        $this->withEnv('APM_BASELINE_ROOT', $baseline);
        $this->withEnv('HOME', $home);
        chdir($workspace);

        $tester = new CommandTester($this->command($baseline, $registryPath));
        $exit = $tester->execute(['--scope' => 'user', '--target' => ['cursor'], '--type' => 'rule']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('rule:u  installed (user)', $tester->getDisplay());
    }

    public function testShowCommandMergedViewWithoutScope(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('merged-one', "base\n", "base\n");
        $this->withEnv('APM_BASELINE_ROOT', $baseline);
        chdir($workspace);

        $tester = new CommandTester($this->command($baseline, $registryPath));
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'rule']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('rule:merged-one  installed (project)', $tester->getDisplay());
        self::assertStringNotContainsString('(user+project)', $tester->getDisplay());
    }

    #[Group('deprecated-scope')]
    public function testShowCommandInstalledWhenBaselineUnknownButFileOnDisk(): void
    {
        $baseline = $this->tmpDir . '/unknown-base';
        $workspace = $this->tmpDir . '/unknown-ws';
        mkdir($baseline, 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/probe.mdc', "local\n");
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, <<<'YAML'
version: "1"
rules:
  - path: probe
    description: probe
    targets:
      cursor: .cursor/rules/git/probe.mdc
agents: []
skills: []
hooks: []
YAML);

        $this->withEnv('APM_BASELINE_ROOT', '');
        chdir($workspace);

        $tester = new CommandTester($this->command($baseline, $registryPath));
        $exit = $tester->execute(['--scope' => 'project', '--target' => ['cursor'], '--type' => 'rule']);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('rule:probe  installed (project)', $display);
        self::assertStringNotContainsString('not installed', $display);
    }

    public function testShowCommandExcludesPresetNamesFromOutput(): void
    {
        [$baseline, $workspace, $registryPath] = $this->createBaselineWithRule('ability-only', "base\n", "base\n");
        file_put_contents(
            $registryPath,
            (string) file_get_contents($registryPath) . "\npresets:\n  - name: gitflow\n    description: x\n    includes:\n      - rule:ability-only\n",
        );
        $this->withEnv('APM_BASELINE_ROOT', $baseline);
        chdir($workspace);

        $tester = new CommandTester($this->command($baseline, $registryPath));
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'rule']);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('rule:ability-only', $display);
        self::assertStringNotContainsString('gitflow', $display);
        self::assertStringNotContainsString('presets:', $display);
    }

    public function testShowCommandCreateFactory(): void
    {
        $pkg = $this->tmpDir . '/factory-pkg';
        mkdir($pkg, 0775, true);
        file_put_contents($pkg . '/abilities.yaml', "skills: []\nrules: []\nagents: []\nhooks: []\n");
        $installer = new Installer(
            registry: new AbilityRegistry($pkg . '/abilities.yaml'),
            packageRoot: $pkg,
        );

        $cmd = ShowCommand::create($installer, new CheckService());
        $tester = new CommandTester($cmd);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function createBaselineWithRule(string $name, string $baselineContent, ?string $workspaceContent): array
    {
        $baseline = $this->tmpDir . '/rule-base-' . $name;
        $workspace = $this->tmpDir . '/rule-ws-' . $name;
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/' . $name . '.mdc', $baselineContent);
        if ($workspaceContent !== null) {
            mkdir($workspace . '/.cursor/rules/git', 0775, true);
            file_put_contents($workspace . '/.cursor/rules/git/' . $name . '.mdc', $workspaceContent);
        } else {
            mkdir($workspace, 0775, true);
        }
        $registryPath = $baseline . '/abilities.yaml';
        file_put_contents($registryPath, <<<YAML
version: "1"
rules:
  - path: {$name}
    description: test rule
    targets:
      cursor: .cursor/rules/git/{$name}.mdc
agents: []
skills: []
hooks: []
YAML);

        return [$baseline, $workspace, $registryPath];
    }

    private function command(string $packageRoot, ?string $registryPath = null): ShowCommand
    {
        $path = $registryPath ?? $packageRoot . '/abilities.yaml';
        if (!is_file($path)) {
            file_put_contents($path, "skills: []\nrules: []\nagents: []\nhooks: []\n");
        }
        $registry = new AbilityRegistry($path);
        $resolver = new DeployRootResolver();
        $presenter = new ShowStatusPresenter(
            $registry,
            new CheckService(),
            new InstallationProbe($registry, $resolver),
            $packageRoot,
        );

        return new ShowCommand($presenter, $resolver);
    }
}
