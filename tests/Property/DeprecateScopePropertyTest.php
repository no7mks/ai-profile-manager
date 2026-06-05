<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Property;

use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\BootstrapCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Service\ProjectInitializer;
use AiProfileManager\Service\ShowStatusPresenter;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Property-based tests for the deprecate-scope feature.
 *
 * Uses eris 1.1 for property-based testing (100 iterations per property).
 *
 * Validates: Requirements 4, AC 1-5
 */
final class DeprecateScopePropertyTest extends TestCase
{
    use TestTrait;
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-pbt-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── Property 2 ──────────────────────────────────────────────────

    /**
     * Property 2: Bootstrap includes 解析正确性
     *
     * For any valid abilities.yaml containing a bootstrap.includes list with well-formed
     * type:path entries, AbilityRegistry::bootstrapIncludes() SHALL return a list where
     * each element's type and path match the original YAML content exactly (round-trip
     * preservation of semantics).
     *
     * **Validates: Requirements 4.3**
     *
     * @group Feature: deprecate-scope, Property 2
     */
    public function testBootstrapIncludesRoundTripPreservation(): void
    {
        $this->limitTo(100);

        $typeGenerator = Generators::elements(['skill', 'rule', 'agent', 'hook']);
        // Build path segments by mapping over a length generator and constructing from valid chars
        $lowercaseLetters = range('a', 'z');
        $alphanumeric = array_merge(range('a', 'z'), range('0', '9'));
        $pathSegmentGenerator = Generators::map(
            function (int $len) use ($lowercaseLetters, $alphanumeric): string {
                $result = $lowercaseLetters[array_rand($lowercaseLetters)];
                for ($i = 1; $i < $len; $i++) {
                    $result .= $alphanumeric[array_rand($alphanumeric)];
                }
                return $result;
            },
            Generators::choose(3, 8),
        );

        $this->forAll(
            Generators::choose(1, 8),
            Generators::constant(null), // placeholder for seed variety
        )->then(function (int $count) use ($typeGenerator, $pathSegmentGenerator): void {
            $entries = [];
            for ($i = 0; $i < $count; $i++) {
                $type = $this->sample($typeGenerator, 1)->collected()[0];
                $segCount = random_int(1, 2);
                $segments = [];
                for ($j = 0; $j < $segCount; $j++) {
                    $segments[] = $this->sample($pathSegmentGenerator, 1)->collected()[0];
                }
                $entries[] = ['type' => $type, 'path' => implode(':', $segments)];
            }

            // Build YAML
            $includeLines = array_map(
                fn (array $e) => "    - {$e['type']}:{$e['path']}",
                $entries,
            );
            $yaml = "bootstrap:\n  includes:\n" . implode("\n", $includeLines) . "\n";

            $filePath = $this->tmpDir . '/p2-' . bin2hex(random_bytes(4)) . '.yaml';
            file_put_contents($filePath, $yaml);

            try {
                $registry = new AbilityRegistry($filePath);
                $result = $registry->bootstrapIncludes();

                self::assertCount(count($entries), $result);
                foreach ($entries as $i => $expected) {
                    self::assertSame($expected['type'], $result[$i]['type']);
                    self::assertSame($expected['path'], $result[$i]['path']);
                }
            } finally {
                @unlink($filePath);
            }
        });
    }

    // ─── Property 5 ──────────────────────────────────────────────────

