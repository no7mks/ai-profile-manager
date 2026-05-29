<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityDiffService;
use AiProfileManager\Service\AbilityDirectoryDiff;
use AiProfileManager\Service\AbilityRegistry;
use PHPUnit\Framework\TestCase;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class AbilityDiffServiceExtendedTest extends TestCase
{
    use RemovesDirTrait;

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
        mkdir($baseline . '/.kiro/agents', 0775, true);
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($baseline . '/.kiro/agents/reviewer.md', "content\n");
        file_put_contents($workspace . '/.kiro/agents/reviewer.md', "content\n");

        $svc = $this->createService([
            'agents' => [
                ['path' => 'reviewer', 'description' => 'test', 'targets' => ['kiro' => '.kiro/agents/reviewer.md']],
            ],
        ]);
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
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/agents/reviewer.md', "original\n");
        file_put_contents($workspace . '/.cursor/agents/reviewer.md', "modified\n");

        $svc = $this->createService([
            'agents' => [
                ['path' => 'reviewer', 'description' => 'test', 'targets' => ['cursor' => '.cursor/agents/reviewer.md']],
            ],
        ]);
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
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.cursor/agents/reviewer.md', "content\n");

        $svc = $this->createService([
            'agents' => [
                ['path' => 'reviewer', 'description' => 'test', 'targets' => ['cursor' => '.cursor/agents/reviewer.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testDiffAgentSkipsWhenEntryNotInRegistry(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($workspace . '/.cursor/agents/unknown-agent.md', "x\n");

        $svc = $this->createService(['agents' => []]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['unknown-agent']],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(0, $results);
    }

    public function testDiffRuleDetectsUnchangedOnCursor(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/branch-overview.mdc', "rule\n");
        file_put_contents($workspace . '/.cursor/rules/git/branch-overview.mdc', "rule\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'git:branch-overview', 'description' => 'test', 'targets' => ['cursor' => '.cursor/rules/git/branch-overview.mdc']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['git:branch-overview'], 'agents' => []],
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
        mkdir($baseline . '/.kiro/steering/spec', 0775, true);
        mkdir($workspace . '/.kiro/steering/spec', 0775, true);
        file_put_contents($baseline . '/.kiro/steering/spec/spec-goal.md', "original\n");
        file_put_contents($workspace . '/.kiro/steering/spec/spec-goal.md', "modified\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'spec:spec-goal', 'description' => 'test', 'targets' => ['kiro' => '.kiro/steering/spec/spec-goal.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['spec:spec-goal'], 'agents' => []],
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
        mkdir($baseline . '/.kiro/steering/spec', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.kiro/steering/spec/spec-goal.md', "content\n");

        $svc = $this->createService([
            'rules' => [
                ['path' => 'spec:spec-goal', 'description' => 'test', 'targets' => ['kiro' => '.kiro/steering/spec/spec-goal.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['spec:spec-goal'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testHashFilesProducesConsistentHash(): void
    {
        $svc = $this->createService([]);
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
        $svc = $this->createService([]);
        $files = [['path' => 'a.txt', 'content' => '', 'deleted' => true]];
        $hash = $svc->hashFiles($files);
        self::assertSame(64, strlen($hash));
    }

    public function testDiffMultipleTargets(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/.cursor/skills/demo', 0775, true);
        mkdir($baseline . '/.kiro/skills/demo', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo', 0775, true);
        mkdir($workspace . '/.kiro/skills/demo', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo/SKILL.md', "x\n");
        file_put_contents($baseline . '/.kiro/skills/demo/SKILL.md', "x\n");
        file_put_contents($workspace . '/.cursor/skills/demo/SKILL.md', "x\n");
        file_put_contents($workspace . '/.kiro/skills/demo/SKILL.md', "x\n");

        $svc = $this->createService([
            'skills' => [
                ['path' => 'demo', 'description' => 'test', 'targets' => ['cursor' => '.cursor/skills/demo/', 'kiro' => '.kiro/skills/demo/']],
            ],
        ]);
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

    public function testDiffSkipsWhenTargetNotInEntry(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true);
        mkdir($workspace, 0775, true);

        // Entry only has cursor target, but we request kiro
        $svc = $this->createService([
            'rules' => [
                ['path' => 'cursor-scope', 'description' => 'test', 'targets' => ['cursor' => '.cursor/rules/cursor-scope.mdc']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => ['cursor-scope'], 'agents' => []],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(0, $results);
    }

    public function testDiffReturnsNoBaselineWhenBaselineRootIsInvalid(): void
    {
        $baseline = $this->tmpDir . '/nonexistent-baseline'; // does NOT exist as directory
        $workspace = $this->tmpDir . '/workspace';
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/demo.mdc', "content\n");

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
        self::assertSame('no-baseline', $results[0]['status']);
    }

    public function testDiffSkillReturnsNoBaselineWhenBaselineRootIsInvalid(): void
    {
        $baseline = $this->tmpDir . '/nonexistent-baseline'; // does NOT exist as directory
        $workspace = $this->tmpDir . '/workspace';
        mkdir($workspace . '/.cursor/skills/demo', 0775, true);
        file_put_contents($workspace . '/.cursor/skills/demo/SKILL.md', "x\n");

        $svc = $this->createService([
            'skills' => [
                ['path' => 'demo', 'description' => 'test', 'targets' => ['cursor' => '.cursor/skills/demo/']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => ['demo'], 'rules' => [], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('no-baseline', $results[0]['status']);
    }

    public function testDiffAgentReturnsNoBaselineWhenBaselineRootIsInvalid(): void
    {
        $baseline = $this->tmpDir . '/nonexistent-baseline'; // does NOT exist as directory
        $workspace = $this->tmpDir . '/workspace';
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($workspace . '/.kiro/agents/reviewer.md', "x\n");

        $svc = $this->createService([
            'agents' => [
                ['path' => 'reviewer', 'description' => 'test', 'targets' => ['kiro' => '.kiro/agents/reviewer.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('no-baseline', $results[0]['status']);
    }

    public function testDiffRuleReturnsNewWhenBaselineRootExistsButAbilityPathMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true); // baseline root exists but no rule file inside
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($workspace . '/.cursor/rules/git/demo.mdc', "content\n");

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
        self::assertSame('new', $results[0]['status']);
    }

    public function testDiffSkillReturnsNewWhenBaselineRootExistsButSkillDirMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true); // baseline root exists but no skill dir inside
        mkdir($workspace . '/.cursor/skills/demo', 0775, true);
        file_put_contents($workspace . '/.cursor/skills/demo/SKILL.md', "x\n");

        $svc = $this->createService([
            'skills' => [
                ['path' => 'demo', 'description' => 'test', 'targets' => ['cursor' => '.cursor/skills/demo/']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => ['demo'], 'rules' => [], 'agents' => []],
            ['cursor'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('new', $results[0]['status']);
    }

    public function testDiffAgentReturnsNewWhenBaselineRootExistsButAgentFileMissing(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline, 0775, true); // baseline root exists but no agent file inside
        mkdir($workspace . '/.kiro/agents', 0775, true);
        file_put_contents($workspace . '/.kiro/agents/reviewer.md', "x\n");

        $svc = $this->createService([
            'agents' => [
                ['path' => 'reviewer', 'description' => 'test', 'targets' => ['kiro' => '.kiro/agents/reviewer.md']],
            ],
        ]);
        $results = $svc->diffForInstalledTargets(
            ['skills' => [], 'rules' => [], 'agents' => ['reviewer']],
            ['kiro'],
            $baseline,
            $workspace,
        );

        self::assertCount(1, $results);
        self::assertSame('new', $results[0]['status']);
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

}
