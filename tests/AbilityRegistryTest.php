<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityEntry;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\AbilityRegistryException;
use PHPUnit\Framework\TestCase;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class AbilityRegistryTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-registry-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testParseValidYamlReturnsAbilityEntriesBySection(): void
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

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertCount(1, $result['rules']);
        self::assertInstanceOf(AbilityEntry::class, $result['rules'][0]);
        self::assertSame('my-rule', $result['rules'][0]->path);
        self::assertSame('A test rule', $result['rules'][0]->description);
        self::assertSame(['cursor' => '.cursor/rules/my-rule.mdc', 'kiro' => '.kiro/steering/my-rule.md'], $result['rules'][0]->targets);
        self::assertSame('rule', $result['rules'][0]->type);

        self::assertCount(1, $result['agents']);
        self::assertSame('my-agent', $result['agents'][0]->path);
        self::assertSame('agent', $result['agents'][0]->type);

        self::assertCount(1, $result['skills']);
        self::assertSame('my-skill', $result['skills'][0]->path);
        self::assertSame('skill', $result['skills'][0]->type);

        self::assertCount(1, $result['hooks']);
        self::assertSame('my-hook', $result['hooks'][0]->path);
        self::assertSame('hook', $result['hooks'][0]->type);
        self::assertSame(['kiro' => '.kiro/hooks/my-hook.kiro.hook'], $result['hooks'][0]->targets);
    }

    public function testParseIgnoresUnknownSections(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: valid-rule
    description: Valid rule
    targets:
      cursor: .cursor/rules/valid.mdc

unknown_section:
  - path: something
    description: Should be ignored
    targets:
      cursor: .cursor/something

another_unknown:
  - foo: bar
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertCount(1, $result['rules']);
        self::assertSame('valid-rule', $result['rules'][0]->path);
        self::assertArrayNotHasKey('unknown_section', $result);
        self::assertArrayNotHasKey('another_unknown', $result);
    }

    public function testParseMissingFieldsCollectsAllErrorsBeforeReporting(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: rule-no-desc
    targets:
      cursor: .cursor/rules/x.mdc

  - description: rule-no-path
    targets:
      cursor: .cursor/rules/y.mdc

hooks:
  - path: hook-no-targets
    description: Missing targets

  - path: hook-no-desc
    targets:
      kiro: .kiro/hooks/x.kiro.hook
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);

        try {
            $registry->parse();
            self::fail('Expected AbilityRegistryException was not thrown');
        } catch (AbilityRegistryException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('description', $message);
            self::assertStringContainsString('path', $message);
            self::assertStringContainsString('targets', $message);
            self::assertStringContainsString("validation failed", $message);
        }
    }

    public function testParseThrowsExceptionWhenFileNotFound(): void
    {
        $path = $this->tmpDir . '/nonexistent.yaml';
        $registry = new AbilityRegistry($path);

        try {
            $registry->parse();
            self::fail('Expected AbilityRegistryException was not thrown');
        } catch (AbilityRegistryException $e) {
            self::assertStringContainsString($path, $e->getMessage());
            self::assertStringContainsString('not found', $e->getMessage());
        }
    }

    public function testParseThrowsExceptionForInvalidYaml(): void
    {
        $invalidYaml = "rules:\n  - path: ok\n    description: ok\n    targets: [\n      invalid yaml here";
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $invalidYaml);

        $registry = new AbilityRegistry($path);

        try {
            $registry->parse();
            self::fail('Expected AbilityRegistryException was not thrown');
        } catch (AbilityRegistryException $e) {
            self::assertStringContainsString($path, $e->getMessage());
            self::assertStringContainsString('Invalid YAML', $e->getMessage());
        }
    }

    public function testParseReturnsEmptyArraysForMissingSections(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: only-rule
    description: Only rule present
    targets:
      cursor: .cursor/rules/only.mdc
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertCount(1, $result['rules']);
        self::assertSame([], $result['agents']);
        self::assertSame([], $result['skills']);
        self::assertSame([], $result['hooks']);
    }

    public function testKnownSectionsReturnsExpectedList(): void
    {
        $expected = ['rules', 'agents', 'skills', 'hooks', 'gitignore', 'presets'];
        self::assertSame($expected, AbilityRegistry::knownSections());
    }

    public function testParseHookWithSinglePlatformTarget(): void
    {
        $yaml = <<<'YAML'
hooks:
  - path: kiro-only-hook
    description: Hook for Kiro only
    targets:
      kiro: .kiro/hooks/kiro-only.kiro.hook

  - path: cursor-only-hook
    description: Hook for Cursor only
    targets:
      cursor: .cursor/hooks/cursor-only/
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertCount(2, $result['hooks']);
        self::assertSame(['kiro' => '.kiro/hooks/kiro-only.kiro.hook'], $result['hooks'][0]->targets);
        self::assertSame(['cursor' => '.cursor/hooks/cursor-only/'], $result['hooks'][1]->targets);
    }

}