    /**
     * Property 5: 遗留字段 fail-fast 终止 (scopes field)
     *
     * For any abilities.yaml containing one or more legacy violations (a scopes field
     * on any entry), AbilityRegistry::parse() SHALL throw an exception on the *first*
     * violation encountered and SHALL NOT process any subsequent entries or violations.
     *
     * **Validates: Requirements 4.1, 4.2, 4.5**
     *
     * @group Feature: deprecate-scope, Property 5
     */
    public function testLegacyScopesFieldFailFastOnFirstViolation(): void
    {
        $this->limitTo(100);

        $sectionGenerator = Generators::elements(['rules', 'agents', 'skills', 'hooks']);

        $this->forAll(
            $sectionGenerator,
            Generators::choose(2, 8),
        )->then(function (string $section, int $totalEntries): void {
            $violationIndex = random_int(0, $totalEntries - 1);
            $hasSecondViolation = ($violationIndex < $totalEntries - 1) && (random_int(0, 1) === 1);
            $secondViolationIndex = $hasSecondViolation
                ? random_int($violationIndex + 1, $totalEntries - 1)
                : -1;

            $entries = [];
            for ($i = 0; $i < $totalEntries; $i++) {
                $entryPath = 'entry-' . bin2hex(random_bytes(3)) . '-' . $i;
                $entry = [
                    'path' => $entryPath,
                    'description' => 'Test entry ' . $i,
                    'targets' => ['cursor' => '.cursor/rules/' . $entryPath . '.mdc'],
                ];
                if ($i === $violationIndex || $i === $secondViolationIndex) {
                    $entry['scopes'] = ['project', 'user'];
                }
                $entries[] = $entry;
            }

            // Build YAML
            $yamlLines = [$section . ':'];
            foreach ($entries as $entry) {
                $yamlLines[] = '  - path: ' . $entry['path'];
                $yamlLines[] = '    description: ' . $entry['description'];
                $yamlLines[] = '    targets:';
                foreach ($entry['targets'] as $platform => $target) {
                    $yamlLines[] = '      ' . $platform . ': ' . $target;
                }
                if (isset($entry['scopes'])) {
                    $yamlLines[] = '    scopes:';
                    foreach ($entry['scopes'] as $scope) {
                        $yamlLines[] = '      - ' . $scope;
                    }
                }
            }
            $yaml = implode("\n", $yamlLines) . "\n";

            $filePath = $this->tmpDir . '/p5a-' . bin2hex(random_bytes(4)) . '.yaml';
            file_put_contents($filePath, $yaml);

            try {
                $registry = new AbilityRegistry($filePath);

                try {
                    $registry->parse();
                    self::fail('Expected InvalidScopeException was not thrown');
                } catch (InvalidScopeException $e) {
                    $message = $e->getMessage();
                    $firstViolationPath = $entries[$violationIndex]['path'];
                    self::assertStringContainsString($firstViolationPath, $message);

                    if ($hasSecondViolation) {
                        $secondViolationPath = $entries[$secondViolationIndex]['path'];
                        self::assertStringNotContainsString($secondViolationPath, $message);
                    }
                }
            } finally {
                @unlink($filePath);
            }
        });
    }

    // ─── Property 3 ──────────────────────────────────────────────────

