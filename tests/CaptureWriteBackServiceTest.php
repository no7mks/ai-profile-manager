<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureWriteBackService;
use PHPUnit\Framework\TestCase;

final class CaptureWriteBackServiceTest extends TestCase
{
    public function testWriteBackSkillRuleAgentAndPresetPaths(): void
    {
        $root = sys_get_temp_dir() . '/apm-wb-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/myagent', 0775, true);
        mkdir($root . '/abilities/rules/git', 0775, true);
        mkdir($root . '/abilities/agents', 0775, true);

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
                    'files' => [['path' => 'rules/git/myrule.cursor.mdc', 'content' => 'r', 'patch' => 'p']],
                ],
                [
                    'type' => 'agent',
                    'name' => 'myagent',
                    'files' => [['path' => 'agents/myagent.cursor.md', 'content' => 'a', 'patch' => 'p']],
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

        self::assertFileExists($root . '/abilities/skills/myskill/SKILL.md');
        self::assertFileExists($root . '/abilities/rules/git/myrule.cursor.mdc');
        self::assertFileExists($root . '/abilities/agents/myagent.cursor.md');
        self::assertFileExists($root . '/abilities/_presets.json');
        self::assertFileExists($root . '/abilities/unknown-items/x/cursor/u.txt');
        self::assertNotEmpty($lines);
    }

    public function testWriteBackDeletesFileWhenDeletedFlagSet(): void
    {
        $root = sys_get_temp_dir() . '/apm-wbdel-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/delme', 0775, true);
        $path = $root . '/abilities/skills/delme/SKILL.md';
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

    public function testWriteBackSkipsRelativePathThatNormalizesToNothing(): void
    {
        $root = sys_get_temp_dir() . '/apm-wbskip-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/x', 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($root);

        $svc = new CaptureWriteBackService();
        $lines = $svc->writeBack([
            'target' => 'cursor',
            'items' => [[
                'type' => 'skill',
                'name' => 'x',
                'files' => [['path' => '..', 'content' => 'z', 'patch' => 'p']],
            ]],
        ]);

        chdir($old);

        self::assertSame([], $lines);
    }
}
