<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PresetInstallValidationTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-preset-valid-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testUnknownSkillFailsWithNoFilesUnderCursor(): void
    {
        [$pkg, $proj] = $this->fixturePackageAndProject([
            'skills: []',
            'presets:',
            '  - name: bad-preset',
            '    description: bad',
            '    includes:',
            '      - skill:ghost-skill',
        ]);

        $exit = $this->runInstall($pkg, $proj, 'bad-preset');

        self::assertSame(Command::FAILURE, $exit['code']);
        self::assertStringContainsString('ghost-skill', $exit['display']);
        self::assertStringContainsString('not found in registry', $exit['display']);
        self::assertDirectoryDoesNotExist($proj . '/.cursor');
    }

    public function testMixedValidAndInvalidFailsWithZeroWrites(): void
    {
        [$pkg, $proj] = $this->fixturePackageAndProject([
            'skills:',
            '  - path: graphify',
            '    description: graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'presets:',
            '  - name: mixed-preset',
            '    description: mixed',
            '    includes:',
            '      - skill:graphify',
            '      - skill:missing-skill',
        ], createGraphify: true);

        $exit = $this->runInstall($pkg, $proj, 'mixed-preset');

        self::assertSame(Command::FAILURE, $exit['code']);
        self::assertStringContainsString('missing-skill', $exit['display']);
        self::assertStringContainsString('not found in registry', $exit['display']);
        self::assertDirectoryDoesNotExist($proj . '/.cursor/skills/graphify');
        self::assertDirectoryDoesNotExist($proj . '/.cursor');
    }

    public function testValidPresetInstallSucceeds(): void
    {
        [$pkg, $proj] = $this->fixturePackageAndProject([
            'skills:',
            '  - path: graphify',
            '    description: graphify',
            '    targets:',
            '      cursor: .cursor/skills/graphify',
            'presets:',
            '  - name: ok-preset',
            '    description: ok',
            '    includes:',
            '      - skill:graphify',
        ], createGraphify: true);

        $exit = $this->runInstall($pkg, $proj, 'ok-preset');

        self::assertSame(Command::SUCCESS, $exit['code']);
        self::assertStringContainsString('Preset: ok-preset', $exit['display']);
        self::assertStringContainsString('Installed skill graphify', $exit['display']);
        self::assertFileExists($proj . '/.cursor/skills/graphify/SKILL.md');
    }

    /**
     * @param list<string> $abilitiesYamlLines
     * @return array{0: string, 1: string} package root, project root
     */
    private function fixturePackageAndProject(array $abilitiesYamlLines, bool $createGraphify = false): array
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);

        if ($createGraphify) {
            mkdir($pkg . '/.cursor/skills/graphify', 0775, true);
            file_put_contents($pkg . '/.cursor/skills/graphify/SKILL.md', "skill\n");
        }

        file_put_contents($pkg . '/abilities.yaml', implode("\n", $abilitiesYamlLines) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        return [$pkg, $proj];
    }

    /**
     * @return array{code: int, display: string}
     */
    private function runInstall(string $pkg, string $proj, string $preset): array
    {
        chdir($proj);

        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $presetRegistry = new PresetRegistry($registry);
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new InstallCommand($installer, $presetRegistry);
        $tester = new CommandTester($cmd);
        $code = $tester->execute(['preset' => $preset, '--target' => ['cursor']]);

        return ['code' => $code, 'display' => $tester->getDisplay()];
    }
}
