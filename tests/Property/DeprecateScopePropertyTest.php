<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Property;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

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
        $pathSegmentGenerator = Generators::suchThat(
            fn (string $s): bool => preg_match('/^[a-z][a-z0-9]{2,7}$/', $s) === 1,
            Generators::string(),
            1000,
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
}
