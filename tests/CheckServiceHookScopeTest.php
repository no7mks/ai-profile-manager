<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Tests\Support\RestoresCwdTrait;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class CheckServiceHookScopeTest extends TestCase
{
    use RestoresCwdTrait;
    use RestoresEnvTrait;

    public function testUserScopeKiroHookUsesHomeRoot(): void
    {
        [$baseline, $home, $ws] = $this->makeDirs('uhk');
        mkdir($baseline . '/hooks', 0775, true);
        $content = '{"name":"scope-hook"}';
        file_put_contents($baseline . '/hooks/scope-hook.kiro.hook', $content);
        mkdir($home . '/.kiro/hooks', 0775, true);
        file_put_contents($home . '/.kiro/hooks/scope-hook.kiro.hook', $content);

        $results = $this->checkHooks($baseline, $home, $ws, ['scope-hook'], ['kiro'], DeployScope::User);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testUserScopeKiroHookMissingWhenOnlyInProject(): void
    {
        [$baseline, $home, $ws] = $this->makeDirs('uhkm');
        mkdir($baseline . '/hooks', 0775, true);
        $content = '{"name":"scope-hook"}';
        file_put_contents($baseline . '/hooks/scope-hook.kiro.hook', $content);
        mkdir($ws . '/.kiro/hooks', 0775, true);
        file_put_contents($ws . '/.kiro/hooks/scope-hook.kiro.hook', $content);

        $user = $this->checkHooks($baseline, $home, $ws, ['scope-hook'], ['kiro'], DeployScope::User);
        $project = $this->checkHooks($baseline, $home, $ws, ['scope-hook'], ['kiro'], DeployScope::Project);
        self::assertSame('missing', $user[0]['status']);
        self::assertSame('unchanged', $project[0]['status']);
    }

    public function testUserScopeCursorHookUsesHomeRoot(): void
    {
        [$baseline, $home, $ws] = $this->makeDirs('uch');
        $this->seedCursorHook($baseline, $home, 'user-cursor-hook');

        $results = $this->checkHooks($baseline, $home, $ws, ['user-cursor-hook'], ['cursor'], DeployScope::User);
        self::assertSame('unchanged', $results[0]['status']);
    }

    public function testUserScopeCursorHookMissingWhenOnlyInProject(): void
    {
        [$baseline, $home, $ws] = $this->makeDirs('uchm');
        $this->seedCursorHook($baseline, $ws, 'proj-only-hook');

        $user = $this->checkHooks($baseline, $home, $ws, ['proj-only-hook'], ['cursor'], DeployScope::User);
        $project = $this->checkHooks($baseline, $home, $ws, ['proj-only-hook'], ['cursor'], DeployScope::Project);
        self::assertSame('missing', $user[0]['status']);
        self::assertSame('unchanged', $project[0]['status']);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function makeDirs(string $tag): array
    {
        $id = bin2hex(random_bytes(4));
        $baseline = sys_get_temp_dir() . "/apm-{$tag}-b-{$id}";
        $home = sys_get_temp_dir() . "/apm-{$tag}-h-{$id}";
        $ws = sys_get_temp_dir() . "/apm-{$tag}-w-{$id}";
        mkdir($baseline, 0775, true);
        mkdir($home, 0775, true);
        mkdir($ws, 0775, true);
        file_put_contents($baseline . '/abilities.yaml', "version: \"1\"\nrules: []\nagents: []\nskills: []\nhooks: []\n");

        return [$baseline, $home, $ws];
    }

    private function seedCursorHook(string $baseline, string $root, string $name): void
    {
        if (!is_dir($baseline . '/hooks')) {
            mkdir($baseline . '/hooks', 0775, true);
        }
        file_put_contents($baseline . "/hooks/{$name}.kiro.hook", '{}');
        $hookDir = $root . "/.cursor/hooks/{$name}";
        mkdir($hookDir, 0775, true);
        $cmd = ".cursor/hooks/{$name}/{$name}.sh";
        file_put_contents("{$hookDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$hookDir}/{$name}.json", json_encode(['preToolUse' => [['command' => $cmd]]]));
        mkdir($root . '/.cursor', 0775, true);
        file_put_contents($root . '/.cursor/hooks.json', json_encode([
            'version' => 1,
            'hooks' => ['preToolUse' => [['command' => $cmd]]],
        ]));
    }

    /**
     * @param list<string> $hooks
     * @param list<string> $targets
     * @return list<array{type: string, name: string, target: string, status: string}>
     */
    private function checkHooks(
        string $baseline,
        string $home,
        string $ws,
        array $hooks,
        array $targets,
        DeployScope $scope,
    ): array {
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $baseline);
        $this->withEnv('HOME', $home);
        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($ws);

        $results = (new CheckService())->checkTypedForScope(
            ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => $hooks],
            $targets,
            $scope,
        );

        chdir($oldCwd);
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertCount(1, $results);
        self::assertSame('hook', $results[0]['type']);

        return $results;
    }
}
