<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityDiffService;
use AiProfileManager\Service\AbilityDirectoryDiff;
use AiProfileManager\Service\AbilityRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for rule diff resolution in AbilityDiffService using targets-based path model.
 */
final class AbilityDiffRuleResolutionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-diff-rule-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testDiffRuleResolvesFromTargetsOnCursor(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/.cursor/rules/spec', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/spec/my-rule.mdc', "base\n");

        mkdir($workspace . '/.cursor/rules/spec', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/spec/my-rule.mdc', "base\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'spec:my-rule', 'description' => 'test', 'targets' => ['cursor' => '.cursor/rules/spec/my-rule.mdc']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['spec:my-rule'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('rule', $results[0]['type']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleResolvesFromTargetsOnKiro(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/.kiro/steering/doc', 0775, true);
        file_put_contents($baseline . '/.kiro/steering/doc/writing-conventions.md', "content\n");

        mkdir($workspace . '/.kiro/steering/doc', 0775, true);
        file_put_contents($workspace . '/.kiro/steering/doc/writing-conventions.md', "content\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'doc:writing-conventions', 'description' => 'test', 'targets' => ['kiro' => '.kiro/steering/doc/writing-conventions.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['doc:writing-conventions'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleDetectsModifiedContent(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/demo.mdc', "original\n");

        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/demo.mdc', "modified\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'git:demo', 'description' => 'test', 'targets' => ['cursor' => '.cursor/rules/git/demo.mdc']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['git:demo'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('modified', $results[0]['status']);
    }

    public function testDiffRuleSkipsWhenTargetNotInEntry(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true);
        mkdir($workspace, 0775, true);

        // Entry only has kiro target, request cursor
        $svc = $this->createService([
            'rules' => [
                ['path' => 'kiro-scope', 'description' => 'test', 'targets' => ['kiro' => '.kiro/steering/kiro-scope.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['kiro-scope'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(0, $results);
    }

    public function testDiffRuleSkipsWhenEntryNotInRegistry(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true);
        mkdir($workspace . '/.cursor/rules', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/orphan.mdc', "x\n");

        $svc = $this->createService(['rules' => []]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['orphan'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(0, $results);
    }

    public function testDiffRuleWithMultiPlatformTargets(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/.cursor/rules/doc', 0775, true);
        mkdir($baseline . '/.kiro/steering/doc', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/doc/writing-conventions.mdc', "content\n");
        file_put_contents($baseline . '/.kiro/steering/doc/writing-conventions.md', "content\n");

        mkdir($workspace . '/.cursor/rules/doc', 0775, true);
        mkdir($workspace . '/.kiro/steering/doc', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/doc/writing-conventions.mdc', "content\n");
        file_put_contents($workspace . '/.kiro/steering/doc/writing-conventions.md', "content\n");

        $svc = $this->createService([
            'rules' => [
                [
                    'path' => 'doc:writing-conventions',
                    'description' => 'test',
                    'targets' => [
                        'cursor' => '.cursor/rules/doc/writing-conventions.mdc',
                        'kiro' => '.kiro/steering/doc/writing-conventions.md',
                    ],
                ],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['doc:writing-conventions'], 'agents' => []],
            ['cursor', 'kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(2, $results);
        self::assertSame('cursor', $results[0]['target']);
        self::assertSame('unchanged', $results[0]['status']);
        self::assertSame('kiro', $results[1]['target']);
        self::assertSame('unchanged', $results[1]['status']);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $sections
     */
    private function createService(array $sections): AbilityDiffService
    {
        $yaml = "version: \"1\"\n";
        foreach (['rules', 'agents', 'skills', 'hooks'] as $section) {
            $entries = $sections[$section] ?? [];
            if ($entries === []) {
                $yaml .= "{$section}: []\n";
                continue;
            }
            $yaml .= "{$section}:\n";
            foreach ($entries as $entry) {
                $yaml .= "  - path: {$entry['path']}\n";
                $yaml .= "    description: {$entry['description']}\n";
                $yaml .= "    targets:\n";
                foreach ($entry['targets'] as $platform => $path) {
                    $yaml .= "      {$platform}: {$path}\n";
                }
            }
        }

        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, $yaml);

        return new AbilityDiffService(
            new AbilityDirectoryDiff(),
            new AbilityRegistry($registryPath),
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
