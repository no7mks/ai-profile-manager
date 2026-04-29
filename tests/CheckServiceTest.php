<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;

final class CheckServiceTest extends TestCase
{
    public function testCheckTypedReturnsUnknownWhenBaselineMissing(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-check-no-base-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['packages' => []]));
        $oldCh = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $service = new CheckService();

        $results = $service->checkTyped([
            'skills' => ['graphify'],
            'rules' => ['spec-goal'],
            'agents' => ['spec-gatekeeper'],
        ], ['cursor']);

        if ($oldCh === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldCh);
        }
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(3, $results);
        foreach ($results as $result) {
            self::assertSame('unknown', $result['status']);
        }
    }

    public function testCheckTypedDetectsUnchangedModifiedAndMissing(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/demo-skill', 0775, true);
        mkdir($baseline . '/abilities/rules/git', 0775, true);
        mkdir($baseline . '/abilities/agents', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($baseline . '/abilities/rules/git/demo-rule.cursor.mdc', "rule-base\n");
        file_put_contents($baseline . '/abilities/agents/demo-agent.cursor.md', "agent-base\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-mod\n");

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => ['demo-skill'],
            'rules' => ['demo-rule'],
            'agents' => ['demo-agent'],
        ], ['cursor']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(3, $results);
        self::assertSame('unchanged', $results[0]['status']);
        self::assertSame('modified', $results[1]['status']);
        self::assertSame('missing', $results[2]['status']);
    }

    public function testEvaluateExitCodeReturnsTwoForModifiedOrMissing(): void
    {
        $service = new CheckService();

        $exitCode = $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'graphify', 'target' => 'cursor', 'status' => 'modified'],
        ]);

        self::assertSame(2, $exitCode);

        self::assertSame(2, $service->evaluateExitCode([
            ['type' => 'rule', 'name' => 'spec-goal', 'target' => 'cursor', 'status' => 'missing'],
        ]));
    }

    public function testEvaluateExitCodeReturnsZeroWhenNoModifiedOrMissing(): void
    {
        $service = new CheckService();

        self::assertSame(0, $service->evaluateExitCode([]));

        self::assertSame(0, $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'x', 'target' => 'cursor', 'status' => 'unchanged'],
            ['type' => 'skill', 'name' => 'y', 'target' => 'cursor', 'status' => 'unknown'],
        ]));
    }

    public function testRenderResultsPrefixesByStatus(): void
    {
        $service = new CheckService();
        $lines = $service->renderResults([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'unchanged'],
            ['type' => 'rule', 'name' => 'b', 'target' => 'cursor', 'status' => 'modified'],
            ['type' => 'agent', 'name' => 'c', 'target' => 'cursor', 'status' => 'missing'],
            ['type' => 'skill', 'name' => 'd', 'target' => 'cursor', 'status' => 'unknown'],
        ]);

        self::assertStringContainsString('[ok]', $lines[0]);
        self::assertStringContainsString('[drift]', $lines[1]);
        self::assertStringContainsString('[miss]', $lines[2]);
        self::assertStringContainsString('[todo]', $lines[3]);
    }

    public function testHasModifiedDetectsModifiedOnly(): void
    {
        $service = new CheckService();
        self::assertFalse($service->hasModified([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'unchanged'],
            ['type' => 'rule', 'name' => 'b', 'target' => 'cursor', 'status' => 'missing'],
        ]));
        self::assertTrue($service->hasModified([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'modified'],
        ]));
    }
}
