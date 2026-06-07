<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;

final class InstallationProbeTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    private string $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-probe-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd() ?: '';
        chdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        chdir($this->oldCwd);
        $this->removeDir($this->tmpDir);
    }

    public function testCategoryRulePresentAtFullRelativePathInProjectScope(): void
    {
        $rulePath = '.cursor/rules/doc/writing-conventions.mdc';
        $this->mkdirForFile($rulePath, '# rule');

        $probe = $this->createProbe();

        self::assertTrue(
            $probe->isPresent('rule', 'doc:writing-conventions', 'cursor'),
        );
    }

    public function testCategoryRuleAbsentWhenOnlySameBasenameExistsElsewhere(): void
    {
        $this->mkdirForFile('.cursor/rules/other/writing-conventions.mdc', '# wrong place');

        $probe = $this->createProbe();

        self::assertFalse(
            $probe->isPresent('rule', 'doc:writing-conventions', 'cursor'),
        );
    }

    public function testBasenameCollisionDistinguishedByCategoryPath(): void
    {
        $this->mkdirForFile('.cursor/rules/doc/writing-conventions.mdc', '# doc rule');

        $probe = $this->createProbe();

        self::assertTrue(
            $probe->isPresent('rule', 'doc:writing-conventions', 'cursor'),
        );
        self::assertFalse(
            $probe->isPresent('rule', 'safety:writing-conventions', 'cursor'),
        );

        $this->mkdirForFile('.cursor/rules/safety/writing-conventions.mdc', '# safety rule');

        self::assertTrue(
            $probe->isPresent('rule', 'safety:writing-conventions', 'cursor'),
        );
        self::assertTrue(
            $probe->isPresent('rule', 'doc:writing-conventions', 'cursor'),
        );
    }

    public function testProjectScopeUsesWorkspaceRoot(): void
    {
        $rulePath = '.cursor/rules/doc/writing-conventions.mdc';
        $this->mkdirForFile($rulePath, '# rule');

        $probe = $this->createProbe();

        self::assertTrue(
            $probe->isPresent('rule', 'doc:writing-conventions', 'cursor'),
        );
    }

    public function testReturnsFalseForUnknownEntry(): void
    {
        $probe = $this->createProbe();

        self::assertFalse(
            $probe->isPresent('rule', 'missing:rule', 'cursor'),
        );
    }

    public function testReturnsFalseWhenTargetNotInEntry(): void
    {
        $probe = $this->createProbe();

        self::assertFalse(
            $probe->isPresent('rule', 'doc:writing-conventions', 'kiro'),
        );
    }

    private function createProbe(): InstallationProbe
    {
        return new InstallationProbe(
            new AbilityRegistry($this->writeRegistryYaml()),
            new DeployRootResolver(),
        );
    }

    private function writeRegistryYaml(): string
    {
        $yaml = <<<'YAML'
rules:
  - path: doc:writing-conventions
    description: Doc writing conventions
    targets:
      cursor: .cursor/rules/doc/writing-conventions.mdc

  - path: safety:writing-conventions
    description: Safety writing conventions
    targets:
      cursor: .cursor/rules/safety/writing-conventions.mdc
YAML;

        $path = $this->tmpDir . '/abilities.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }

    private function mkdirForFile(string $relativePath, string $contents): void
    {
        $this->mkdirForFileUnder($this->tmpDir, $relativePath, $contents);
    }

    private function mkdirForFileUnder(string $root, string $relativePath, string $contents): void
    {
        $absolute = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolute, $contents);
    }
}
