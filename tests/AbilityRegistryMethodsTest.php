<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityEntry;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;

final class AbilityRegistryMethodsTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-registry-methods-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testGlobalSetupIncludesReturnsEmptyWhenSectionMissing(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: some-rule
    description: Rule without global-setup
    targets:
      cursor: .cursor/rules/some-rule.mdc
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertSame([], $registry->globalSetupIncludes());
    }

    public function testGlobalSetupIncludesReturnsEmptyWhenIncludesMissing(): void
    {
        $yaml = <<<'YAML'
global-setup: {}
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertSame([], $registry->globalSetupIncludes());
    }

    public function testGlobalSetupIncludesParsesTypedRefs(): void
    {
        $yaml = <<<'YAML'
global-setup:
  includes:
    - skill:apm
    - agent:code-reviewer
    - rule:git:git-conventions
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertSame(
            [
                ['type' => 'skill', 'path' => 'apm'],
                ['type' => 'agent', 'path' => 'code-reviewer'],
                ['type' => 'rule', 'path' => 'git:git-conventions'],
            ],
            $registry->globalSetupIncludes(),
        );
    }

    public function testGlobalSetupIncludesSkipsInvalidEntries(): void
    {
        $yaml = <<<'YAML'
global-setup:
  includes:
    - skill:valid-skill
    - not-a-typed-ref
    - agent:
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertSame(
            [['type' => 'skill', 'path' => 'valid-skill']],
            $registry->globalSetupIncludes(),
        );
    }

    public function testProjectOnlyPathsFromScopesGitignoreAndPrompts(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: cursor-scope
    description: Project-only rule
    targets:
      cursor: .cursor/rules/cursor-scope.mdc
    scopes:
      - project

  - path: plugin:superpowers-integration
    description: Superpowers rule
    targets:
      cursor: .cursor/rules/plugin/superpowers-integration.mdc

  - path: git:git-conventions
    description: Dual-scope rule
    targets:
      cursor: .cursor/rules/git/git-conventions.mdc
    scopes:
      - user
      - project

skills:
  - path: tga-query
    description: TGA skill
    targets:
      cursor: .cursor/skills/tga-query/
    scopes:
      - project

  - path: lark-sheets
    description: Lark skill
    targets:
      cursor: .cursor/skills/lark-sheets/

  - path: apm
    description: User-capable skill
    targets:
      cursor: .cursor/skills/apm/
    scopes:
      - user
      - project

gitignore:
  - marker: php
    description: PHP ignores

  - marker: graphify
    description: Graphify ignores

prompts:
  - name: graphify:project-section
    message: |
      Prompt body
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        $expected = [
            'cursor-scope',
            'plugin:superpowers-integration',
            'tga-query',
            'lark-sheets',
            'php',
            'graphify',
            'graphify:project-section',
        ];

        self::assertEqualsCanonicalizing($expected, $registry->projectOnlyPaths());
    }

    public function testGetEntryReturnsMatchingAbilityEntry(): void
    {
        $yaml = <<<'YAML'
agents:
  - path: code-reviewer
    description: Code review agent
    targets:
      cursor: .cursor/agents/code-reviewer.md
      kiro: .kiro/agents/code-reviewer.md
    scopes:
      - user
      - project

skills:
  - path: apm
    description: APM skill
    targets:
      cursor: .cursor/skills/apm/
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        $entry = $registry->getEntry('agent', 'code-reviewer');

        self::assertInstanceOf(AbilityEntry::class, $entry);
        self::assertSame('code-reviewer', $entry->path);
        self::assertSame('agent', $entry->type);
        self::assertSame(['user', 'project'], $entry->scopes);
        self::assertSame(
            ['cursor' => '.cursor/agents/code-reviewer.md', 'kiro' => '.kiro/agents/code-reviewer.md'],
            $entry->targets,
        );
    }

    public function testGetEntryReturnsNullForUnknownPath(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: apm
    description: APM skill
    targets:
      cursor: .cursor/skills/apm/
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertNull($registry->getEntry('skill', 'missing-skill'));
    }

    public function testGetEntryReturnsNullWhenTypeDoesNotMatch(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: apm
    description: APM skill
    targets:
      cursor: .cursor/skills/apm/
YAML;

        $path = $this->writeYaml($yaml);
        $registry = new AbilityRegistry($path);

        self::assertNull($registry->getEntry('agent', 'apm'));
    }

    private function writeYaml(string $yaml): string
    {
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }
}
