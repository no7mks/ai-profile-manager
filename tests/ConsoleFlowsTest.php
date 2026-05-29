<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\UpdateCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RestoresCwdTrait;

final class ConsoleFlowsTest extends TestCase
{
    use RestoresCwdTrait;

    /**
     * Helper: write a minimal abilities.yaml and return an AbilityRegistry for it.
     *
     * @param array{skills?: list<array{path: string, description?: string}>, rules?: list<array{path: string, description?: string}>, agents?: list<array{path: string, description?: string}>, hooks?: list<array{path: string, description?: string}>} $entries
     * @param list<array{name: string, includes: list<string>, description?: string}> $presets
     */
    private static function createRegistry(string $dir, array $entries = [], array $presets = []): AbilityRegistry
    {
        $lines = ['version: "1"'];
        foreach (['skills', 'rules', 'agents', 'hooks'] as $section) {
            if (!isset($entries[$section]) || $entries[$section] === []) {
                continue;
            }
            $lines[] = "{$section}:";
            foreach ($entries[$section] as $entry) {
                $path = $entry['path'];
                $desc = $entry['description'] ?? $path;
                $lines[] = "  - path: {$path}";
                $lines[] = "    description: {$desc}";
                $lines[] = "    targets:";
                $lines[] = "      cursor: .cursor/{$section}/{$path}";
                $lines[] = "      kiro: .kiro/{$section}/{$path}";
            }
        }
        if ($presets !== []) {
            $lines[] = 'presets:';
            foreach ($presets as $preset) {
                $lines[] = "  - name: {$preset['name']}";
                $lines[] = "    description: " . ($preset['description'] ?? $preset['name']);
                $lines[] = "    includes:";
                foreach ($preset['includes'] as $include) {
                    $lines[] = "      - {$include}";
                }
            }
        }
        $yamlPath = $dir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", $lines) . "\n");

        return new AbilityRegistry($yamlPath);
    }

    public function testInstallCommandInstallsKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-i-' . bin2hex(random_bytes(4));
        $pkg = sys_get_temp_dir() . '/apm-flow-i-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($pkg, 0775, true);

        // .gitignore template at package root
        file_put_contents($pkg . '/.gitignore', implode("\n", [
            '## @apm:block ability=installable-preset target=*',
            '/.apm/gitflow/',
            '## @apm:end',
        ]));

