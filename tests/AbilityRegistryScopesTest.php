<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\AbilityRegistryException;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;

final class AbilityRegistryScopesTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-registry-scopes-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testParseDefaultScopesWhenOmitted(): void
    {
        $yaml = <<<'YAML'
rules:
  - path: scoped-rule
    description: Rule without scopes field
    targets:
      cursor: .cursor/rules/scoped-rule.mdc
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertSame(['project'], $result['rules'][0]->scopes);
    }

    public function testParseExplicitScopesUserAndProject(): void
    {
        $yaml = <<<'YAML'
agents:
  - path: dual-scope-agent
    description: Agent deployable to user and project
    targets:
      cursor: .cursor/agents/dual-scope-agent.md
    scopes:
      - user
      - project
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);
        $result = $registry->parse();

        self::assertSame(['user', 'project'], $result['agents'][0]->scopes);
    }

    public function testParseInvalidScopeCollectsValidationError(): void
    {
        $yaml = <<<'YAML'
skills:
  - path: bad-scope-skill
    description: Skill with invalid deploy scope
    targets:
      cursor: .cursor/skills/bad-scope-skill/
    scopes:
      - workspace
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        $registry = new AbilityRegistry($path);

        try {
            $registry->parse();
            self::fail('Expected AbilityRegistryException was not thrown');
        } catch (AbilityRegistryException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('validation failed', $message);
            self::assertStringContainsString('scopes', $message);
            self::assertStringContainsString('workspace', $message);
        }
    }
}
