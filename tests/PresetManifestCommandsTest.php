<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for preset write operations (createPreset, deletePreset, addAbility, removeAbility).
 *
 * These tests exercise the PresetRegistry write methods directly since the commands
 * use a hardcoded path to abilities.yaml relative to the command file.
 */
final class PresetManifestCommandsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-pmb-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmpDir);
        }
    }

    public function testPresetAddAbilitySavesNewAbilityToManifest(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/
  - path: new-skill-for-test
    description: Test skill
    targets:
      cursor: .cursor/skills/new-skill-for-test/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $registry->addAbility('gitflow', 'skill', 'new-skill-for-test');

        $preset = $registry->getPreset('gitflow');
        self::assertNotNull($preset);
        $paths = array_map(fn(array $inc) => $inc['type'] . ':' . $inc['path'], $preset['includes']);
        self::assertContains('skill:new-skill-for-test', $paths);
    }

    public function testPresetAddAbilityNoOpWhenAbilityAlreadyPresent(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $registry->addAbility('gitflow', 'skill', 'gitflow');

        $preset = $registry->getPreset('gitflow');
        self::assertNotNull($preset);
        // Should still have exactly one entry (no duplicate)
        $skillEntries = array_filter($preset['includes'], fn($inc) => $inc['type'] === 'skill' && $inc['path'] === 'gitflow');
        self::assertCount(1, $skillEntries);
    }

    public function testPresetRemoveAbilityRemovesFromManifest(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $registry->removeAbility('gitflow', 'skill', 'gitflow');

        $preset = $registry->getPreset('gitflow');
        self::assertNotNull($preset);
        self::assertSame([], $preset['includes']);
    }

    public function testPresetCreateSavesNewPreset(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $registry->createPreset('fresh-preset', 'A fresh preset', ['skill:gitflow']);

        $preset = $registry->getPreset('fresh-preset');
        self::assertNotNull($preset);
        self::assertSame('fresh-preset', $preset['name']);
        self::assertSame('A fresh preset', $preset['description']);
        self::assertSame(['type' => 'skill', 'path' => 'gitflow'], $preset['includes'][0]);
    }

    public function testPresetDeleteRemovesPresetFromManifest(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: gitflow
    description: GitFlow start/finish
    targets:
      cursor: .cursor/skills/gitflow/

presets:
  - name: gitflow
    description: GitFlow 全套能力
    includes:
      - skill:gitflow
  - name: to-delete
    description: Will be deleted
    includes:
      - skill:gitflow
YAML;
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new PresetRegistry(new AbilityRegistry($path));
        $registry->deletePreset('to-delete');

        self::assertNull($registry->getPreset('to-delete'));
        // gitflow should still exist
        self::assertNotNull($registry->getPreset('gitflow'));
    }
}
