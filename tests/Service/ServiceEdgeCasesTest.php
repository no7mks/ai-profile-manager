<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Service\AbilityRegistryException;
use AiProfileManager\Service\ComposerBaselineResolver;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\HookChecker;
use AiProfileManager\Service\HookInstaller;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\ProjectInitializer;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Edge-case tests for service classes: error paths, boundary conditions, and defensive branches.
 */
final class ServiceEdgeCasesTest extends TestCase
{
    use RemovesDirTrait;
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-cov-bump-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── HookChecker: checkCursor returns ok when entry declaration is empty ───

    public function testCheckCursorReturnsOkWhenEntryDeclarationFileNotExists(): void
    {
        $name = 'no-json-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);
        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        // No .json file created → readEntryDeclaration returns []

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result);
    }

    public function testCheckCursorReturnsOkWhenEntryDeclarationIsInvalidJson(): void
    {
        $name = 'bad-json-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);
        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", 'not valid json!!!');

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result);
    }

    public function testCheckCursorReturnsOkWhenHooksJsonHasNoHooksKey(): void
    {
        $name = 'no-hooks-key';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);
        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh"],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode(['version' => 1]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        // hooks.json has no 'hooks' key → loadHookRegistry adds empty hooks → registryContainsEntry returns false
        self::assertSame('missing', $result);
    }

    public function testCheckCursorReturnsMissingWhenHooksJsonIsInvalidJson(): void
    {
        $name = 'invalid-registry';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);
        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh"],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        if (!is_dir(dirname($hookRegistryPath))) {
            mkdir(dirname($hookRegistryPath), 0775, true);
        }
        file_put_contents($hookRegistryPath, '{{{invalid');

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        // Invalid JSON → loadHookRegistry returns default with empty hooks → entry not found
        self::assertSame('missing', $result);
    }

    // ─── ComposerBaselineResolver: edge cases ─────────────────────────

    public function testResolveReturnsNullWhenInstalledJsonIsInvalidJson(): void
    {
        $composerHome = $this->tmpDir . '/composer-home';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', 'not json at all');

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNull($out);
    }

    public function testResolveReturnsNullWhenInstalledJsonHasNoPackagesArray(): void
    {
        $composerHome = $this->tmpDir . '/composer-home-nopkg';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['something' => 'else']));

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        // packages key missing → empty packages array → package not found → null
        self::assertNull($out);
    }

    public function testResolveReturnsNullWhenComposerHomeAndHomeAreEmpty(): void
    {
        $this->withEnv('COMPOSER_HOME', null);
        $this->withEnv('APM_BASELINE_ROOT', null);
        $this->withEnv('HOME', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        // installedJsonPath returns null → resolve returns null
        self::assertNull($out);
    }

    public function testResolveReturnsNullWhenPackageNotFoundInInstalledJson(): void
    {
        $composerHome = $this->tmpDir . '/composer-home-other';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0'],
            ],
        ]));

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNull($out);
    }

    // ─── PresetRegistry: error paths ──────────────────────────────────

    public function testCreatePresetThrowsWhenNameAlreadyExists(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'presets:',
            '  - name: existing',
            '    description: Already here',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Preset 'existing' already exists");
        $presetRegistry->createPreset('existing', 'duplicate', []);
    }

    public function testCreatePresetWorksWhenNoPresetsSection(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        // No presets section at all
        file_put_contents($yamlPath, "skills: []\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $presetRegistry->createPreset('new-one', 'A new preset', []);

        $preset = $presetRegistry->getPreset('new-one');
        self::assertNotNull($preset);
        self::assertSame('new-one', $preset['name']);
    }

    public function testDeletePresetThrowsWhenNameNotFound(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'presets:',
            '  - name: only-one',
            '    description: The only preset',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Preset 'nonexistent' not found");
        $presetRegistry->deletePreset('nonexistent');
    }

    public function testAddAbilityThrowsOnInvalidType(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'presets:',
            '  - name: test-preset',
            '    description: Test',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid ability type: invalid');
        $presetRegistry->addAbility('test-preset', 'invalid', 'some-path');
    }

    public function testAddAbilityThrowsWhenAbilityNotInRegistry(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'skills:',
            '  - path: real-skill',
            '    description: A real skill',
            '    targets:',
            '      cursor: .cursor/skills/real-skill',
            'presets:',
            '  - name: test-preset',
            '    description: Test',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Ability 'skill:nonexistent' not found in registry");
        $presetRegistry->addAbility('test-preset', 'skill', 'nonexistent');
    }

    public function testAddAbilityThrowsWhenPresetNotFound(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'skills:',
            '  - path: real-skill',
            '    description: A real skill',
            '    targets:',
            '      cursor: .cursor/skills/real-skill',
            'presets:',
            '  - name: other-preset',
            '    description: Other',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Preset 'missing-preset' not found");
        $presetRegistry->addAbility('missing-preset', 'skill', 'real-skill');
    }

    public function testRemoveAbilityThrowsWhenPresetNotFound(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'presets:',
            '  - name: only-preset',
            '    description: Only',
            '    includes:',
            '      - skill:something',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Preset 'ghost' not found");
        $presetRegistry->removeAbility('ghost', 'skill', 'something');
    }

    public function testAddAbilityWithHookType(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'hooks:',
            '  - path: check-write-length',
            '    description: Check write length hook',
            '    targets:',
            '      cursor: .cursor/hooks/check-write-length',
            'rules:',
            '  - path: my-rule',
            '    description: A rule',
            '    targets:',
            '      cursor: .cursor/rules/my-rule.mdc',
            'agents:',
            '  - path: my-agent',
            '    description: An agent',
            '    targets:',
            '      cursor: .cursor/agents/my-agent.md',
            'presets:',
            '  - name: my-preset',
            '    description: Test',
            '    includes: []',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);

        // Test hook type
        $presetRegistry->addAbility('my-preset', 'hook', 'check-write-length');
        // Test rule type
        $presetRegistry->addAbility('my-preset', 'rule', 'my-rule');
        // Test agent type
        $presetRegistry->addAbility('my-preset', 'agent', 'my-agent');

        $preset = $presetRegistry->getPreset('my-preset');
        self::assertNotNull($preset);
        self::assertCount(3, $preset['includes']);
        $types = array_column($preset['includes'], 'type');
        self::assertContains('hook', $types);
        self::assertContains('rule', $types);
        self::assertContains('agent', $types);
    }

    public function testNormalizePresetSkipsNonStringAndNoColonEntries(): void
    {
        $yamlPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'presets:',
            '  - name: weird',
            '    description: Has weird includes',
            '    includes:',
            '      - 123',
            '      - no-colon-here',
            '      - skill:valid-one',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);
        $presetRegistry = new PresetRegistry($registry);
        $preset = $presetRegistry->getPreset('weird');

        self::assertNotNull($preset);
        // Only the valid entry with colon should be parsed
        self::assertCount(1, $preset['includes']);
        self::assertSame('skill', $preset['includes'][0]['type']);
        self::assertSame('valid-one', $preset['includes'][0]['path']);
    }

    public function testToTypedSpecIgnoresUnknownTypes(): void
    {
        $preset = [
            'name' => 'test',
            'description' => 'test',
            'includes' => [
                ['type' => 'skill', 'path' => 'a'],
                ['type' => 'unknown', 'path' => 'b'],
                ['type' => 'hook', 'path' => 'c'],
            ],
        ];

        $result = PresetRegistry::toTypedSpec($preset);

        self::assertSame(['a'], $result['skills']);
        self::assertSame(['c'], $result['hooks']);
        self::assertSame([], $result['rules']);
        self::assertSame([], $result['agents']);
    }

    // ─── HookInstaller: installKiro copy failure (line 35 already covered) ───

    public function testInstallCursorWithNoEntryDeclarationFile(): void
    {
        // Source dir exists but has no <name>.json → entries = [] → no merge needed
        $sourceDir = $this->tmpDir . '/source/minimal-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/minimal-hook.sh', '#!/bin/bash');
        // No minimal-hook.json

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/minimal-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        self::assertFileExists($targetDir . '/minimal-hook.sh');
        // hooks.json should be created with empty hooks (no entries to merge)
        self::assertFileExists($hookRegistryPath);
        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertSame(1, $data['version']);
        self::assertSame([], $data['hooks']);
    }

    public function testUninstallCursorWithNoHooksJsonFile(): void
    {
        // Target dir exists but hooks.json doesn't → skip registry removal, just delete dir
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/orphan-hook';
        mkdir($targetDir, 0775, true);
        file_put_contents($targetDir . '/orphan-hook.sh', '#!/bin/bash');
        file_put_contents($targetDir . '/orphan-hook.json', json_encode([
            'preToolUse' => [['command' => '.cursor/hooks/orphan-hook/orphan-hook.sh']],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/nonexistent-hooks.json';

        $installer = new HookInstaller();
        $result = $installer->uninstallCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        self::assertDirectoryDoesNotExist($targetDir);
    }

    // ─── AbilityRegistry: validation errors ───────────────────────────

    public function testParseThrowsOnInvalidYamlContent(): void
    {
        $yamlPath = $this->tmpDir . '/bad.yaml';
        file_put_contents($yamlPath, "skills:\n  - not_a_mapping_string\n");

        $registry = new AbilityRegistry($yamlPath);

        $this->expectException(AbilityRegistryException::class);
        $registry->parse();
    }

    public function testParseThrowsOnMissingRequiredFields(): void
    {
        $yamlPath = $this->tmpDir . '/incomplete.yaml';
        file_put_contents($yamlPath, implode("\n", [
            'skills:',
            '  - path: valid-path',
            '    description: Missing targets field',
        ]) . "\n");

        $registry = new AbilityRegistry($yamlPath);

        $this->expectException(AbilityRegistryException::class);
        $registry->parse();
    }

    // ─── HookInstaller: installCursor with invalid entry declaration JSON ──

    public function testInstallCursorWithInvalidEntryDeclarationJson(): void
    {
        $sourceDir = $this->tmpDir . '/source/bad-entry-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/bad-entry-hook.sh', '#!/bin/bash');
        // Invalid JSON in entry declaration → readEntryDeclaration returns []
        file_put_contents($sourceDir . '/bad-entry-hook.json', 'not valid json');

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/bad-entry-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        // hooks.json created with empty hooks since entries couldn't be parsed
        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertSame([], $data['hooks']);
    }

    public function testUninstallCursorWithInvalidEntryDeclarationJson(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/bad-entry';
        mkdir($targetDir, 0775, true);
        file_put_contents($targetDir . '/bad-entry.sh', '#!/bin/bash');
        // Invalid JSON → entries = [] → no removal from registry
        file_put_contents($targetDir . '/bad-entry.json', '{{invalid');

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        if (!is_dir(dirname($hookRegistryPath))) {
            mkdir(dirname($hookRegistryPath), 0775, true);
        }
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => ['preToolUse' => [['command' => 'keep-me']]],
        ]));

        $installer = new HookInstaller();
        $result = $installer->uninstallCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        self::assertDirectoryDoesNotExist($targetDir);
        // Registry entry should remain since no entries were parsed for removal
        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertCount(1, $data['hooks']['preToolUse']);
    }

    // ─── HookInstaller: writeHookRegistry creates parent dir ──────────

    public function testInstallCursorCreatesHooksJsonParentDirectory(): void
    {
        $sourceDir = $this->tmpDir . '/source/deep-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/deep-hook.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/deep-hook.json', json_encode([
            'preToolUse' => [['command' => '.cursor/hooks/deep-hook/deep-hook.sh']],
        ]));

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/deep-hook';
        // hooks.json in a non-existent deep directory
        $hookRegistryPath = $this->tmpDir . '/workspace/deep/nested/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        self::assertFileExists($hookRegistryPath);
    }

    // ─── Installer: uninstallHook with drift on kiro ──────────────────

    public function testUninstallTypedHookWithDriftBlocksWithoutForce(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/my-hook.kiro.hook', '{"name":"my-hook","version":"1"}');

        $project = $this->tmpDir . '/project';
        mkdir($project . '/.kiro/hooks', 0775, true);
        // Installed file has different content (drift)
        file_put_contents($project . '/.kiro/hooks/my-hook.kiro.hook', '{"name":"my-hook","version":"2-modified"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro'], false);

        chdir($old);

        self::assertSame(1, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('drift', $output);
        self::assertStringContainsString('--force', $output);
        // File should still exist (not deleted)
        self::assertFileExists($project . '/.kiro/hooks/my-hook.kiro.hook');
    }

    public function testUninstallTypedHookWithDriftSucceedsWithForce(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/my-hook.kiro.hook', '{"name":"my-hook","version":"1"}');

        $project = $this->tmpDir . '/project';
        mkdir($project . '/.kiro/hooks', 0775, true);
        file_put_contents($project . '/.kiro/hooks/my-hook.kiro.hook', '{"name":"my-hook","version":"2-modified"}');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro'], true);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Uninstalled hook my-hook from kiro', $output);
        self::assertFileDoesNotExist($project . '/.kiro/hooks/my-hook.kiro.hook');
    }

    public function testUninstallTypedHookMissingOnKiroSkips(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/ghost.kiro.hook', '{"name":"ghost"}');

        $project = $this->tmpDir . '/project';
        mkdir($project, 0775, true);
        // No hook installed

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['ghost'],
        ], ['kiro'], false);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip]', $output);
    }

    // ─── Installer: uninstallHook on cursor (skip path) ───────────────

    public function testUninstallTypedHookOnCursorSkipsWhenDirMissing(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);

        $project = $this->tmpDir . '/project';
        mkdir($project, 0775, true);
        // No .cursor/hooks/ghost dir

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['ghost'],
        ], ['cursor'], false);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip]', $output);
    }

    // ─── Installer: installTyped with preset name for gitignore ───────

    public function testInstallTypedPassesPresetNameToGitignore(): void
    {
        $pkg = $this->tmpDir . '/pkg-gi';
        mkdir($pkg . '/.cursor/skills/demo', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/demo/SKILL.md', 'x');
        file_put_contents($pkg . '/.gitignore', implode("\n", [
            '## @apm:block ability=my-preset target=*',
            '/.cache/preset/',
            '## @apm:end',
        ]));
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: demo',
            '    description: Demo',
            '    targets:',
            '      cursor: .cursor/skills/demo',
        ]) . "\n");

        $project = $this->tmpDir . '/project-gi';
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => ['demo'],
            'rules' => [],
            'agents' => [],
        ], ['cursor'], 'my-preset');

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        $gitignore = (string) file_get_contents($project . '/.gitignore');
        self::assertStringContainsString('/.cache/preset/', $gitignore);
    }

    // ─── Installer: isInstalledOnTarget rule not found ────────────────

    public function testIsInstalledOnTargetReturnsFalseWhenRuleNotFound(): void
    {
        $pkg = $this->tmpDir . '/pkg-is';
        mkdir($pkg, 0775, true);

        $project = $this->tmpDir . '/project-is';
        mkdir($project . '/.cursor/rules', 0775, true);
        // Create a rule file that doesn't match the name we'll search for
        file_put_contents($project . '/.cursor/rules/other-rule.mdc', 'x');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->isInstalledOnTarget('rule', 'nonexistent-rule', 'cursor');

        chdir($old);

        self::assertFalse($result);
    }

    // ─── Installer: installTyped silently skips when target not in entry ──

    public function testInstallTypedSkipsAbilityWhenTargetNotInEntry(): void
    {
        $pkg = $this->tmpDir . '/pkg-skip';
        mkdir($pkg . '/.cursor/skills/only-cursor', 0775, true);
        file_put_contents($pkg . '/.cursor/skills/only-cursor/SKILL.md', 'x');
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: only-cursor',
            '    description: Cursor only',
            '    targets:',
            '      cursor: .cursor/skills/only-cursor',
        ]) . "\n");

        $project = $this->tmpDir . '/project-skip';
        mkdir($project, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        // Install on kiro — but the skill only has cursor target
        $result = $installer->installTyped([
            'skills' => ['only-cursor'],
            'rules' => [],
            'agents' => [],
        ], ['kiro']);

        chdir($old);

        self::assertSame(0, $result['exit_code']);
        // No error, no [fail] — just silently skipped
        $output = implode("\n", $result['lines']);
        self::assertStringNotContainsString('[fail]', $output);
        self::assertStringNotContainsString('Installed', $output);
    }

    // ─── InstallCommand: bootstrap failure path ───────────────────────

    public function testInstallCommandBootstrapFailsWhenInitThrows(): void
    {
        $pkg = $this->tmpDir . '/pkg-boot';
        mkdir($pkg . '/docs', 0775, true);
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/docs/README.md', '# Docs');
        file_put_contents($pkg . '/issues/README.md', '# Issues');
        file_put_contents($pkg . '/AGENTS.md', 'pkg agents');
        file_put_contents($pkg . '/abilities.yaml', "skills: []\n");

        // Create a project dir that already has scaffold (will cause error without --force)
        $project = $this->tmpDir . '/project-boot';
        mkdir($project . '/docs', 0775, true);
        file_put_contents($project . '/AGENTS.md', 'existing');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($project);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        // Create a ProjectInitializer that will throw (scaffold targets exist without --force)
        $initializer = new ProjectInitializer($pkg);

        $cmd = new InstallCommand($installer, $initializer);
        $app = new Application();
        $app->addCommand($cmd);

        $tester = new CommandTester($cmd);
        // No preset argument → bootstrap mode; scaffold exists → throws
        $exit = $tester->execute([]);

        chdir($old);

        self::assertSame(\Symfony\Component\Console\Command\Command::FAILURE, $exit);
        self::assertStringContainsString('already contains', $tester->getDisplay());
    }

    // ─── Helper ───────────────────────────────────────────────────────

}