    /**
     * Property 3: Bootstrap 幂等性
     *
     * For any ability in bootstrap.includes that is already installed on disk at
     * the target path, executing bootstrap without --force SHALL skip that ability
     * (output contains [skip]), and executing bootstrap with --force SHALL overwrite it.
     * In both cases the final disk state for that ability SHALL be valid.
     *
     * **Validates: Requirements 3.3, 3.4**
     *
     * @group Feature: deprecate-scope, Property 3
     */
    public function testBootstrapIdempotency(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(1, 5),   // number of abilities
            Generators::bool(),          // force flag
        )->then(function (int $abilityCount, bool $force): void {
            $types = ['skill', 'rule'];
            $abilities = [];
            $preInstalled = [];

            for ($i = 0; $i < $abilityCount; $i++) {
                $type = $types[array_rand($types)];
                $name = 'ab-' . bin2hex(random_bytes(3)) . '-' . $i;
                $abilities[] = ['type' => $type, 'name' => $name];
                // Randomly choose whether this ability is pre-installed
                $preInstalled[] = (bool) random_int(0, 1);
            }

            $pkg = $this->buildPackageForAbilities($abilities);
            $proj = $this->tmpDir . '/proj-' . bin2hex(random_bytes(4));
            mkdir($proj, 0775, true);
            chdir($proj);

            // Pre-install some abilities on disk
            foreach ($abilities as $i => $ability) {
                if ($preInstalled[$i]) {
                    $this->preInstallAbility($proj, $ability['type'], $ability['name']);
                }
            }

            $initializer = new ProjectInitializer($pkg);
            $registry = new AbilityRegistry($pkg . '/abilities.yaml');
            $installer = new Installer(
                registry: $registry,
                gitIgnore: new GitIgnoreTemplateService(),
                packageRoot: $pkg,
                mirror: new DirectoryMirrorService(),
            );

            $cmd = new BootstrapCommand($initializer, $registry, $installer);
            $tester = new CommandTester($cmd);
            $options = ['--target' => ['cursor']];
            if ($force) {
                $options['--force'] = true;
            }
            $exit = $tester->execute($options);
            $display = $tester->getDisplay();

            // All should succeed (exit 0) since all source files exist
            self::assertSame(0, $exit, 'Expected exit 0 but got: ' . $display);

            foreach ($abilities as $i => $ability) {
                $marker = sprintf('[skip] %s:%s', $ability['type'], $ability['name']);
                if ($preInstalled[$i] && !$force) {
                    // Already installed + no force → must be skipped
                    self::assertStringContainsString($marker, $display,
                        "Pre-installed {$ability['type']}:{$ability['name']} should be skipped without --force");
                } elseif ($preInstalled[$i] && $force) {
                    // Already installed + force → overwrite (no [skip])
                    self::assertStringNotContainsString($marker, $display,
                        "Pre-installed {$ability['type']}:{$ability['name']} should be overwritten with --force");
                    self::assertStringContainsString("Installed {$ability['type']} {$ability['name']}", $display);
                } else {
                    // Not pre-installed → installed normally
                    self::assertStringContainsString("Installed {$ability['type']} {$ability['name']}", $display);
                }

                // In all cases, the final disk state for the ability should be valid
                $this->assertAbilityOnDisk($proj, $ability['type'], $ability['name']);
            }
        });
    }

    // ─── Property 4 ──────────────────────────────────────────────────

    /**
     * Property 4: Bootstrap 部分失败容错
     *
     * For any bootstrap.includes list containing N items where item K (1 ≤ K ≤ N)
     * fails installation, all items at positions ≠ K SHALL still be attempted,
     * and the scaffold SHALL remain intact on disk.
     *
     * **Validates: Requirements 3.5, 3.7**
     *
     * @group Feature: deprecate-scope, Property 4
     */
    public function testBootstrapPartialFailureTolerance(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(2, 6),   // total ability count (need at least 2 for partial failure)
        )->then(function (int $totalCount): void {
            $types = ['skill', 'rule'];
            $abilities = [];

            for ($i = 0; $i < $totalCount; $i++) {
                $type = $types[array_rand($types)];
                $name = 'pf-' . bin2hex(random_bytes(3)) . '-' . $i;
                $abilities[] = ['type' => $type, 'name' => $name];
            }

            // Randomly choose which positions will fail (at least 1, at most totalCount-1)
            $failCount = random_int(1, $totalCount - 1);
            $allPositions = range(0, $totalCount - 1);
            shuffle($allPositions);
            $failPositions = array_slice($allPositions, 0, $failCount);

            $pkg = $this->buildPackageForAbilities($abilities, $failPositions);
            $proj = $this->tmpDir . '/proj-' . bin2hex(random_bytes(4));
            mkdir($proj, 0775, true);
            chdir($proj);

            $initializer = new ProjectInitializer($pkg);
            $registry = new AbilityRegistry($pkg . '/abilities.yaml');
            $installer = new Installer(
                registry: $registry,
                gitIgnore: new GitIgnoreTemplateService(),
                packageRoot: $pkg,
                mirror: new DirectoryMirrorService(),
            );

            $cmd = new BootstrapCommand($initializer, $registry, $installer);
            $tester = new CommandTester($cmd);
            $exit = $tester->execute(['--target' => ['cursor']]);
            $display = $tester->getDisplay();

            // Must exit with failure code since at least one item failed
            self::assertSame(1, $exit, 'Expected exit 1 with partial failure but got 0: ' . $display);

            // Failed positions have [fail] marker
            foreach ($failPositions as $pos) {
                $ability = $abilities[$pos];
                self::assertStringContainsString('[fail]', $display,
                    "Expected [fail] for {$ability['type']}:{$ability['name']}");
            }

            // Non-failed positions should be attempted (have [ok] Installed or [skip])
            foreach ($abilities as $i => $ability) {
                if (!in_array($i, $failPositions, true)) {
                    $installMsg = sprintf('Installed %s %s', $ability['type'], $ability['name']);
                    self::assertStringContainsString($installMsg, $display,
                        "Non-failed {$ability['type']}:{$ability['name']} should be attempted");
                }
            }

            // Scaffold remains intact (not rolled back)
            self::assertFileExists($proj . '/docs/README.md', 'Scaffold docs/README.md must survive partial failure');
            self::assertFileExists($proj . '/AGENTS.md', 'Scaffold AGENTS.md must survive partial failure');
        });
    }

    // ─── Property 3/4 Helpers ────────────────────────────────────────

    /**
     * Build a package directory with scaffold files, abilities, and bootstrap.includes.
     *
     * @param list<array{type: string, name: string}> $abilities
     * @param list<int>                               $failPositions Positions where source files should NOT exist (causes install failure)
     */
    private function buildPackageForAbilities(array $abilities, array $failPositions = []): string
    {
        $pkg = $this->tmpDir . '/pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        // Group abilities by section for YAML
        $sections = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];
        $includeLines = [];

        foreach ($abilities as $i => $ability) {
            $type = $ability['type'];
            $name = $ability['name'];
            $section = $type . 's';
            $shouldFail = in_array($i, $failPositions, true);

            if ($type === 'skill') {
                $targetPath = '.cursor/skills/' . $name;
                if (!$shouldFail) {
                    mkdir($pkg . '/' . $targetPath, 0775, true);
                    file_put_contents($pkg . '/' . $targetPath . '/SKILL.md', "# $name content\n");
                }
            } else {
                // rule
                $targetPath = '.cursor/rules/' . $name . '/' . $name . '.mdc';
                if (!$shouldFail) {
                    mkdir($pkg . '/.cursor/rules/' . $name, 0775, true);
                    file_put_contents($pkg . '/' . $targetPath, "# $name content\n");
                }
            }

            $sections[$section][] = [
                'path' => $name,
                'description' => "$name ability",
                'targets' => ['cursor' => $targetPath],
            ];
            $includeLines[] = "    - $type:$name";
        }

        // Build abilities.yaml
        $yamlLines = [];
        foreach (['skills', 'rules', 'agents', 'hooks'] as $sec) {
            if ($sections[$sec] === []) {
                $yamlLines[] = "$sec: []";
            } else {
                $yamlLines[] = "$sec:";
                foreach ($sections[$sec] as $entry) {
                    $yamlLines[] = '  - path: ' . $entry['path'];
                    $yamlLines[] = '    description: ' . $entry['description'];
                    $yamlLines[] = '    targets:';
                    foreach ($entry['targets'] as $platform => $target) {
                        $yamlLines[] = '      ' . $platform . ': ' . $target;
                    }
                }
            }
        }
        $yamlLines[] = 'bootstrap:';
        $yamlLines[] = '  includes:';
        foreach ($includeLines as $line) {
            $yamlLines[] = $line;
        }

        file_put_contents($pkg . '/abilities.yaml', implode("\n", $yamlLines) . "\n");

        return $pkg;
    }

    /**
     * Pre-install an ability on disk at the project's target path.
     */
    private function preInstallAbility(string $proj, string $type, string $name): void
    {
        if ($type === 'skill') {
            $dir = $proj . '/.cursor/skills/' . $name;
            mkdir($dir, 0775, true);
            file_put_contents($dir . '/SKILL.md', "# pre-installed $name\n");
        } else {
            // rule
            $dir = $proj . '/.cursor/rules/' . $name;
            mkdir($dir, 0775, true);
            file_put_contents($dir . '/' . $name . '.mdc', "# pre-installed $name\n");
        }
    }

    /**
     * Assert that an ability's files exist on disk in the project.
     */
    private function assertAbilityOnDisk(string $proj, string $type, string $name): void
    {
        if ($type === 'skill') {
            self::assertDirectoryExists($proj . '/.cursor/skills/' . $name,
                "Skill $name should exist on disk");
        } else {
            // rule
            self::assertFileExists($proj . '/.cursor/rules/' . $name . '/' . $name . '.mdc',
                "Rule $name should exist on disk");
        }
    }

    // ─── Property 5 ──────────────────────────────────────────────────

    /**
     * Property 5 (supplemental): global-setup key fail-fast
     *
     * For any abilities.yaml containing a global-setup top-level key,
     * AbilityRegistry::parse() SHALL throw InvalidScopeException referencing
     * global-setup and NOT process any section entries.
     *
     * **Validates: Requirements 4.2, 4.5**
     *
     * @group Feature: deprecate-scope, Property 5
     */
    public function testLegacyGlobalSetupKeyFailFast(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(0, 5),
        )->then(function (int $entryCount): void {
            $yamlLines = [];
            $yamlLines[] = 'global-setup:';
            $yamlLines[] = '  includes:';
            $yamlLines[] = '    - skill:' . bin2hex(random_bytes(3));

            if ($entryCount > 0) {
                $yamlLines[] = '';
                $yamlLines[] = 'rules:';
                for ($i = 0; $i < $entryCount; $i++) {
                    $entryPath = 'rule-' . bin2hex(random_bytes(3));
                    $yamlLines[] = '  - path: ' . $entryPath;
                    $yamlLines[] = '    description: Test rule ' . $i;
                    $yamlLines[] = '    targets:';
                    $yamlLines[] = '      cursor: .cursor/rules/' . $entryPath . '.mdc';
                }
            }

            $yaml = implode("\n", $yamlLines) . "\n";
            $filePath = $this->tmpDir . '/p5b-' . bin2hex(random_bytes(4)) . '.yaml';
            file_put_contents($filePath, $yaml);

            try {
                $registry = new AbilityRegistry($filePath);

                try {
                    $registry->parse();
                    self::fail('Expected InvalidScopeException was not thrown');
                } catch (InvalidScopeException $e) {
                    $message = $e->getMessage();
                    self::assertStringContainsString('global-setup', $message);
                    self::assertStringContainsString('bootstrap', $message);
                }
            } finally {
                @unlink($filePath);
            }
        });
    }

    // ─── Property 1 ──────────────────────────────────────────────────

    /**
     * Property 1: --scope 参数全面拒绝
     *
     * For any command that uses HandlesDeployScopeOption trait, and for any string value
     * passed as --scope (including "project", "user", empty string, arbitrary text),
     * the command SHALL reject with a deprecation error containing both removal notice
     * and project-only guidance, and SHALL NOT execute any business logic.
     *
     * **Validates: Requirements 1.2**
     *
     * @group Feature: deprecate-scope, Property 1
     */
    public function testScopeOptionRejection(): void
    {
        $this->limitTo(100);

        // Generator: random scope values including known ones and arbitrary strings
        $scopeGenerator = Generators::oneOf(
            Generators::constant('project'),
            Generators::constant('user'),
            Generators::string(),
            Generators::suchThat(
                fn (string $s): bool => $s !== '',
                Generators::string(),
            ),
        );

        $this->forAll($scopeGenerator)->then(function (string $scope): void {
            $commands = $this->buildAllScopeCommands();

            foreach ($commands as $name => $inputFactory) {
                [$command, $baseInput] = $inputFactory;
                $input = array_merge($baseInput, ['--scope' => $scope]);
                $tester = new CommandTester($command);
                $exit = $tester->execute($input);
                $display = $tester->getDisplay();

                self::assertSame(
                    Command::FAILURE,
                    $exit,
                    "Command '$name' with --scope='$scope' should return FAILURE but returned $exit. Output: $display",
                );
                self::assertStringContainsString(
                    '--scope option has been removed',
                    $display,
                    "Command '$name' with --scope='$scope' should mention removal. Output: $display",
                );
                self::assertStringContainsString(
                    'project scope only',
                    $display,
                    "Command '$name' with --scope='$scope' should mention project-only. Output: $display",
                );
            }
        });
    }

    /**
     * Build all 9 commands that use HandlesDeployScopeOption trait.
     *
     * Returns [commandName => [Command, baseInput]] where baseInput provides
     * any required arguments to pass Symfony Console input validation.
     *
     * @return array<string, array{0: Command, 1: array<string, mixed>}>
     */
    private function buildAllScopeCommands(): array
    {
        $registryPath = $this->tmpDir . '/abilities.yaml';
        if (!is_file($registryPath)) {
            file_put_contents($registryPath, "skills: []\nrules: []\nagents: []\nhooks: []\n");
        }

        $registry = new AbilityRegistry($registryPath);
        $rootResolver = new DeployRootResolver();
        $installer = new Installer(
            registry: $registry,
            packageRoot: $this->tmpDir,
        );
        $checker = new CheckService(rootResolver: $rootResolver);
        $presetRegistry = new PresetRegistry($registry);
        $probe = new InstallationProbe($registry, $rootResolver);
        $presenter = new ShowStatusPresenter(
            $registry,
            $checker,
            $probe,
            $this->tmpDir,
            $rootResolver,
        );

        return [
            'install' => [new InstallCommand($installer, $presetRegistry), ['preset' => 'dummy']],
            'show' => [new ShowCommand($presenter), []],
            'skill:install' => [new SkillInstallCommand($installer), []],
            'rule:install' => [new RuleInstallCommand($installer), []],
            'agent:install' => [new AgentInstallCommand($installer), []],
            'skill:uninstall' => [new SkillUninstallCommand($installer, $checker), []],
            'rule:uninstall' => [new RuleUninstallCommand($installer, $checker), []],
            'agent:uninstall' => [new AgentUninstallCommand($installer, $checker), []],
            'preset:uninstall' => [new PresetUninstallCommand($installer, $checker, $presetRegistry), ['preset' => 'dummy']],
        ];
    }
}
