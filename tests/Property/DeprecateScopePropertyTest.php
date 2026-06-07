<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Property;

use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\BootstrapCommand;
use AiProfileManager\Command\CleanupCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\AbilityUpdateService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\ComposerBaselineResolver;
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

    // ─── Property 6 ──────────────────────────────────────────────────

    /**
     * Property 6: Show 输出无 scope 标记
     *
     * For any set of registered abilities (installed or not), the output of show command
     * SHALL contain only lines in format {type}:{name}  {status}   {targets} with no
     * scope labels ((user), (project), (user+project)) and no dual-scope warning text.
     *
     * **Validates: Requirements 5.1, 5.2, 5.3**
     *
     * @group Feature: deprecate-scope, Property 6
     */
    public function testShowOutputHasNoScopeLabels(): void
    {
        $this->limitTo(100);

        $this->forAll(
            Generators::choose(0, 20),
        )->then(function (int $entryCount): void {
            $types = ['skill', 'rule', 'agent', 'hook', 'gitignore', 'prompt'];
            $entries = [];

            for ($i = 0; $i < $entryCount; $i++) {
                $type = $types[array_rand($types)];
                $name = 'p6-' . $type[0] . bin2hex(random_bytes(3)) . '-' . $i;
                $entries[] = ['type' => $type, 'name' => $name];
            }

            $pkg = $this->buildShowPackage($entries);
            $proj = $this->tmpDir . '/proj6-' . bin2hex(random_bytes(4));
            mkdir($proj, 0775, true);

            // Install some abilities on disk to get varied statuses
            foreach ($entries as $entry) {
                if (random_int(0, 1) === 1) {
                    $this->preInstallShowAbility($proj, $entry['type'], $entry['name']);
                }
            }

            $registryPath = $pkg . '/abilities.yaml';
            $registry = new AbilityRegistry($registryPath);
            $rootResolver = new DeployRootResolver();
            $presenter = new ShowStatusPresenter(
                $registry,
                new CheckService(rootResolver: $rootResolver),
                new InstallationProbe($registry, $rootResolver),
                $pkg,
                $rootResolver,
            );

            $oldCwd = (string) getcwd();
            chdir($proj);
            try {
                $lines = $presenter->lines(['cursor'], null);
            } finally {
                chdir($oldCwd);
            }

            $joined = implode("\n", $lines);

            // No scope labels
            self::assertStringNotContainsString('(user)', $joined,
                'Output must not contain (user) scope label');
            self::assertStringNotContainsString('(project)', $joined,
                'Output must not contain (project) scope label');
            self::assertStringNotContainsString('(user+project)', $joined,
                'Output must not contain (user+project) scope label');

            // No dual-scope warning text
            self::assertStringNotContainsString('[warn]', $joined,
                'Output must not contain [warn] dual-scope warning');
            self::assertStringNotContainsString('installed in both', $joined,
                'Output must not contain "installed in both" dual-scope text');

            // Every line matches the expected format
            foreach ($lines as $line) {
                self::assertMatchesRegularExpression(
                    '/^(skill|rule|agent|hook|gitignore|prompt):.+  (not installed|installed|installed with local change)   .+$/',
                    $line,
                    "Line does not match expected format: $line",
                );
            }
        });
    }

    // ─── Property 7 ──────────────────────────────────────────────────

    /**
     * Property 7: Show type 过滤器正确性
     *
     * For any valid --type filter value and for any registry content, all lines in
     * show output SHALL have a type prefix matching the filter value, and no lines
     * with a different type SHALL appear.
     *
     * **Validates: Requirements 5.4**
     *
     * @group Feature: deprecate-scope, Property 7
     */
    public function testShowTypeFilterCorrectness(): void
    {
        $this->limitTo(100);

        $knownTypes = ['skill', 'rule', 'agent', 'hook', 'gitignore', 'prompt'];
        $typeGenerator = Generators::elements($knownTypes);

        $this->forAll(
            $typeGenerator,
            Generators::choose(1, 15),
        )->then(function (string $filterType, int $entryCount) use ($knownTypes): void {
            $entries = [];

            // Generate a mix of types to ensure filter actually needs to work
            for ($i = 0; $i < $entryCount; $i++) {
                $type = $knownTypes[array_rand($knownTypes)];
                $name = 'p7-' . $type[0] . bin2hex(random_bytes(3)) . '-' . $i;
                $entries[] = ['type' => $type, 'name' => $name];
            }

            $pkg = $this->buildShowPackage($entries);
            $proj = $this->tmpDir . '/proj7-' . bin2hex(random_bytes(4));
            mkdir($proj, 0775, true);

            // Pre-install some to get output lines
            foreach ($entries as $entry) {
                $this->preInstallShowAbility($proj, $entry['type'], $entry['name']);
            }

            $registryPath = $pkg . '/abilities.yaml';
            $registry = new AbilityRegistry($registryPath);
            $rootResolver = new DeployRootResolver();
            $presenter = new ShowStatusPresenter(
                $registry,
                new CheckService(rootResolver: $rootResolver),
                new InstallationProbe($registry, $rootResolver),
                $pkg,
                $rootResolver,
            );

            $oldCwd = (string) getcwd();
            chdir($proj);
            try {
                $lines = $presenter->lines(['cursor'], $filterType);
            } finally {
                chdir($oldCwd);
            }

            // All output lines must start with the filter type prefix
            foreach ($lines as $line) {
                self::assertStringStartsWith(
                    $filterType . ':',
                    $line,
                    "Line should start with '$filterType:' when filtered, got: $line",
                );
            }

            // No lines with a different type should appear
            foreach ($knownTypes as $otherType) {
                if ($otherType === $filterType) {
                    continue;
                }
                foreach ($lines as $line) {
                    self::assertStringStartsNotWith(
                        $otherType . ':',
                        $line,
                        "Line should NOT start with '$otherType:' when filter is '$filterType', got: $line",
                    );
                }
            }
        });
    }

    // ─── Property 6/7 Helpers ────────────────────────────────────────

    /**
     * Build a package directory with abilities.yaml for show PBT tests.
     *
     * @param list<array{type: string, name: string}> $entries
     */
    private function buildShowPackage(array $entries): string
    {
        $pkg = $this->tmpDir . '/show-pkg-' . bin2hex(random_bytes(4));
        mkdir($pkg, 0775, true);

        // Group entries by YAML section
        $sections = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];
        $gitignoreEntries = [];
        $promptEntries = [];

        foreach ($entries as $entry) {
            $type = $entry['type'];
            $name = $entry['name'];

            if ($type === 'gitignore') {
                $gitignoreEntries[] = $name;
                // Create a .gitignore template file in package
                file_put_contents(
                    $pkg . '/.gitignore',
                    ($pkg . '/.gitignore' !== '' && is_file($pkg . '/.gitignore')
                        ? (string) file_get_contents($pkg . '/.gitignore')
                        : '')
                    . "## @apm:block ability=$name target=*\n/$name/\n## @apm:end\n",
                );
            } elseif ($type === 'prompt') {
                $promptEntries[] = $name;
            } else {
                $section = $type . 's';
                $targetPath = $this->buildShowSourceFile($pkg, $type, $name);
                $sections[$section][] = [
                    'path' => $name,
                    'description' => "$name ability",
                    'targets' => ['cursor' => $targetPath],
                ];
            }
        }

        // Build YAML
        $yamlLines = ['version: "1"'];
        foreach (['skills', 'rules', 'agents', 'hooks'] as $sec) {
            if ($sections[$sec] === []) {
                $yamlLines[] = "$sec: []";
            } else {
                $yamlLines[] = "$sec:";
                foreach ($sections[$sec] as $e) {
                    $yamlLines[] = '  - path: ' . $e['path'];
                    $yamlLines[] = '    description: ' . $e['description'];
                    $yamlLines[] = '    targets:';
                    foreach ($e['targets'] as $platform => $target) {
                        $yamlLines[] = '      ' . $platform . ': ' . $target;
                    }
                }
            }
        }

        if ($gitignoreEntries !== []) {
            $yamlLines[] = 'gitignore:';
            foreach ($gitignoreEntries as $marker) {
                $yamlLines[] = '  - marker: ' . $marker;
                $yamlLines[] = '    description: ' . $marker . ' gitignore';
            }
        } else {
            $yamlLines[] = 'gitignore: []';
        }

        if ($promptEntries !== []) {
            $yamlLines[] = 'prompts:';
            foreach ($promptEntries as $pName) {
                $yamlLines[] = '  - name: ' . $pName;
                $yamlLines[] = '    message: Hello from ' . $pName;
            }
        } else {
            $yamlLines[] = 'prompts: []';
        }

        file_put_contents($pkg . '/abilities.yaml', implode("\n", $yamlLines) . "\n");

        return $pkg;
    }

    /**
     * Build source file for a given ability type in the package.
     * Returns the relative target path used in abilities.yaml.
     */
    private function buildShowSourceFile(string $pkg, string $type, string $name): string
    {
        return match ($type) {
            'skill' => $this->buildShowSkillSource($pkg, $name),
            'rule' => $this->buildShowRuleSource($pkg, $name),
            'agent' => $this->buildShowAgentSource($pkg, $name),
            'hook' => $this->buildShowHookSource($pkg, $name),
            default => throw new \InvalidArgumentException("Unknown type: $type"),
        };
    }

    private function buildShowSkillSource(string $pkg, string $name): string
    {
        $dir = $pkg . '/.cursor/skills/' . $name;
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/SKILL.md', "# $name skill\n");

        return '.cursor/skills/' . $name . '/';
    }

    private function buildShowRuleSource(string $pkg, string $name): string
    {
        $dir = $pkg . '/.cursor/rules/' . $name;
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/' . $name . '.mdc', "# $name rule\n");

        return '.cursor/rules/' . $name . '/' . $name . '.mdc';
    }

    private function buildShowAgentSource(string $pkg, string $name): string
    {
        $dir = $pkg . '/.cursor/agents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.md', "# $name agent\n");

        return '.cursor/agents/' . $name . '.md';
    }

    private function buildShowHookSource(string $pkg, string $name): string
    {
        $dir = $pkg . '/.cursor/hooks/' . $name;
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/' . $name . '.json', '{}');

        return '.cursor/hooks/' . $name . '/';
    }

    /**
     * Pre-install an ability in the project workspace for show tests.
     */
    private function preInstallShowAbility(string $proj, string $type, string $name): void
    {
        match ($type) {
            'skill' => $this->preInstallShowSkill($proj, $name),
            'rule' => $this->preInstallShowRule($proj, $name),
            'agent' => $this->preInstallShowAgent($proj, $name),
            'hook' => $this->preInstallShowHook($proj, $name),
            'gitignore' => $this->preInstallShowGitignore($proj, $name),
            'prompt' => null, // prompts are not "installed" on disk
        };
    }

    private function preInstallShowSkill(string $proj, string $name): void
    {
        $dir = $proj . '/.cursor/skills/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/SKILL.md', "# $name skill\n");
    }

    private function preInstallShowRule(string $proj, string $name): void
    {
        $dir = $proj . '/.cursor/rules/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.mdc', "# $name rule\n");
    }

    private function preInstallShowAgent(string $proj, string $name): void
    {
        $dir = $proj . '/.cursor/agents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.md', "# $name agent\n");
    }

    private function preInstallShowHook(string $proj, string $name): void
    {
        $dir = $proj . '/.cursor/hooks/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name . '.json', '{}');
    }

    private function preInstallShowGitignore(string $proj, string $name): void
    {
        $content = "# BEGIN apm-managed-gitignore v1\n/$name/\n# END apm-managed-gitignore v1\n";
        $path = $proj . '/.gitignore';
        if (is_file($path)) {
            file_put_contents($path, (string) file_get_contents($path) . $content);
        } else {
            file_put_contents($path, $content);
        }
    }



    // ─── Property 1 Helpers ──────────────────────────────────────────

    // ─── Property 8 ──────────────────────────────────────────────────

    /**
     * Property 8: Update 仅遍历 project scope
     *
     * For any set of installed abilities, AbilityUpdateService::reportChanges() SHALL only
     * check abilities present in project scope (via DeployRootResolver::resolve() as workspace root),
     * and SHALL NOT attempt to resolve or check user home directory paths.
     *
     * **Validates: Requirements 6, AC 1-4**
     *
     * @group Feature: deprecate-scope, Property 8
     */
    public function testUpdateOnlyTraversesProjectScope(): void
    {
        $this->limitTo(100);

        $typeGenerator = Generators::elements(['skill', 'rule']);

        $this->forAll(
            Generators::choose(1, 5),
        )->then(function (int $abilityCount) use ($typeGenerator): void {
            // Generate random abilities
            $abilities = [];
            for ($i = 0; $i < $abilityCount; $i++) {
                $type = $this->sample($typeGenerator, 1)->collected()[0];
                $name = 'p8-' . $type[0] . bin2hex(random_bytes(3)) . '-' . $i;
                $abilities[] = ['type' => $type, 'name' => $name];
            }

            // Randomly decide which abilities are installed in project scope and which only in user home
            $inProject = [];
            $inUserOnly = [];
            foreach ($abilities as $ability) {
                if (random_int(0, 1) === 1) {
                    $inProject[] = $ability;
                } else {
                    $inUserOnly[] = $ability;
                }
            }

            // Set up baseline
            $baseline = $this->tmpDir . '/p8-bl-' . bin2hex(random_bytes(4));
            $workspace = $this->tmpDir . '/p8-ws-' . bin2hex(random_bytes(4));
            $userHome = $this->tmpDir . '/p8-home-' . bin2hex(random_bytes(4));
            mkdir($baseline, 0775, true);
            mkdir($workspace, 0775, true);
            mkdir($userHome, 0775, true);

            // Build abilities.yaml and baseline source files
            $this->buildUpdateBaseline($baseline, $abilities);

            // Install abilities in project scope (from baseline — unchanged)
            foreach ($inProject as $ability) {
                $this->installAbilityFromBaseline($baseline, $workspace, $ability['type'], $ability['name']);
            }

            // Install abilities in user home (with drift to provoke detection if traversed)
            foreach ($inUserOnly as $ability) {
                $this->installAbilityWithDrift($userHome, $ability['type'], $ability['name']);
            }

            // Also install in project scope with drift for those that are in project
            // to ensure we get some output (but only project items should show up)
            foreach ($inProject as $ability) {
                $this->modifyInstalledAbility($workspace, $ability['type'], $ability['name']);
            }

            $oldCwd = (string) getcwd();
            $oldEnvBaseline = getenv('APM_BASELINE_ROOT');
            $oldEnvHome = getenv('HOME');

            putenv('APM_BASELINE_ROOT=' . $baseline);
            putenv('HOME=' . $userHome);
            chdir($workspace);

            try {
                $registry = new AbilityRegistry($baseline . '/abilities.yaml');
                $resolver = new DeployRootResolver();
                $service = new AbilityUpdateService(
                    $registry,
                    new ComposerBaselineResolver(overrideInstallPath: $baseline),
                    new CheckService(new ComposerBaselineResolver(overrideInstallPath: $baseline)),
                    new InstallationProbe($registry, $resolver),
                    $resolver,
                    new DirectoryMirrorService(),
                );

                $result = $service->reportChanges(false);
                $output = implode("\n", $result['lines']);

                // Verify: output must NOT contain user home path
                self::assertStringNotContainsString($userHome, $output,
                    'Output must not reference user home directory');

                // Verify: output must NOT contain "(user)" scope label
                self::assertStringNotContainsString('(user)', $output,
                    'Output must not contain (user) scope label');

                // Verify: any "changed:" lines must only reference abilities installed in project scope
                foreach ($result['lines'] as $line) {
                    if (!str_starts_with($line, 'changed:')) {
                        continue;
                    }
                    // Extract the name from the line: "changed: {type}:{name} {target}"
                    if (preg_match('/^changed: (\w+):(\S+) (\S+)$/', $line, $m)) {
                        $detectedName = $m[2];
                        $projectNames = array_map(fn (array $a) => $a['name'], $inProject);
                        self::assertContains($detectedName, $projectNames,
                            "Detected change '$detectedName' is not in project scope, but appeared in output");
                    }
                }

                // User-only abilities must NOT appear in the output
                foreach ($inUserOnly as $ability) {
                    self::assertStringNotContainsString($ability['name'], $output,
                        "User-only ability '{$ability['name']}' should not appear in output");
                }
            } finally {
                chdir($oldCwd);
                if ($oldEnvBaseline === false) {
                    putenv('APM_BASELINE_ROOT');
                } else {
                    putenv('APM_BASELINE_ROOT=' . $oldEnvBaseline);
                }
                if ($oldEnvHome === false) {
                    putenv('HOME');
                } else {
                    putenv('HOME=' . $oldEnvHome);
                }
            }
        });
    }

    // ─── Property 9 ──────────────────────────────────────────────────

    /**
     * Property 9: Update 输出格式无 scope 标签
     *
     * For any ability detected as changed by update, the output line SHALL match format
     * `changed: {type}:{name} {target}` without any parenthesized scope label.
     *
     * **Validates: Requirements 6, AC 1-4**
     *
     * @group Feature: deprecate-scope, Property 9
     */
    public function testUpdateOutputFormatNoScopeLabel(): void
    {
        $this->limitTo(100);

        $typeGenerator = Generators::elements(['skill', 'rule']);

        $this->forAll(
            Generators::choose(1, 5),
        )->then(function (int $abilityCount) use ($typeGenerator): void {
            // Generate random abilities
            $abilities = [];
            for ($i = 0; $i < $abilityCount; $i++) {
                $type = $this->sample($typeGenerator, 1)->collected()[0];
                $name = 'p9-' . $type[0] . bin2hex(random_bytes(3)) . '-' . $i;
                $abilities[] = ['type' => $type, 'name' => $name];
            }

            // Set up baseline and workspace
            $baseline = $this->tmpDir . '/p9-bl-' . bin2hex(random_bytes(4));
            $workspace = $this->tmpDir . '/p9-ws-' . bin2hex(random_bytes(4));
            mkdir($baseline, 0775, true);
            mkdir($workspace, 0775, true);

            // Build abilities.yaml and baseline source files
            $this->buildUpdateBaseline($baseline, $abilities);

            // Install all abilities in project scope then modify to create drift
            foreach ($abilities as $ability) {
                $this->installAbilityFromBaseline($baseline, $workspace, $ability['type'], $ability['name']);
                $this->modifyInstalledAbility($workspace, $ability['type'], $ability['name']);
            }

            $oldCwd = (string) getcwd();
            $oldEnvBaseline = getenv('APM_BASELINE_ROOT');

            putenv('APM_BASELINE_ROOT=' . $baseline);
            chdir($workspace);

            try {
                $registry = new AbilityRegistry($baseline . '/abilities.yaml');
                $resolver = new DeployRootResolver();
                $service = new AbilityUpdateService(
                    $registry,
                    new ComposerBaselineResolver(overrideInstallPath: $baseline),
                    new CheckService(new ComposerBaselineResolver(overrideInstallPath: $baseline)),
                    new InstallationProbe($registry, $resolver),
                    $resolver,
                    new DirectoryMirrorService(),
                );

                $result = $service->reportChanges(false);

                // Every line starting with "changed:" must match the expected format
                $changedLines = array_filter(
                    $result['lines'],
                    static fn (string $line): bool => str_starts_with($line, 'changed:'),
                );

                // We expect at least one changed line since all abilities have drift
                self::assertNotEmpty($changedLines, 'Expected at least one changed line');

                foreach ($changedLines as $line) {
                    // Must match: changed: {type}:{name} {target}
                    self::assertMatchesRegularExpression(
                        '/^changed: \w+:\S+ \S+$/',
                        $line,
                        "Line does not match expected format: $line",
                    );

                    // Must NOT contain any parenthesized scope label
                    self::assertDoesNotMatchRegularExpression(
                        '/\(project\)|\(user\)|\(user\+project\)/',
                        $line,
                        "Line contains scope label: $line",
                    );
                }
            } finally {
                chdir($oldCwd);
                if ($oldEnvBaseline === false) {
                    putenv('APM_BASELINE_ROOT');
                } else {
                    putenv('APM_BASELINE_ROOT=' . $oldEnvBaseline);
                }
            }
        });
    }

    // ─── Property 8/9 Helpers ────────────────────────────────────────

    /**
     * Build a baseline directory with abilities.yaml and source files for update tests.
     *
     * @param list<array{type: string, name: string}> $abilities
     */
    private function buildUpdateBaseline(string $baseline, array $abilities): void
    {
        $sections = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];

        foreach ($abilities as $ability) {
            $type = $ability['type'];
            $name = $ability['name'];
            $section = $type . 's';

            if ($type === 'skill') {
                $targetPath = '.cursor/skills/' . $name;
                mkdir($baseline . '/' . $targetPath, 0775, true);
                file_put_contents($baseline . '/' . $targetPath . '/SKILL.md', "# Baseline $name\n");
            } else {
                // rule
                $targetPath = '.cursor/rules/' . $name . '/' . $name . '.mdc';
                mkdir($baseline . '/.cursor/rules/' . $name, 0775, true);
                file_put_contents($baseline . '/' . $targetPath, "# Baseline $name rule\n");
            }

            $sections[$section][] = [
                'path' => $name,
                'description' => "$name ability",
                'targets' => ['cursor' => $targetPath],
            ];
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

        file_put_contents($baseline . '/abilities.yaml', implode("\n", $yamlLines) . "\n");
    }

    /**
     * Install an ability from baseline to workspace (unchanged copy).
     */
    private function installAbilityFromBaseline(string $baseline, string $workspace, string $type, string $name): void
    {
        if ($type === 'skill') {
            $dir = $workspace . '/.cursor/skills/' . $name;
            mkdir($dir, 0775, true);
            copy(
                $baseline . '/.cursor/skills/' . $name . '/SKILL.md',
                $dir . '/SKILL.md',
            );
        } else {
            // rule
            $dir = $workspace . '/.cursor/rules/' . $name;
            mkdir($dir, 0775, true);
            copy(
                $baseline . '/.cursor/rules/' . $name . '/' . $name . '.mdc',
                $dir . '/' . $name . '.mdc',
            );
        }
    }

    /**
     * Modify an installed ability to create drift (content differs from baseline).
     */
    private function modifyInstalledAbility(string $workspace, string $type, string $name): void
    {
        if ($type === 'skill') {
            $path = $workspace . '/.cursor/skills/' . $name . '/SKILL.md';
            file_put_contents($path, "# Local edit $name\n");
        } else {
            // rule
            $path = $workspace . '/.cursor/rules/' . $name . '/' . $name . '.mdc';
            file_put_contents($path, "# Local edit $name\n");
        }
    }

    /**
     * Install an ability in user home with drift (different content from baseline).
     */
    private function installAbilityWithDrift(string $userHome, string $type, string $name): void
    {
        if ($type === 'skill') {
            $dir = $userHome . '/.cursor/skills/' . $name;
            mkdir($dir, 0775, true);
            file_put_contents($dir . '/SKILL.md', "# User drift $name\n");
        } else {
            // rule
            $dir = $userHome . '/.cursor/rules/' . $name;
            mkdir($dir, 0775, true);
            file_put_contents($dir . '/' . $name . '.mdc', "# User drift $name\n");
        }
    }

    // ─── Property 1 Helpers ──────────────────────────────────────────

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

    // ─── Property 10 ─────────────────────────────────────────────────

    /**
     * Property 10: Cleanup 输出无遗留用语
     *
     * For any execution of `cleanup` command (success or failure), the complete
     * output SHALL NOT contain the strings "user-scope", "user scope", or "global-setup".
     *
     * **Validates: Requirements 7**
     *
     * @group Feature: deprecate-scope, Property 10
     */
    public function testCleanupOutputHasNoLegacyWording(): void
    {
        $this->limitTo(100);

        $typeGenerator = Generators::elements(['skill', 'rule']);

        $this->forAll(
            Generators::choose(1, 5),
            Generators::elements([true, false]),
        )->then(function (int $abilityCount, bool $isFailureScenario) use ($typeGenerator): void {
            // Generate random abilities
            $abilities = [];
            for ($i = 0; $i < $abilityCount; $i++) {
                $type = $this->sample($typeGenerator, 1)->collected()[0];
                $name = 'p10-' . $type[0] . bin2hex(random_bytes(3)) . '-' . $i;
                $abilities[] = ['type' => $type, 'name' => $name];
            }

            // Set up package root with abilities.yaml
            $pkg = $this->tmpDir . '/p10-pkg-' . bin2hex(random_bytes(4));
            $workspace = $this->tmpDir . '/p10-ws-' . bin2hex(random_bytes(4));
            mkdir($pkg, 0775, true);
            mkdir($workspace, 0775, true);

            // Build abilities.yaml with skill/rule entries
            $sections = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];

            foreach ($abilities as $ability) {
                $type = $ability['type'];
                $name = $ability['name'];
                $section = $type . 's';

                if ($type === 'skill') {
                    $targetPath = '.cursor/skills/' . $name;
                    mkdir($pkg . '/' . $targetPath, 0775, true);
                    file_put_contents($pkg . '/' . $targetPath . '/SKILL.md', "# $name\n");
                } else {
                    $targetPath = '.cursor/rules/' . $name . '/' . $name . '.mdc';
                    mkdir($pkg . '/.cursor/rules/' . $name, 0775, true);
                    file_put_contents($pkg . '/' . $targetPath, "# $name rule\n");
                }

                $sections[$section][] = [
                    'path' => $name,
                    'description' => "$name ability",
                    'targets' => ['cursor' => $targetPath],
                ];
            }

            // For failure scenario, add a hook entry pointing to a read-only dir
            if ($isFailureScenario) {
                $hookName = 'p10-hook-' . bin2hex(random_bytes(3));
                $hookTargetPath = '.cursor/hooks/' . $hookName;
                mkdir($pkg . '/' . $hookTargetPath, 0775, true);
                file_put_contents($pkg . '/' . $hookTargetPath . '/hook.json', '{}');
                $sections['hooks'][] = [
                    'path' => $hookName,
                    'description' => "$hookName hook",
                    'targets' => ['cursor' => $hookTargetPath],
                ];

                // Install hook in workspace then make parent directory read-only
                $hookDir = $workspace . '/.cursor/hooks/' . $hookName;
                mkdir($hookDir, 0775, true);
                file_put_contents($hookDir . '/hook.json', '{}');
                chmod($workspace . '/.cursor/hooks', 0555);
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

            file_put_contents($pkg . '/abilities.yaml', implode("\n", $yamlLines) . "\n");

            // Install abilities in workspace (so cleanup has something to uninstall)
            foreach ($abilities as $ability) {
                $this->installAbilityFromBaseline($pkg, $workspace, $ability['type'], $ability['name']);
            }

            $oldCwd = (string) getcwd();
            chdir($workspace);

            try {
                $registry = new AbilityRegistry($pkg . '/abilities.yaml');
                $installer = new Installer(
                    registry: $registry,
                    packageRoot: $pkg,
                );

                $cmd = new CleanupCommand($installer);
                $tester = new CommandTester($cmd);

                // Suppress PHP warnings from chmod-induced rmdir failures
                set_error_handler(static fn (): bool => true);
                try {
                    $tester->execute([]);
                } finally {
                    restore_error_handler();
                }

                $output = $tester->getDisplay();

                // Assert no legacy wording in complete output
                self::assertStringNotContainsString(
                    'user-scope',
                    $output,
                    "Cleanup output must not contain 'user-scope'. Got: $output",
                );
                self::assertStringNotContainsString(
                    'user scope',
                    $output,
                    "Cleanup output must not contain 'user scope'. Got: $output",
                );
                self::assertStringNotContainsString(
                    'global-setup',
                    $output,
                    "Cleanup output must not contain 'global-setup'. Got: $output",
                );
            } finally {
                chdir($oldCwd);
                // Restore permissions for cleanup
                if ($isFailureScenario && is_dir($workspace . '/.cursor/hooks')) {
                    chmod($workspace . '/.cursor/hooks', 0775);
                }
            }
        });
    }
}
