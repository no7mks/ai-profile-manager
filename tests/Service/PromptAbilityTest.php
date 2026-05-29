<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the `prompts` ability type.
 *
 * A prompt is a message that apm outputs after installation,
 * instructing the agent to perform a one-time action.
 */
final class PromptAbilityTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-prompt-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testRegistryParsesPromptsSection(): void
    {
        $yaml = <<<'YAML'
prompts:
  - name: graphify:project-section
    message: |
      Insert knowledge graph section into PROJECT.md.

skills:
  - path: demo
    description: Demo skill
    targets:
      kiro: .kiro/skills/demo/
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertArrayHasKey('prompts', $result);
        self::assertCount(1, $result['prompts']);
        self::assertSame('graphify:project-section', $result['prompts'][0]['name']);
        self::assertStringContainsString('PROJECT.md', $result['prompts'][0]['message']);
    }

    public function testRegistryReturnsEmptyPromptsWhenSectionMissing(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: demo
    description: Demo skill
    targets:
      kiro: .kiro/skills/demo/
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertArrayHasKey('prompts', $result);
        self::assertSame([], $result['prompts']);
    }

    public function testKnownSectionsIncludesPrompts(): void
    {
        self::assertContains('prompts', AbilityRegistry::knownSections());
    }

    public function testToTypedSpecIncludesPrompts(): void
    {
        $preset = [
            'name' => 'graphify',
            'description' => 'Graphify preset',
            'includes' => [
                ['type' => 'skill', 'path' => 'graphify'],
                ['type' => 'prompt', 'path' => 'graphify:project-section'],
            ],
        ];

        $spec = PresetRegistry::toTypedSpec($preset);

        self::assertArrayHasKey('prompts', $spec);
        self::assertSame(['graphify:project-section'], $spec['prompts']);
        self::assertSame(['graphify'], $spec['skills']);
    }

    public function testInstallerOutputsPromptMessage(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.kiro/skills/demo', 0775, true);
        file_put_contents($pkg . '/.kiro/skills/demo/SKILL.md', "# Demo\n");

        $yaml = <<<'YAML'
version: "1"
skills:
  - path: demo
    description: Demo skill
    targets:
      kiro: .kiro/skills/demo/
prompts:
  - name: test:setup-project
    message: |
      Please add a section to PROJECT.md.
YAML;
        file_put_contents($pkg . '/abilities.yaml', $yaml);

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            packageRoot: $pkg,
        );

        $items = [
            'skills' => ['demo'],
            'rules' => [],
            'agents' => [],
            'hooks' => [],
            'prompts' => ['test:setup-project'],
        ];

        $result = $installer->installTyped($items, ['kiro']);

        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Please add a section to PROJECT.md.', $output);
    }

    public function testPresetRegistryAddAbilityAcceptsPromptType(): void
    {
        $yaml = <<<'YAML'
prompts:
  - name: my:prompt
    message: Do something.
presets:
  - name: test-preset
    description: Test
    includes:
      - skill:demo
skills:
  - path: demo
    description: Demo
    targets:
      kiro: .kiro/skills/demo/
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $presetRegistry = new PresetRegistry($registry);

        $presetRegistry->addAbility('test-preset', 'prompt', 'my:prompt');

        $preset = $presetRegistry->getPreset('test-preset');
        self::assertNotNull($preset);

        $found = false;
        foreach ($preset['includes'] as $include) {
            if ($include['type'] === 'prompt' && $include['path'] === 'my:prompt') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Prompt ability should be in preset includes');
    }
}
