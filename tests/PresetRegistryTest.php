<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class PresetRegistryTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-pr-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testAllPresetsReturnsNormalizedPresets(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: git:branch-overview
    description: GitFlow 分支模型总览
    targets:
      cursor: .cursor/rules/git/branch-overview.mdc

skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - rule:git:branch-overview
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $presets = $registry->allPresets();

        self::assertCount(1, $presets);
        self::assertSame('gitflow', $presets[0]['name']);
        self::assertSame('GitFlow 全套能力', $presets[0]['description']);
        self::assertCount(2, $presets[0]['includes']);
        self::assertSame(['type' => 'rule', 'path' => 'git:branch-overview'], $presets[0]['includes'][0]);
        self::assertSame(['type' => 'skill', 'path' => 'gitflow'], $presets[0]['includes'][1]);
    }

    public function testGetPresetReturnsMatchingPreset(): void
    {
        $yaml = <<<'YAML'
presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - rule:git:branch-overview
      - skill:gitflow

  - name: spec-core
    description: Spec 规划与执行全套
    includes:
      - skill:spec-execution
      - agent:spec-gatekeeper
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));

        $preset = $registry->getPreset('spec-core');
        self::assertNotNull($preset);
        self::assertSame('spec-core', $preset['name']);
        self::assertSame('Spec 规划与执行全套', $preset['description']);
        self::assertSame(['type' => 'skill', 'path' => 'spec-execution'], $preset['includes'][0]);
        self::assertSame(['type' => 'agent', 'path' => 'spec-gatekeeper'], $preset['includes'][1]);
    }

    public function testGetPresetReturnsNullForUnknownName(): void
    {
        $yaml = <<<'YAML'
presets:
  - name: gitflow
    description: GitFlow
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));

        self::assertNull($registry->getPreset('nonexistent'));
    }

    public function testAllPresetsReturnsEmptyWhenNoPresetsSection(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: safety:command-safety
    description: Shell 命令安全约束
    targets:
      cursor: .cursor/rules/safety/command-safety.mdc
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));

        self::assertSame([], $registry->allPresets());
    }

    public function testIncludesParsingHandlesColonInPath(): void
    {
        $yaml = <<<'YAML'
presets:
  - name: test
    description: Test preset
    includes:
      - rule:git:branch-overview
      - rule:safety:config-safety
      - skill:spec-execution
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $preset = $registry->getPreset('test');

        self::assertNotNull($preset);
        self::assertSame(['type' => 'rule', 'path' => 'git:branch-overview'], $preset['includes'][0]);
        self::assertSame(['type' => 'rule', 'path' => 'safety:config-safety'], $preset['includes'][1]);
        self::assertSame(['type' => 'skill', 'path' => 'spec-execution'], $preset['includes'][2]);
    }

    public function testIncludesSkipsInvalidEntries(): void
    {
        $yaml = <<<'YAML'
presets:
  - name: test
    description: Test preset
    includes:
      - rule:valid-path
      - no-colon-entry
      - skill:another-valid
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $preset = $registry->getPreset('test');

        self::assertNotNull($preset);
        self::assertCount(2, $preset['includes']);
        self::assertSame(['type' => 'rule', 'path' => 'valid-path'], $preset['includes'][0]);
        self::assertSame(['type' => 'skill', 'path' => 'another-valid'], $preset['includes'][1]);
    }
    #[Group('deprecated-scope')]
    public function testPackageAbilitiesBootstrapIncludesResolve(): void
    {
        $path = dirname(__DIR__) . '/abilities.yaml';
        $registry = new AbilityRegistry($path);
        $includes = $registry->bootstrapIncludes();

        self::assertNotEmpty($includes);

        foreach ($includes as $include) {
            $entry = $registry->getEntry($include['type'], $include['path']);

            self::assertNotNull(
                $entry,
                sprintf(
                    "Ability '%s:%s' in bootstrap.includes not found in abilities.yaml",
                    $include['type'],
                    $include['path'],
                ),
            );
        }
    }

    public function testPackageAbilitiesHasNoDefaultPreset(): void
    {
        $path = dirname(__DIR__) . '/abilities.yaml';
        $preset = (new PresetRegistry(new AbilityRegistry($path)))->getPreset('default');

        self::assertNull($preset);
    }

}
