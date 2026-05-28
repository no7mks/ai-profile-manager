<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityDiffService;
use PHPUnit\Framework\TestCase;

final class AbilityDiffServiceExtendedTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-diff-ext-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testDiffAgentDetectsUnchanged(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($baseline . '/abilities/agents/reviewer.kiro.md', "content\n");
        file_put_contents($workspace . '/.kiro/agents/reviewer.md', "content\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('agent', $results[0]['type']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffAgentDetectsModified(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/abilities/agents/reviewer.cursor.md', "original\n");
        file_put_contents($workspace . '/.cursor/agents/reviewer.md', "modified\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('modified', $results[0]['status']);
    }

    public function testDiffAgentDetectsMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/abilities/agents/reviewer.cursor.md', "content\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testDiffAgentReturnsUnknownWhenBaselineMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($workspace . '/.cursor/agents/unknown-agent.md', "x\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['unknown-agent']],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('unknown', $results[0]['status']);
    }

    public function testDiffRuleDetectsUnchangedOnCursor(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/abilities/rules/git/branch-overview.cursor.mdc', "rule\n");
        file_put_contents($workspace . '/.cursor/rules/git/branch-overview.mdc', "rule\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['branch-overview'], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('rule', $results[0]['type']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testDiffRuleDetectsModifiedOnKiro(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/rules/spec', 0775, true);
        mkdir($workspace . '/.kiro/steering/spec', 0775, true);
        file_put_contents($baseline . '/abilities/rules/spec/spec-goal.kiro.md', "original\n");
        file_put_contents($workspace . '/.kiro/steering/spec/spec-goal.md', "modified\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['spec-goal'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('modified', $results[0]['status']);
    }

    public function testDiffRuleDetectsMissingOnKiro(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/rules/spec', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/abilities/rules/spec/spec-goal.kiro.md', "content\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['spec-goal'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testHashFilesProducesConsistentHash(): void
    {
        $svc = new AbilityDiffService();
        $files = [
            ['path' => 'b.txt', 'content' => 'hello'],
            ['path' => 'a.txt', 'content' => 'world'],
        ];
        $hash1 = $svc->hashFiles($files);
        $files2 = array_reverse($files);
        $hash2 = $svc->hashFiles($files2);

        self::assertSame($hash1, $hash2);
    }

    public function testHashFilesHandlesDeletedFlag(): void
    {
        $svc = new AbilityDiffService();
        $files = [['path' => 'a.txt', 'content' => '', 'deleted' => true]];
        $hash = $svc->hashFiles($files);
        self::assertSame(64, strlen($hash));
    }

    public function testDiffMultipleTargets(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/abilities/skills/demo', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo', 0775, true);
        mkdir($workspace . '/.kiro/skills/demo', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo/SKILL.md', "x\n");
        file_put_contents($workspace . '/.cursor/skills/demo/SKILL.md', "x\n");
        file_put_contents($workspace . '/.kiro/skills/demo/SKILL.md', "x\n");

        $svc = new AbilityDiffService();
        $results = $svc->diffForInstalledTargets(
            ['skills' => ['demo'], 'rules' => [], 'agents' => []],
            ['cursor', 'kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(2, $results);
        self::assertSame('cursor', $results[0]['target']);
        self::assertSame('kiro', $results[1]['target']);
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
