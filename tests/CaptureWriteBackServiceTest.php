<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureWriteBackService;
use PHPUnit\Framework\TestCase;

final class CaptureWriteBackServiceTest extends TestCase
{
    public function testWriteBackSkillRuleAgentAndPresetPaths(): void
    {
        $root = sys_get_temp_dir() . '/aipm-wb-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/myagent/cursor', 0775, true);
        mkdir($root . '/abilities/rules/myrule/cursor', 0775, true);
        mkdir($root . '/abilities/agents/myagent/cursor', 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($root);

        $svc = new CaptureWriteBackService();
        $lines = $svc->writeBack([
            'target' => 'cursor',
            'items' => [
                [
                    'type' => 'skill',
                    'name' => 'myskill',
                    'files' => [['path' => 'SKILL.md', 'content' => 'x', 'patch' => 'p']],
                ],
                [
                    'type' => 'rule',
                    'name' => 'myrule',
                    'files' => [['path' => 'RULE.mdc', 'content' => 'r', 'patch' => 'p']],
                ],
                [
                    'type' => 'agent',
                    'name' => 'myagent',
                    'files' => [['path' => 'agent.md', 'content' => 'a', 'patch' => 'p']],
                ],
                [
                    'type' => 'preset',
                    'name' => '_presets',
                    'files' => [['path' => 'abilities/_presets.json', 'content' => '{}', 'patch' => 'p']],
                ],
                [
                    'type' => 'other',
                    'name' => 'x',
                    'files' => [['path' => 'u.txt', 'content' => 'u', 'patch' => 'p']],
                ],
            ],
        ]);

        chdir($old);

        self::assertFileExists($root . '/abilities/skills/myskill/cursor/SKILL.md');
        self::assertFileExists($root . '/abilities/rules/myrule/cursor/RULE.mdc');
        self::assertFileExists($root . '/abilities/agents/myagent/cursor/agent.md');
        self::assertFileExists($root . '/abilities/_presets.json');
        self::assertFileExists($root . '/abilities/unknown-items/x/cursor/u.txt');
        self::assertNotEmpty($lines);
    }

    public function testWriteBackDeletesFileWhenDeletedFlagSet(): void
    {
        $root = sys_get_temp_dir() . '/aipm-wbdel-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/delme/cursor', 0775, true);
        $path = $root . '/abilities/skills/delme/cursor/SKILL.md';
        file_put_contents($path, 'gone');

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($root);

        $svc = new CaptureWriteBackService();
        $lines = $svc->writeBack([
            'target' => 'cursor',
            'items' => [[
                'type' => 'skill',
                'name' => 'delme',
                'files' => [[
                    'path' => 'SKILL.md',
                    'content' => '',
                    'patch' => '...',
                    'deleted' => true,
                ]],
            ]],
        ]);

        chdir($old);

        self::assertFileDoesNotExist($path);
        self::assertTrue(array_reduce($lines, fn (bool $a, string $l): bool => $a || str_contains($l, 'Delete'), false));
    }
}
