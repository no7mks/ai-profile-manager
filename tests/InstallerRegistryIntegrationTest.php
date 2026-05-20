<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Installer integration with AbilityRegistry.
 * Validates: Requirements 1 AC 1, 4; Requirement 2 AC 1-3
 */
final class InstallerRegistryIntegrationTest extends TestCase
{
    private string $tmpDir;
    private string $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-inst-reg-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        $this->removeDir($this->tmpDir);
    }

    public function testListAvailableItemsDelegatesToAbilityRegistryParse(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: my-rule
    description: A test rule
    targets:
      cursor: .cursor/rules/my-rule.mdc
      kiro: .kiro/steering/my-rule.md

agents:
  - path: my-agent
    description: A test agent
    targets:
      cursor: .cursor/agents/my-agent.md

skills:
  - path: my-skill
    description: A test skill
    targets:
      cursor: .cursor/skills/my-skill/
      kiro: .kiro/skills/my-skill/

hooks:
  - path: my-hook
    description: A test hook
    targets:
      kiro: .kiro/hooks/my-hook.kiro.hook
YAML;

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        $registry = new AbilityRegistry($registryPath);
        $installer = new Installer(
            registry: $registry,
        );

        $items = $installer->listAvailableItems();

        self::assertSame(['my-rule'], $items['rules']);
        self::assertSame(['my-agent'], $items['agents']);
        self::assertSame(['my-skill'], $items['skills']);
        self::assertSame(['my-hook'], $items['hooks']);
    }

    public function testInstallTypedAcceptsHooksKeyAndSkipsHookItems(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/abilities/skills/demo-skill', 0775, true);
        file_put_contents($pkg . '/abilities/skills/demo-skill/SKILL.md', "demo\n");

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, "rules: []\n");

        $project = $this->tmpDir . '/project';
        mkdir($project, 0775, true);
        chdir($project);

        $registry = new AbilityRegistry($registryPath);
        $installer = new Installer(
            registry: $registry,
            packageRoot: $pkg,
        );

        $result = $installer->installTyped([
            'skills' => ['demo-skill'],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['cursor']);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Installed skill demo-skill -> cursor', $output);
        // hooks key is accepted but hook items are skipped (no-op)
        self::assertStringContainsString('Hooks: my-hook', $output);
    }

    public function testUninstallTypedAcceptsHooksKeyAndSkipsHookItems(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, "rules: []\n");

        $project = $this->tmpDir . '/project';
        mkdir($project . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($project . '/.cursor/skills/demo-skill/SKILL.md', "x\n");
        chdir($project);

        $registry = new AbilityRegistry($registryPath);
        $installer = new Installer(
            registry: $registry,
            packageRoot: $pkg,
        );

        $result = $installer->uninstallTyped([
            'skills' => ['demo-skill'],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['cursor']);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Uninstalled skill demo-skill from cursor', $output);
        self::assertStringContainsString('Hooks: my-hook', $output);
    }

    public function testListAvailableItemsReturnsSortedNames(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: z-rule
    description: Z rule
    targets:
      cursor: .cursor/rules/z.mdc
  - path: a-rule
    description: A rule
    targets:
      cursor: .cursor/rules/a.mdc

skills:
  - path: zeta-skill
    description: Zeta
    targets:
      cursor: .cursor/skills/zeta/
  - path: alpha-skill
    description: Alpha
    targets:
      cursor: .cursor/skills/alpha/

hooks:
  - path: z-hook
    description: Z hook
    targets:
      kiro: .kiro/hooks/z.kiro.hook
  - path: a-hook
    description: A hook
    targets:
      kiro: .kiro/hooks/a.kiro.hook
YAML;

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        $registry = new AbilityRegistry($registryPath);
        $installer = new Installer(registry: $registry);

        $items = $installer->listAvailableItems();

        self::assertSame(['a-rule', 'z-rule'], $items['rules']);
        self::assertSame(['alpha-skill', 'zeta-skill'], $items['skills']);
        self::assertSame(['a-hook', 'z-hook'], $items['hooks']);
    }

    public function testInstallTypedDispatchesRuleAndAgentCorrectlyWithRegistry(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/abilities/rules/git', 0775, true);
        file_put_contents($pkg . '/abilities/rules/git/my-rule.cursor.mdc', "rule content\n");
        mkdir($pkg . '/abilities/agents', 0775, true);
        file_put_contents($pkg . '/abilities/agents/my-agent.cursor.md', "agent content\n");

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, "rules: []\n");

        $project = $this->tmpDir . '/project';
        mkdir($project, 0775, true);
        chdir($project);

        $registry = new AbilityRegistry($registryPath);
        $installer = new Installer(
            registry: $registry,
            packageRoot: $pkg,
        );

        $result = $installer->installTyped([
            'skills' => [],
            'rules' => ['my-rule'],
            'agents' => ['my-agent'],
            'hooks' => [],
        ], ['cursor']);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Installed rule my-rule -> cursor', $output);
        self::assertStringContainsString('Installed agent my-agent -> cursor', $output);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $dir . '/' . $item;
            is_dir($fullPath) ? $this->removeDir($fullPath) : unlink($fullPath);
        }
        rmdir($dir);
    }
}
