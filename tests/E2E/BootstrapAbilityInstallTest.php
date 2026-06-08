<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\E2E;

/**
 * E2E: Bootstrap ability install phase — `apm bootstrap`.
 *
 * Validates bootstrap.includes ability installation, skip logic,
 * force overwrite, partial failure tolerance, and fail-fast validation.
 *
 * Ref: Requirement 3
 */
final class BootstrapAbilityInstallTest extends EndToEndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Scaffold source files required for bootstrap phase 1
        mkdir($this->packageRoot . '/docs', 0775, true);
        file_put_contents($this->packageRoot . '/docs/README.md', "# Docs\n");
        mkdir($this->packageRoot . '/issues', 0775, true);
        file_put_contents($this->packageRoot . '/issues/README.md', "# Issues\n");
        file_put_contents($this->packageRoot . '/AGENTS.md', "# Agents\n");
    }

    /**
     * Test 1: Bootstrap installs abilities from bootstrap.includes.
     */
    public function testBootstrapInstallsAbilitiesFromIncludes(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
bootstrap:
  includes:
    - skill:my-skill
skills:
  - path: my-skill
    description: Test skill
    targets:
      cursor: .cursor/skills/my-skill
YAML);

        $this->createSkillSource('my-skill', "# My Skill\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        $this->assertFileInWorkspace('.cursor/skills/my-skill/SKILL.md');
        self::assertStringContainsString('Installed skill my-skill', $r['stdout']);
    }

    /**
     * Test 2: Bootstrap skip logic — already installed, no --force.
     */
    public function testBootstrapSkipsAlreadyInstalledAbility(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
bootstrap:
  includes:
    - skill:my-skill
skills:
  - path: my-skill
    description: Test skill
    targets:
      cursor: .cursor/skills/my-skill
YAML);

        $this->createSkillSource('my-skill', "# Baseline Content\n");

        // Pre-install the skill with custom content
        $preInstallDir = $this->workspace . '/.cursor/skills/my-skill';
        mkdir($preInstallDir, 0775, true);
        file_put_contents($preInstallDir . '/SKILL.md', "# Pre-installed Custom\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        self::assertStringContainsString('[skip]', $r['stdout']);
        // Verify the pre-installed file is NOT overwritten
        self::assertSame("# Pre-installed Custom\n", $this->readWorkspaceFile('.cursor/skills/my-skill/SKILL.md'));
    }

    /**
     * Test 3: Bootstrap force overwrites existing abilities.
     */
    public function testBootstrapForceOverwritesExistingAbility(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
bootstrap:
  includes:
    - skill:my-skill
skills:
  - path: my-skill
    description: Test skill
    targets:
      cursor: .cursor/skills/my-skill
YAML);

        $this->createSkillSource('my-skill', "# Baseline Content\n");

        // Pre-install the skill with modified content
        $preInstallDir = $this->workspace . '/.cursor/skills/my-skill';
        mkdir($preInstallDir, 0775, true);
        file_put_contents($preInstallDir . '/SKILL.md', "# Modified By User\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor', '--force']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        // Verify the file is overwritten with baseline content
        self::assertSame("# Baseline Content\n", $this->readWorkspaceFile('.cursor/skills/my-skill/SKILL.md'));
    }

    /**
     * Test 4: Bootstrap partial failure tolerance — one valid, one missing source.
     */
    public function testBootstrapPartialFailureTolerance(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
bootstrap:
  includes:
    - skill:good-skill
    - skill:bad-skill
skills:
  - path: good-skill
    description: Good skill with source
    targets:
      cursor: .cursor/skills/good-skill
  - path: bad-skill
    description: Bad skill without source files
    targets:
      cursor: .cursor/skills/bad-skill
YAML);

        // Only create source for good-skill, NOT for bad-skill
        $this->createSkillSource('good-skill', "# Good Skill\n");

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(1, $r['exit'], "Expected exit code 1 for partial failure.\nstderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        // Verify the good skill IS installed
        $this->assertFileInWorkspace('.cursor/skills/good-skill/SKILL.md');
        // Verify output contains failure indicator for bad-skill
        self::assertStringContainsString('[fail]', $r['stdout']);
    }

    /**
     * Test 5: Bootstrap with empty includes — scaffold only.
     */
    public function testBootstrapWithEmptyIncludesScaffoldOnly(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
skills:
  - path: some-skill
    description: A skill not in bootstrap
    targets:
      cursor: .cursor/skills/some-skill
YAML);

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(0, $r['exit'], "stderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        // Scaffold files should be created
        $this->assertFileInWorkspace('docs/README.md');
        $this->assertFileInWorkspace('issues/README.md');
        $this->assertFileInWorkspace('AGENTS.md');
        // No abilities installed
        $this->assertFileNotInWorkspace('.cursor/skills/some-skill/SKILL.md');
        self::assertStringContainsString('Bootstrap complete', $r['stdout']);
    }

    /**
     * Test 6: Bootstrap validates bootstrap.includes — fail-fast on invalid reference.
     */
    public function testBootstrapFailsFastOnInvalidReference(): void
    {
        $this->createAbilitiesYaml(<<<'YAML'
bootstrap:
  includes:
    - skill:nonexistent
skills:
  - path: existing-skill
    description: An existing skill
    targets:
      cursor: .cursor/skills/existing-skill
YAML);

        $r = $this->apm(['bootstrap', '-t', 'cursor']);

        self::assertSame(1, $r['exit'], "Expected exit code 1 for invalid reference.\nstderr: {$r['stderr']}\nstdout: {$r['stdout']}");
        // Verify error message mentions the invalid reference
        $combined = $r['stdout'] . $r['stderr'];
        self::assertStringContainsString('nonexistent', $combined);
    }
}
