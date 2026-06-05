<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\ScopeGuard;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('deprecated-scope')]
final class ScopeGuardTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-scope-guard-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testUserScopeRejectsProjectOnlyRule(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);
        $marker = $this->tmpDir . '/side-effect-marker';
        file_put_contents($marker, 'untouched');

        try {
            $guard->assertBatchAllowed(DeployScope::User, ['rule:cursor-scope']);
            self::fail('Expected InvalidScopeException');
        } catch (InvalidScopeException $e) {
            self::assertStringContainsString('cursor-scope', $e->getMessage());
        }

        self::assertSame('untouched', file_get_contents($marker));
    }

    public function testUserScopeRejectsProjectOnlySkill(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('tga-query');

        $guard->assertBatchAllowed(DeployScope::User, ['skill:tga-query']);
    }

    public function testUserScopeAllowsUserCapableAbility(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $guard->assertBatchAllowed(DeployScope::User, ['skill:apm', 'agent:code-reviewer']);

        self::assertTrue(true);
    }

    public function testProjectScopeAllowsProjectOnlyAbilities(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $guard->assertBatchAllowed(DeployScope::Project, [
            'rule:cursor-scope',
            'skill:tga-query',
            'gitignore:php',
            'prompt:graphify:project-section',
        ]);

        self::assertTrue(true);
    }

    public function testMixedBatchFailsEntireBatchOnFirstIllegalItem(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);
        $marker = $this->tmpDir . '/batch-marker';
        file_put_contents($marker, 'clean');

        try {
            $guard->assertBatchAllowed(DeployScope::User, [
                'skill:apm',
                'rule:cursor-scope',
                'skill:tga-query',
            ]);
            self::fail('Expected InvalidScopeException');
        } catch (InvalidScopeException $e) {
            self::assertStringContainsString('cursor-scope', $e->getMessage());
        }

        self::assertSame('clean', file_get_contents($marker));
    }

    public function testInvalidTypedRefWithoutColonIsIgnored(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $guard->assertBatchAllowed(DeployScope::User, ['skill:apm', 'not-a-typed-ref']);

        self::assertTrue(true);
    }

    public function testUnknownAbilityTypeIsIgnored(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $guard->assertBatchAllowed(DeployScope::User, ['skill:apm', 'bogus:whatever']);

        self::assertTrue(true);
    }

    public function testUserScopeRejectsGitignoreAndPrompt(): void
    {
        $registry = $this->registryWithSampleAbilities();
        $guard = new ScopeGuard($registry);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('php');

        $guard->assertBatchAllowed(DeployScope::User, ['gitignore:php']);
    }

    private function registryWithSampleAbilities(): AbilityRegistry
    {
        $yaml = <<<'YAML'
rules:
  - path: cursor-scope
    description: Cursor scope rule
    targets:
      cursor: .cursor/rules/cursor-scope.mdc
    scopes:
      - project

skills:
  - path: apm
    description: User-capable skill
    targets:
      cursor: .cursor/skills/apm/
    scopes:
      - user
      - project

  - path: tga-query
    description: Project-only skill
    targets:
      cursor: .cursor/skills/tga-query/
    scopes:
      - project

agents:
  - path: code-reviewer
    description: Dual-scope agent
    targets:
      cursor: .cursor/agents/code-reviewer.md
    scopes:
      - user
      - project

gitignore:
  - marker: php
    description: PHP ignores

prompts:
  - name: graphify:project-section
    message: Prompt body
YAML;

        return new AbilityRegistry($this->writeYaml($yaml));
    }

    private function writeYaml(string $yaml): string
    {
        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }
}
