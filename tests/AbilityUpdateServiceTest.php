<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\AbilityUpdateService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\ComposerBaselineResolver;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;

final class AbilityUpdateServiceTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;
    private string|false $oldBaseline;
    private string|false $oldHome;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-update-svc-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
        $this->oldBaseline = getenv('APM_BASELINE_ROOT');
        $this->oldHome = getenv('HOME');
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        if ($this->oldBaseline === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $this->oldBaseline);
        }
        if ($this->oldHome === false) {
            putenv('HOME');
        } else {
            putenv('HOME=' . $this->oldHome);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testReportUpToDateWhenNoInstalledAbilities(): void
    {
        $baseline = $this->createBaselinePackage();
        $workspace = $this->tmpDir . '/workspace';
        mkdir($workspace, 0775, true);
        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $service = $this->service($baseline);

        $result = $service->reportChanges(false);

        self::assertSame(0, $result['exit_code']);
        self::assertContains('All installed abilities are up to date.', $result['lines']);
    }

    public function testReportChangedWithoutForce(): void
    {
        [$baseline, $workspace] = $this->createBaselineWithSkill('demo-skill');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $installed = $workspace . '/.cursor/skills/demo-skill/SKILL.md';
        file_put_contents($installed, "# Local edit\n");

        $service = $this->service($baseline);
        $result = $service->reportChanges(false);

        self::assertSame(0, $result['exit_code']);
        self::assertTrue(
            (bool) array_filter(
                $result['lines'],
                static fn (string $line): bool => str_contains($line, 'changed: skill:demo-skill'),
            ),
        );
        self::assertContains('Run with --force to overwrite local changes from baseline.', $result['lines']);
    }

    public function testForceOverwritesProjectScopeDrift(): void
    {
        [$baseline, $workspace] = $this->createBaselineWithSkill('demo-skill');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $installed = $workspace . '/.cursor/skills/demo-skill/SKILL.md';
        file_put_contents($installed, "# Local edit\n");

        $service = $this->service($baseline);
        $result = $service->reportChanges(true);

        self::assertSame(0, $result['exit_code']);
        self::assertStringContainsString(
            '# Baseline',
            (string) file_get_contents($installed),
        );
    }

    public function testReportsUserAndProjectScopeSeparately(): void
    {
        [$baseline, $workspace] = $this->createBaselineWithSkill('shared-skill');
        $home = $this->tmpDir . '/home';
        mkdir($home . '/.cursor/skills/shared-skill', 0775, true);
        file_put_contents($home . '/.cursor/skills/shared-skill/SKILL.md', "# User drift\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        putenv('HOME=' . $home);
        chdir($workspace);

        file_put_contents($workspace . '/.cursor/skills/shared-skill/SKILL.md', "# Project drift\n");

        $service = $this->service($baseline);
        $result = $service->reportChanges(false);

        $lines = implode("\n", $result['lines']);
        self::assertStringContainsString('(user)', $lines);
        self::assertStringContainsString('(project)', $lines);
    }

    private function service(string $baselineRoot): AbilityUpdateService
    {
        $registry = new AbilityRegistry($baselineRoot . '/abilities.yaml');
        $resolver = new DeployRootResolver();

        return new AbilityUpdateService(
            $registry,
            new ComposerBaselineResolver(overrideInstallPath: $baselineRoot),
            new CheckService(new ComposerBaselineResolver(overrideInstallPath: $baselineRoot)),
            new InstallationProbe($registry, $resolver),
            $resolver,
            new DirectoryMirrorService(),
        );
    }

    private function createBaselinePackage(): string
    {
        $baseline = $this->tmpDir . '/baseline';
        mkdir($baseline, 0775, true);
        file_put_contents($baseline . '/abilities.yaml', "skills: []\nrules: []\nagents: []\nhooks: []\n");

        return $baseline;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createBaselineWithSkill(string $name): array
    {
        $baseline = $this->tmpDir . '/baseline-' . $name;
        $workspace = $this->tmpDir . '/workspace-' . $name;
        mkdir($baseline . '/.cursor/skills/' . $name, 0775, true);
        file_put_contents($baseline . '/.cursor/skills/' . $name . '/SKILL.md', "# Baseline\n");
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: ' . $name,
            '    description: Demo',
            '    targets:',
            '      cursor: .cursor/skills/' . $name,
        ]) . "\n");

        mkdir($workspace . '/.cursor/skills/' . $name, 0775, true);
        copy(
            $baseline . '/.cursor/skills/' . $name . '/SKILL.md',
            $workspace . '/.cursor/skills/' . $name . '/SKILL.md',
        );

        return [$baseline, $workspace];
    }
}
