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

    public function testGetEntryReturnsMatchingAbilityEntry(): void
    {
        $yaml = <<<'YAML'
agents:
  - path: code-reviewer
    description: Code review agent
    targets:
      cursor: .cursor/agents/code-reviewer.md
      kiro: .kiro/agents/code-reviewer.md

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