        // Create skill source fixture at Source-is-Target path
        mkdir($pkg . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/graphify/SKILL.md', "# Graphify\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($pkg, [
            'skills' => [['path' => 'graphify']],
        ], [
            ['name' => 'installable-preset', 'includes' => ['skill:graphify']],
        ]);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new InstallCommand(new Installer(registry: $registry, packageRoot: $pkg), presetRegistry: $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'installable-preset', '--target' => ['cursor']]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Preset: installable-preset', $tester->getDisplay());
        self::assertFileExists($tmp . '/.gitignore');
        self::assertStringContainsString('/.apm/gitflow/', (string) file_get_contents($tmp . '/.gitignore'));
    }

    public function testInstallCommandUnknownPresetFails(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-i2-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($tmp);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new InstallCommand(new Installer(registry: $registry, packageRoot: $tmp), presetRegistry: $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'no-such-preset']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testInstallCommandUnknownTargetsFails(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-itgt-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($tmp);
        $cmd = new InstallCommand(new Installer(registry: $registry, packageRoot: $tmp));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['not-a-target']]);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testInstallCommandWithoutPresetRunsBootstrap(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-bootstrap-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        // Create a fake package root with scaffold files and abilities for the bootstrap flow
        $pkg = sys_get_temp_dir() . '/apm-flow-bootstrap-pkg-' . bin2hex(random_bytes(4));
        // Scaffold files (at package root level)
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        // Scope rules (at package root level)
        mkdir($pkg . '/.cursor/rules', 0775, true);
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/cursor-scope.mdc', "cursor-scope\n");
        file_put_contents($pkg . '/.kiro/steering/kiro-scope.md', "kiro-scope\n");
        // Abilities for Installer at Source-is-Target paths
        mkdir($pkg . '/.cursor/skills/apm', 0775, true);
        mkdir($pkg . '/.kiro/skills/apm', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/apm/SKILL.md', "# APM\n");
        file_put_contents($pkg . '/.kiro/skills/apm/SKILL.md', "# APM\n");
        // Agents: createRegistry generates target as .cursor/agents/code-reviewer (no extension)
        mkdir($pkg . '/.cursor/agents', 0775, true);
        mkdir($pkg . '/.kiro/agents', 0775, true);
        file_put_contents($pkg . '/.cursor/agents/code-reviewer', "# Code Reviewer\n");
        file_put_contents($pkg . '/.kiro/agents/code-reviewer', "# Code Reviewer\n");

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($pkg, [
            'skills' => [['path' => 'apm']],
            'agents' => [['path' => 'code-reviewer']],
        ]);
        $initializer = new \AiProfileManager\Service\ProjectInitializer($pkg);
        $cmd = new InstallCommand(new Installer(registry: $registry, packageRoot: $pkg), $initializer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        // Scaffold files copied
        self::assertFileExists($tmp . '/docs/README.md');
        self::assertFileExists($tmp . '/issues/README.md');
        self::assertFileExists($tmp . '/AGENTS.md');
        // Scope rules installed
        self::assertFileExists($tmp . '/.cursor/rules/cursor-scope.mdc');
        self::assertFileExists($tmp . '/.kiro/steering/kiro-scope.md');
        // Default skills/agents installed
        self::assertFileExists($tmp . '/.cursor/skills/apm/SKILL.md');
        self::assertFileExists($tmp . '/.kiro/skills/apm/SKILL.md');
        self::assertFileExists($tmp . '/.cursor/agents/code-reviewer');
        self::assertFileExists($tmp . '/.kiro/agents/code-reviewer');
        self::assertStringContainsString("/apm init", $tester->getDisplay());
    }

    public function testCheckCommandRunsForKnownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-ch-' . bin2hex(random_bytes(4));
        $baseline = sys_get_temp_dir() . '/apm-flow-ch-base-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($baseline . '/abilities/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo-skill/SKILL.md', "x\n");
        file_put_contents($tmp . '/.cursor/skills/demo-skill/SKILL.md', "x\n");

        // AbilityDiffService needs abilities.yaml at baseline
        $baselineYaml = implode("\n", [
            'version: "1"',
            'skills:',
            '  - path: demo-skill',
            '    description: demo-skill',
            '    targets:',
            '      cursor: .cursor/skills/demo-skill',
        ]);
        file_put_contents($baseline . '/abilities.yaml', $baselineYaml . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($tmp, [
            'skills' => [['path' => 'demo-skill']],
        ], [
            ['name' => 'known-preset', 'includes' => ['skill:demo-skill']],
        ]);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new CheckCommand(new CheckService(), $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'known-preset', '--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(0, $exit);
        self::assertStringContainsString('Preset: known-preset', $tester->getDisplay());
    }

    public function testSkillInstallUsesDefaultSkillsWhenArgumentEmpty(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-skill-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        // Create skill source fixture at Source-is-Target path
        $pkg = sys_get_temp_dir() . '/apm-flow-skill-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/graphify/SKILL.md', "# Graphify\n");

        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $registry = self::createRegistry($pkg, [
                'skills' => [['path' => 'graphify']],
            ]);
            $cmd = new SkillInstallCommand(new Installer(registry: $registry, packageRoot: $pkg));
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['skills' => [], '--target' => ['cursor']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('graphify', $tester->getDisplay());
    }

    public function testRuleInstallInstallsNamedRule(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-rule-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        // Create rule source fixture at Source-is-Target path
        $pkg = sys_get_temp_dir() . '/apm-flow-rule-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/.kiro/rules', 0775, true);
        file_put_contents($pkg . '/.kiro/rules/spec-goal', "# Spec Goal\n");

        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $registry = self::createRegistry($pkg, [
                'rules' => [['path' => 'spec-goal']],
            ]);
            $cmd = new RuleInstallCommand(new Installer(registry: $registry, packageRoot: $pkg));
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['rules' => ['spec-goal'], '--target' => ['kiro']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('spec-goal', $tester->getDisplay());
    }

    public function testAgentInstallInstallsNamedAgent(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-flow-agent-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        // Create agent source fixture at Source-is-Target path
        $pkg = sys_get_temp_dir() . '/apm-flow-agent-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/.cursor/agents', 0775, true);
        file_put_contents($pkg . '/.cursor/agents/code-reviewer', "# Code Reviewer\n");

        $old = getcwd();
        self::assertNotFalse($old);
        try {
            chdir($tmp);

            $registry = self::createRegistry($pkg, [
                'agents' => [['path' => 'code-reviewer']],
            ]);
            $cmd = new AgentInstallCommand(new Installer(registry: $registry, packageRoot: $pkg));
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['agents' => ['code-reviewer'], '--target' => ['cursor']]);
        } finally {
            chdir($old);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('code-reviewer', $tester->getDisplay());
    }

    public function testUpdateCommandWritesKnowledgeBase(): void
    {
        $home = sys_get_temp_dir() . '/apm-flow-up-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);

        $oldHome = getenv('HOME');
        try {
            putenv('HOME=' . $home);

            $cmd = new UpdateCommand(new KnowledgeBaseUpdater());
            $tester = new CommandTester($cmd);
            $exit = $tester->execute([]);
        } finally {
            if ($oldHome === false) {
                putenv('HOME');
            } else {
                putenv('HOME=' . $oldHome);
            }
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileExists($home . '/.config/apm/knowledge-base.json');
    }

    public function testShowCommandListsInstallableItemsAndPresetMapping(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-' . bin2hex(random_bytes(4));
        $baseline = sys_get_temp_dir() . '/apm-show-base-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($tmp . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($tmp . '/.cursor/skills/graphify/SKILL.md', "x\n");

        // Baseline files at Source-is-Target paths
        mkdir($baseline . '/.cursor/skills/graphify', 0775, true);
        mkdir($baseline . '/.cursor/skills/gitflow', 0775, true);
        mkdir($baseline . '/.cursor/rules', 0775, true);
        mkdir($baseline . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/graphify/SKILL.md', "x\n");
        file_put_contents($baseline . '/.cursor/skills/gitflow/SKILL.md', "x\n");
        file_put_contents($baseline . '/.cursor/rules/spec-goal', "x\n");
        file_put_contents($baseline . '/.cursor/agents/code-reviewer', "x\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($baseline, [
            'skills' => [['path' => 'graphify'], ['path' => 'gitflow']],
            'rules' => [['path' => 'spec-goal']],
            'agents' => [['path' => 'code-reviewer']],
        ], [
            ['name' => 'demo', 'includes' => ['skill:graphify', 'rule:spec-goal', 'agent:code-reviewer']],
        ]);
        $presetRegistry = new PresetRegistry($registry);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $baseline), new CheckService(), $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Skills', $display);
        self::assertStringContainsString('[installed] graphify', $display);
        self::assertStringContainsString('presets: demo', $display);
        self::assertStringContainsString('Rules', $display);
        self::assertStringContainsString('spec-goal', $display);
        self::assertStringContainsString('Agents', $display);
        self::assertStringContainsString('code-reviewer', $display);
    }

    public function testShowCommandUnknownTargetFails(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-unk-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $registry = self::createRegistry($tmp);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $tmp), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['not-a-target']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testShowCommandHighlightsInstalledEvenWhenBaselineUnknown(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-unknown-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-unknown-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($tmp . '/.cursor/skills/graphify/SKILL.md', "x\n");
        mkdir($packageRoot, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        $oldComposerHome = getenv('COMPOSER_HOME');
        putenv('APM_BASELINE_ROOT=' . $tmp . '/missing-baseline-root');
        putenv('COMPOSER_HOME=' . $tmp . '/missing-composer-home');
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($packageRoot, [
            'skills' => [['path' => 'graphify']],
        ]);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldComposerHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldComposerHome);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[installed] graphify', $tester->getDisplay());
    }

    public function testShowCommandMarksInstalledWhenAnyTargetIsInstalled(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-any-target-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-any-target-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/.cursor/skills/graphify', 0775, true);
        file_put_contents($tmp . '/.cursor/skills/graphify/SKILL.md', "x\n");
        mkdir($packageRoot, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        $oldComposerHome = getenv('COMPOSER_HOME');
        putenv('APM_BASELINE_ROOT=' . $tmp . '/missing-baseline-root');
        putenv('COMPOSER_HOME=' . $tmp . '/missing-composer-home');
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($packageRoot, [
            'skills' => [['path' => 'graphify']],
        ]);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor', 'kiro']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldComposerHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldComposerHome);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Targets: cursor, kiro', $tester->getDisplay());
        self::assertStringContainsString('[installed] graphify', $tester->getDisplay());
    }

    public function testShowCommandRendersNoneForEmptyAbilities(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-empty-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-empty-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        $oldComposerHome = getenv('COMPOSER_HOME');
        putenv('APM_BASELINE_ROOT=' . $tmp . '/missing-baseline-root');
        putenv('COMPOSER_HOME=' . $tmp . '/missing-composer-home');
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($packageRoot);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldComposerHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldComposerHome);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Skills', $display);
        self::assertStringContainsString('Agents', $display);
        self::assertStringContainsString('Rules', $display);
        self::assertStringContainsString('  (none)', $display);
    }

    /**
     * AC 1: show 命令输出中以独立的 "Hooks" 分区列出所有 hook 类型 ability
     */
    public function testShowCommandDisplaysHooksSection(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-hooks-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-hooks-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/check-write-length.kiro.hook', "hook content\n");

        // Create abilities.yaml with hooks section
        file_put_contents($packageRoot . '/abilities.yaml', implode("\n", [
            'hooks:',
            '  - path: check-write-length',
            '    description: Check write length hook',
            '    targets:',
            '      cursor: .cursor/hooks/check-write-length',
            '      kiro: .kiro/hooks/check-write-length.kiro.hook',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $packageRoot);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($packageRoot . '/abilities.yaml');
        $installer = new Installer(registry: $registry, packageRoot: $packageRoot);
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('check-write-length', $display);
    }

    /**
     * AC 2: --type hook 仅输出 hook 分区而省略其他类型分区
     */
    public function testShowCommandTypeFilterHookOnlyShowsHooks(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-type-hook-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-type-hook-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/check-write-length.kiro.hook', "hook content\n");

        file_put_contents($packageRoot . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: graphify',
            '    description: Graphify skill',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            '      kiro: .kiro/skills/graphify',
            'hooks:',
            '  - path: check-write-length',
            '    description: Check write length hook',
            '    targets:',
            '      cursor: .cursor/hooks/check-write-length',
            '      kiro: .kiro/hooks/check-write-length.kiro.hook',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $packageRoot);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($packageRoot . '/abilities.yaml');
        $installer = new Installer(registry: $registry, packageRoot: $packageRoot);
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'hook']);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('check-write-length', $display);
        // Other sections should NOT appear
        self::assertStringNotContainsString('Skills', $display);
        self::assertStringNotContainsString('Agents', $display);
        self::assertStringNotContainsString('Rules', $display);
    }

    /**
     * AC 3: 未知类型过滤值返回错误并列出已知类型
     */
    public function testShowCommandTypeFilterUnknownTypeReturnsError(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-type-unk-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $registry = self::createRegistry($tmp);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $tmp), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'banana']);

        self::assertSame(Command::FAILURE, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Unknown type', $display);
        self::assertStringContainsString('rule', $display);
        self::assertStringContainsString('agent', $display);
        self::assertStringContainsString('skill', $display);
        self::assertStringContainsString('hook', $display);
        self::assertStringContainsString('gitignore', $display);
        self::assertStringContainsString('preset', $display);
    }

    /**
     * AC 4: 无 hook 时显示空状态占位文本
     */
    public function testShowCommandDisplaysNoneWhenNoHooks(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-no-hooks-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-no-hooks-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        $oldComposerHome = getenv('COMPOSER_HOME');
        putenv('APM_BASELINE_ROOT=' . $tmp . '/missing-baseline-root');
        putenv('COMPOSER_HOME=' . $tmp . '/missing-composer-home');
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = self::createRegistry($packageRoot);
        $installer = new Installer(registry: $registry, packageRoot: $packageRoot);
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'hook']);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldComposerHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldComposerHome);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('(none)', $display);
    }

    /**
     * AC 5: 未指定类型过滤时，输出中同时包含 hook 分区与其他所有类型分区
     */
    public function testShowCommandWithoutTypeFilterShowsAllSections(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-all-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-all-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/check-write-length.kiro.hook', "hook content\n");

        file_put_contents($packageRoot . '/abilities.yaml', implode("\n", [
            'hooks:',
            '  - path: check-write-length',
            '    description: Check write length hook',
            '    targets:',
            '      cursor: .cursor/hooks/check-write-length',
            '      kiro: .kiro/hooks/check-write-length.kiro.hook',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $packageRoot);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($packageRoot . '/abilities.yaml');
        $installer = new Installer(registry: $registry, packageRoot: $packageRoot);
        $cmd = new ShowCommand($installer, new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        // All sections should be present
        self::assertStringContainsString('Skills', $display);
        self::assertStringContainsString('Agents', $display);
        self::assertStringContainsString('Rules', $display);
        self::assertStringContainsString('Hooks', $display);
    }

    public function testPresetCreateFailsWhenPresetAlreadyExists(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pc-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetCreateCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['name' => 'gitflow']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testPresetAddAbilityRequiresExactlyOneKindFlag(): void
    {
        $cmd = new PresetAddAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', 'ability' => 'x']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('exactly one', $tester->getDisplay());
    }

    public function testPresetDeleteFailsForUnknownPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pdel-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetDeleteCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['name' => 'nonexistent-preset']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testShowCommandDisplaysPresetMappingForHooks(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-hook-preset-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-hook-preset-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/check-write-length.kiro.hook', "hook content\n");

        file_put_contents($packageRoot . '/abilities.yaml', implode("\n", [
            'hooks:',
            '  - path: check-write-length',
            '    description: Check write length hook',
            '    targets:',
            '      cursor: .cursor/hooks/check-write-length',
            '      kiro: .kiro/hooks/check-write-length.kiro.hook',
            'presets:',
            '  - name: dev-hooks',
            '    description: Dev hooks preset',
            '    includes:',
            '      - hook:check-write-length',
        ]) . "\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $packageRoot);
        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($packageRoot . '/abilities.yaml');
        $presetRegistry = new PresetRegistry($registry);
        $installer = new Installer(registry: $registry, packageRoot: $packageRoot);
        $cmd = new ShowCommand($installer, new CheckService(), $presetRegistry);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor'], '--type' => 'hook']);

        chdir($old);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('check-write-length', $display);
        self::assertStringContainsString('dev-hooks', $display);
    }

}
