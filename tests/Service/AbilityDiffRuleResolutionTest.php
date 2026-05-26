<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityDiffService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for rule resolution paths in AbilityDiffService (resolveInstalledRuleRelativePath,
 * resolveRuleRelativePath, pickPreferredRuleSourcePath).
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

    public function testDiffRuleResolvesInstalledRuleInSubdirectory(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        // Baseline has rule in abilities/rules/spec/
        mkdir($baseline . '/abilities/rules/spec', 0775, true);
        file_put_contents($baseline . '/abilities/rules/spec/my-rule.cursor.mdc', "base\n");

        // Workspace has rule installed at .cursor/rules/spec/my-rule.mdc
        mkdir($workspace . '/.cursor/rules/spec', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/spec/my-rule.mdc', "base\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['my-rule'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('rule', $results[0]['type']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleResolvesInstalledRuleOnKiro(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/abilities/rules/doc', 0775, true);
        file_put_contents($baseline . '/abilities/rules/doc/writing.kiro.md', "content\n");

        mkdir($workspace . '/.kiro/steering/doc', 0775, true);
        file_put_contents($workspace . '/.kiro/steering/doc/writing.md', "content\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['writing'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleWithMultipleBaselineSuffixesPicksPreferred(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        // Baseline has both .cursor.mdc and .cursor.md
        mkdir($baseline . '/abilities/rules/git', 0775, true);
        file_put_contents($baseline . '/abilities/rules/git/demo.cursor.mdc', "preferred\n");
        file_put_contents($baseline . '/abilities/rules/git/demo.cursor.md', "fallback\n");

        // Workspace has the installed version
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/demo.mdc', "preferred\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['demo'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        // Should be unchanged because it matches the preferred (.cursor.mdc) source
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleWithMultipleKiroSuffixesPicksPreferred(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/abilities/rules/spec', 0775, true);
        file_put_contents($baseline . '/abilities/rules/spec/demo.kiro.md', "preferred\n");
        file_put_contents($baseline . '/abilities/rules/spec/demo.kiro.mdc', "fallback\n");

        mkdir($workspace . '/.kiro/steering/spec', 0775, true);
        file_put_contents($workspace . '/.kiro/steering/spec/demo.md', "preferred\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['demo'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffForCaptureRuleResolvesFromAbilitiesDir(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';

        mkdir($baseline . '/abilities/rules/safety', 0775, true);
        file_put_contents($baseline . '/abilities/rules/safety/cmd.cursor.mdc', "base\n");

        mkdir($workspace . '/abilities/rules/safety', 0775, true);
        file_put_contents($workspace . '/abilities/rules/safety/cmd.cursor.mdc', "modified\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForCapture(
            ['skills' => [], 'rules' => ['cmd'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('modified', $results[0]['status']);
        self::assertNotEmpty($results[0]['files']);
    }

    public function testDiffRuleReturnsUnknownWhenBaselineHasNoRulesDir(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities', 0775, true);
        mkdir($workspace . '/.cursor/rules', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/orphan.mdc', "x\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['orphan'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('unknown', $results[0]['status']);
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
