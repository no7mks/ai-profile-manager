<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\HookChecker;
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

    public function testCheckTypedDispatchesHookToHookCheckerKiro(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-kiro-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-kiro-ws-' . bin2hex(random_bytes(4));

        // Create baseline hook source file
        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/my-hook.kiro.hook', '{"name":"my-hook"}');

        // Create workspace installed hook file (same content = ok)
        mkdir($workspace . '/.kiro/hooks', 0775, true);
        file_put_contents($workspace . '/.kiro/hooks/my-hook.kiro.hook', '{"name":"my-hook"}');

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('hook', $results[0]['type']);
        self::assertSame('my-hook', $results[0]['name']);
        self::assertSame('kiro', $results[0]['target']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testCheckTypedHookKiroDriftStatus(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-drift-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-drift-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/drifted.kiro.hook', '{"name":"original"}');

        mkdir($workspace . '/.kiro/hooks', 0775, true);
        file_put_contents($workspace . '/.kiro/hooks/drifted.kiro.hook', '{"name":"modified"}');

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['drifted'],
        ], ['kiro']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('modified', $results[0]['status']);
    }

    public function testCheckTypedHookKiroMissingStatus(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-miss-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-miss-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/absent.kiro.hook', '{"name":"absent"}');

        // No installed file in workspace
        mkdir($workspace, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['absent'],
        ], ['kiro']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testCheckTypedDispatchesHookToHookCheckerCursor(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-cursor-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-cursor-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/hooks', 0775, true);
        // Baseline hook source (not used for cursor check directly, but must exist for path resolution)
        file_put_contents($baseline . '/hooks/check-write.kiro.hook', '{}');

        // Create cursor hook directory with entry script and declaration
        $hookDir = $workspace . '/.cursor/hooks/check-write';
        mkdir($hookDir, 0775, true);
        file_put_contents($hookDir . '/check-write.sh', '#!/bin/bash');
        file_put_contents($hookDir . '/check-write.json', json_encode([
            'preToolUse' => [['command' => '.cursor/hooks/check-write/check-write.sh']],
        ]));

        // Create hooks.json with matching entry
        file_put_contents($workspace . '/.cursor/hooks.json', json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [['command' => '.cursor/hooks/check-write/check-write.sh']],
            ],
        ]));

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['check-write'],
        ], ['cursor']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('hook', $results[0]['type']);
        self::assertSame('check-write', $results[0]['name']);
        self::assertSame('cursor', $results[0]['target']);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testCheckTypedHookCursorMissingStatus(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-cmiss-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-cmiss-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/missing-hook.kiro.hook', '{}');

        // No cursor hook directory in workspace
        mkdir($workspace, 0775, true);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['missing-hook'],
        ], ['cursor']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('missing', $results[0]['status']);
    }

    public function testEvaluateExitCodeReturnsTwoForHookDriftOrMissing(): void
    {
        $service = new CheckService();

        self::assertSame(2, $service->evaluateExitCode([
            ['type' => 'hook', 'name' => 'my-hook', 'target' => 'kiro', 'status' => 'modified'],
        ]));

        self::assertSame(2, $service->evaluateExitCode([
            ['type' => 'hook', 'name' => 'my-hook', 'target' => 'cursor', 'status' => 'missing'],
        ]));
    }

    public function testCheckTypedHookResultsMergedWithOtherTypes(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-hook-merge-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-hook-merge-ws-' . bin2hex(random_bytes(4));

        // Setup baseline for skill and hook
        mkdir($baseline . '/abilities/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo-skill/SKILL.md', "v1\n");
        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/my-hook.kiro.hook', '{"name":"my-hook"}');

        // Setup workspace
        mkdir($workspace . '/.kiro/skills/demo-skill', 0775, true);
        file_put_contents($workspace . '/.kiro/skills/demo-skill/SKILL.md', "v1\n");
        mkdir($workspace . '/.kiro/hooks', 0775, true);
        file_put_contents($workspace . '/.kiro/hooks/my-hook.kiro.hook', '{"name":"my-hook"}');

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTyped([
            'skills' => ['demo-skill'],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro']);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(2, $results);
        self::assertSame('skill', $results[0]['type']);
        self::assertSame('hook', $results[1]['type']);
        self::assertSame('unchanged', $results[0]['status']);
        self::assertSame('unchanged', $results[1]['status']);
    }

    public function testCheckTypedReturnsUnknownForHooksWhenBaselineMissing(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-check-hook-nobase-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['packages' => []]));
        $oldCh = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $service = new CheckService();

        $results = $service->checkTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro']);

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

        self::assertCount(1, $results);
        self::assertSame('hook', $results[0]['type']);
        self::assertSame('my-hook', $results[0]['name']);
        self::assertSame('unknown', $results[0]['status']);
    }
}
