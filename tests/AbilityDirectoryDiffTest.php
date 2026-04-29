<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityDirectoryDiff;
use PHPUnit\Framework\TestCase;

final class AbilityDirectoryDiffTest extends TestCase
{
    public function testDiffDirectoriesDetectsContentChange(): void
    {
        $base = sys_get_temp_dir() . '/apm-diff-a-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/apm-diff-b-' . bin2hex(random_bytes(4));
        mkdir($base . '/sub', 0775, true);
        mkdir($ws . '/sub', 0775, true);
        file_put_contents($base . '/sub/a.txt', "old\n");
        file_put_contents($ws . '/sub/a.txt', "new\n");

        $diff = new AbilityDirectoryDiff();
        $files = $diff->diffDirectories($base, $ws);

        self::assertCount(1, $files);
        self::assertSame('sub/a.txt', $files[0]['path']);
        self::assertSame("new\n", $files[0]['content']);
        self::assertIsString($files[0]['patch']);
        self::assertArrayNotHasKey('deleted', $files[0]);
    }

    public function testDiffDirectoriesMarksFileDeletedInWorkspace(): void
    {
        $base = sys_get_temp_dir() . '/apm-diff-del-a-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/apm-diff-del-b-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($ws, 0775, true);
        file_put_contents($base . '/gone.txt', "only in baseline\n");

        $diff = new AbilityDirectoryDiff();
        $files = $diff->diffDirectories($base, $ws);

        self::assertCount(1, $files);
        self::assertSame('gone.txt', $files[0]['path']);
        self::assertTrue($files[0]['deleted'] ?? false);
        self::assertSame('', $files[0]['content']);
    }

    public function testDiffOptionalFilesDeletionVsBaseline(): void
    {
        $base = sys_get_temp_dir() . '/apm-optf-a-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        $presetPath = $base . '/abilities/_presets.json';
        mkdir(dirname($presetPath), 0775, true);
        file_put_contents($presetPath, '{"a":1}');

        $diff = new AbilityDirectoryDiff();
        $files = $diff->diffOptionalFiles($presetPath, null, 'abilities/_presets.json');

        self::assertCount(1, $files);
        self::assertSame('abilities/_presets.json', $files[0]['path']);
        self::assertTrue($files[0]['deleted'] ?? false);
    }

    public function testDiffDirectoriesAddsNestedFileOnlyInWorkspace(): void
    {
        $base = sys_get_temp_dir() . '/apm-nest-a-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/apm-nest-b-' . bin2hex(random_bytes(4));
        mkdir($base, 0775, true);
        mkdir($ws . '/deep/sub', 0775, true);
        file_put_contents($ws . '/deep/sub/z.txt', 'only-here');

        $diff = new AbilityDirectoryDiff();
        $files = $diff->diffDirectories($base, $ws);

        self::assertCount(1, $files);
        self::assertSame('deep/sub/z.txt', $files[0]['path']);
        self::assertStringContainsString('+only-here', $files[0]['patch']);
    }
}
