<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;

final class CheckServiceTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

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

        // New layout: baseline uses targets paths directly
        mkdir($baseline . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($baseline . '/.cursor/rules/git/demo-rule.mdc', "rule-base\n");
        file_put_contents($baseline . '/.cursor/agents/demo-agent.md', "agent-base\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-mod\n");

        // Create abilities.yaml in baseline root
        $yaml = <<<'YAML'
version: "1"
rules:
  - path: demo-rule
    description: test rule
    targets:
      cursor: .cursor/rules/git/demo-rule.mdc
agents:
  - path: demo-agent
    description: test agent
    targets:
      cursor: .cursor/agents/demo-agent.md
skills:
  - path: demo-skill
    description: test skill
    targets:
      cursor: .cursor/skills/demo-skill/
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

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

        // Create empty abilities.yaml
        $this->writeEmptyAbilitiesYaml($baseline);

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

        $this->writeEmptyAbilitiesYaml($baseline);

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

        $this->writeEmptyAbilitiesYaml($baseline);

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

        $this->writeEmptyAbilitiesYaml($baseline);

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

        $this->writeEmptyAbilitiesYaml($baseline);

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

        // Setup baseline for skill and hook (new layout)
        mkdir($baseline . '/.kiro/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/.kiro/skills/demo-skill/SKILL.md', "v1\n");
        mkdir($baseline . '/hooks', 0775, true);
        file_put_contents($baseline . '/hooks/my-hook.kiro.hook', '{"name":"my-hook"}');

        // Create abilities.yaml in baseline root
        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
skills:
  - path: demo-skill
    description: test skill
    targets:
      kiro: .kiro/skills/demo-skill/
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

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

    public function testEvaluateExitCodeReturnsTwoForNoBaseline(): void
    {
        $service = new CheckService();

        self::assertSame(2, $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'graphify', 'target' => 'cursor', 'status' => 'no-baseline'],
        ]));
    }

    public function testEvaluateExitCodeReturnsZeroForNew(): void
    {
        $service = new CheckService();

        self::assertSame(0, $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'graphify', 'target' => 'cursor', 'status' => 'new'],
        ]));
    }

    public function testRenderResultsPrefixForNoBaseline(): void
    {
        $service = new CheckService();
        $lines = $service->renderResults([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'no-baseline'],
        ]);

        self::assertStringContainsString('[nobl]', $lines[0]);
    }

    public function testRenderResultsPrefixForNew(): void
    {
        $service = new CheckService();
        $lines = $service->renderResults([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'new'],
        ]);

        self::assertStringContainsString('[new]', $lines[0]);
    }


    public function testCheckTypedDoesNotReturnAllUnknownWhenBaselineResolvesViaXdgComposerHome(): void
    {
        $home = sys_get_temp_dir() . '/apm-check-xdg-home-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-xdg-ws-' . bin2hex(random_bytes(4));
        $baseline = $home . '/.config/composer/vendor/no7mks/ai-profile-manager';

        mkdir($baseline . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($baseline . '/.cursor/rules/git/demo-rule.mdc', "rule-base\n");
        file_put_contents($baseline . '/.cursor/agents/demo-agent.md', "agent-base\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo-rule.mdc', "rule-mod\n");

        $yaml = <<<'YAML'
version: "1"
rules:
  - path: demo-rule
    description: test rule
    targets:
      cursor: .cursor/rules/git/demo-rule.mdc
agents:
  - path: demo-agent
    description: test agent
    targets:
      cursor: .cursor/agents/demo-agent.md
skills:
  - path: demo-skill
    description: test skill
    targets:
      cursor: .cursor/skills/demo-skill/
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

        $this->seedXdgComposerInstalledJson($home);
        mkdir($home . '/.composer/vendor/composer', 0775, true);

        $this->withEnv('HOME', $home);
        $this->withEnv('APM_BASELINE_ROOT', null);
        $this->withEnv('COMPOSER_HOME', null);

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

        self::assertCount(3, $results);
        foreach ($results as $result) {
            self::assertNotSame('unknown', $result['status'], 'baseline must resolve via XDG composer home');
        }
        self::assertSame('unchanged', $results[0]['status']);
        self::assertSame('modified', $results[1]['status']);
        self::assertSame('missing', $results[2]['status']);
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


    private function seedXdgComposerInstalledJson(string $home): void
    {
        $composerHome = $home . '/.config/composer';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents(
            $composerHome . '/vendor/composer/installed.json',
            json_encode([
                'packages' => [[
                    'name' => 'no7mks/ai-profile-manager',
                    'version' => '1.0.0-xdg-check',
                    'dist' => ['reference' => 'ref-xdg-check'],
                ]],
            ], JSON_UNESCAPED_SLASHES),
        );
    }

    private function writeEmptyAbilitiesYaml(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
skills: []
hooks: []
YAML;
        file_put_contents($dir . '/abilities.yaml', $yaml);
    }
}
