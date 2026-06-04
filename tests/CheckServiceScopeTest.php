<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class CheckServiceScopeTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    public function testCheckTypedForScopeUserScopeUsesHomeRootForDiff(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-user-base-' . bin2hex(random_bytes(4));
        $home = sys_get_temp_dir() . '/apm-check-user-home-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-user-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($home . '/.cursor/rules/git', 0775, true);
        mkdir($workspace, 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/scope-rule.mdc', "base\n");
        file_put_contents($home . '/.cursor/rules/git/scope-rule.mdc', "base\n");

        $yaml = <<<'YAML'
version: "1"
rules:
  - path: scope-rule
    description: user scope rule
    targets:
      cursor: .cursor/rules/git/scope-rule.mdc
agents: []
skills: []
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $this->withEnv('HOME', $home);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $results = $service->checkTypedForScope(
            ['skills' => [], 'rules' => ['scope-rule'], 'agents' => []],
            ['cursor'],
            DeployScope::User,
        );

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testCheckTypedForScopeUserScopeMissingWhenInstalledOnlyInProject(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-user-miss-base-' . bin2hex(random_bytes(4));
        $home = sys_get_temp_dir() . '/apm-check-user-miss-home-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-user-miss-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        mkdir($home, 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/scope-rule.mdc', "base\n");
        file_put_contents($workspace . '/.cursor/rules/git/scope-rule.mdc', "base\n");

        $yaml = <<<'YAML'
version: "1"
rules:
  - path: scope-rule
    description: user scope rule
    targets:
      cursor: .cursor/rules/git/scope-rule.mdc
agents: []
skills: []
hooks: []
YAML;
        file_put_contents($baseline . '/abilities.yaml', $yaml);

        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $this->withEnv('HOME', $home);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        $service = new CheckService();
        $userResults = $service->checkTypedForScope(
            ['skills' => [], 'rules' => ['scope-rule'], 'agents' => []],
            ['cursor'],
            DeployScope::User,
        );
        $projectResults = $service->checkTypedForScope(
            ['skills' => [], 'rules' => ['scope-rule'], 'agents' => []],
            ['cursor'],
            DeployScope::Project,
        );

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame('missing', $userResults[0]['status']);
        self::assertSame('unchanged', $projectResults[0]['status']);
    }

    public function testCheckTypedDelegatesToProjectScope(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-check-delegate-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-check-delegate-ws-' . bin2hex(random_bytes(4));

        mkdir($baseline . '/.cursor/skills/demo-skill', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo-skill', 0775, true);
        file_put_contents($baseline . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");
        file_put_contents($workspace . '/.cursor/skills/demo-skill/SKILL.md', "v1\n");

        $yaml = <<<'YAML'
version: "1"
rules: []
agents: []
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
        $items = ['skills' => ['demo-skill'], 'rules' => [], 'agents' => []];
        $targets = ['cursor'];
        $typed = $service->checkTyped($items, $targets);
        $scoped = $service->checkTypedForScope($items, $targets, DeployScope::Project);

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame($scoped, $typed);
    }
}
