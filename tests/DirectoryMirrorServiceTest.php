<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\DirectoryMirrorService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DirectoryMirrorServiceTest extends TestCase
{
    public function testMirrorDirectoryCopiesNestedTree(): void
    {
        $src = sys_get_temp_dir() . '/apm-mirror-src-' . bin2hex(random_bytes(4));
        $dst = sys_get_temp_dir() . '/apm-mirror-dst-' . bin2hex(random_bytes(4));
        mkdir($src . '/a/b', 0775, true);
        file_put_contents($src . '/a/b/file.txt', "hello\n");

        $mirror = new DirectoryMirrorService();
        $mirror->mirrorDirectory($src, $dst);

        self::assertFileExists($dst . '/a/b/file.txt');
        self::assertSame("hello\n", (string) file_get_contents($dst . '/a/b/file.txt'));
    }

    public function testMirrorDirectoryThrowsWhenSourceMissing(): void
    {
        $mirror = new DirectoryMirrorService();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source is not a directory');

        $mirror->mirrorDirectory('/tmp/apm-mirror-missing-' . bin2hex(random_bytes(4)), '/tmp/apm-any');
    }

    public function testCopyFileThrowsWithoutForceWhenDestinationExists(): void
    {
        $dir = sys_get_temp_dir() . '/apm-mirror-copy-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        $src = $dir . '/src.txt';
        $dst = $dir . '/dst.txt';
        file_put_contents($src, "new\n");
        file_put_contents($dst, "old\n");

        $mirror = new DirectoryMirrorService();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pass --force');
        $mirror->copyFile($src, $dst, false);
    }

    public function testMirrorDirectoryHandlesTrailingSlashInSource(): void
    {
        $src = sys_get_temp_dir() . '/apm-mirror-slash-' . bin2hex(random_bytes(4));
        $dst = sys_get_temp_dir() . '/apm-mirror-slash-dst-' . bin2hex(random_bytes(4));
        mkdir($src, 0775, true);
        file_put_contents($src . '/SKILL.md', "# Skill\n");

        $mirror = new DirectoryMirrorService();
        // Source path with trailing slash — should still copy correctly
        $mirror->mirrorDirectory($src . '/', $dst);

        self::assertFileExists($dst . '/SKILL.md');
        self::assertSame("# Skill\n", (string) file_get_contents($dst . '/SKILL.md'));
    }
}
