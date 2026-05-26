<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityDirectoryDiff;
use PHPUnit\Framework\TestCase;

final class AbilityDirectoryDiffExtendedTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-dirdiff-ext-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testDiffOptionalFilesDetectsModification(): void
    {
        $base = $this->tmpDir . '/base.md';
        $work = $this->tmpDir . '/work.md';
        file_put_contents($base, "line1\nline2\n");
        file_put_contents($work, "line1\nline2-modified\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffOptionalFiles($base, $work, 'test.md');

        self::assertCount(1, $result);
        self::assertSame('test.md', $result[0]['path']);
        self::assertStringContainsString('+line2-modified', $result[0]['patch']);
        self::assertStringContainsString('-line2', $result[0]['patch']);
    }

    public function testDiffOptionalFilesDetectsAddition(): void
    {
        $work = $this->tmpDir . '/work.md';
        file_put_contents($work, "new content\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffOptionalFiles(null, $work, 'new.md');

        self::assertCount(1, $result);
        self::assertStringContainsString('+new content', $result[0]['patch']);
        self::assertArrayNotHasKey('deleted', $result[0]);
    }

    public function testDiffOptionalFilesDetectsDeletion(): void
    {
        $base = $this->tmpDir . '/base.md';
        file_put_contents($base, "old content\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffOptionalFiles($base, null, 'deleted.md');

        self::assertCount(1, $result);
        self::assertTrue($result[0]['deleted']);
        self::assertStringContainsString('-old content', $result[0]['patch']);
    }

    public function testDiffOptionalFilesReturnsEmptyWhenBothMissing(): void
    {
        $diff = new AbilityDirectoryDiff();
        self::assertSame([], $diff->diffOptionalFiles(null, null, 'none.md'));
    }

    public function testDiffDirectoriesDetectsAddedDeletedAndModified(): void
    {
        $baseDir = $this->tmpDir . '/base';
        $workDir = $this->tmpDir . '/work';
        mkdir($baseDir, 0775, true);
        mkdir($workDir, 0775, true);

        file_put_contents($baseDir . '/same.txt', "unchanged\n");
        file_put_contents($workDir . '/same.txt', "unchanged\n");
        file_put_contents($baseDir . '/modified.txt', "old\n");
        file_put_contents($workDir . '/modified.txt', "new\n");
        file_put_contents($baseDir . '/deleted.txt', "gone\n");
        file_put_contents($workDir . '/added.txt', "fresh\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffDirectories($baseDir, $workDir);

        $paths = array_column($result, 'path');
        self::assertContains('added.txt', $paths);
        self::assertContains('deleted.txt', $paths);
        self::assertContains('modified.txt', $paths);
        self::assertNotContains('same.txt', $paths);
    }

    public function testDiffDirectoriesHandlesSubdirectories(): void
    {
        $baseDir = $this->tmpDir . '/base';
        $workDir = $this->tmpDir . '/work';
        mkdir($baseDir . '/sub', 0775, true);
        mkdir($workDir . '/sub', 0775, true);
        file_put_contents($baseDir . '/sub/file.txt', "base\n");
        file_put_contents($workDir . '/sub/file.txt', "work\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffDirectories($baseDir, $workDir);

        self::assertCount(1, $result);
        self::assertSame('sub/file.txt', $result[0]['path']);
    }

    public function testDiffDirectoriesReturnsEmptyWhenBothNull(): void
    {
        $diff = new AbilityDirectoryDiff();
        self::assertSame([], $diff->diffDirectories(null, null));
    }

    public function testDiffDirectoriesHandlesOnlyBaseline(): void
    {
        $baseDir = $this->tmpDir . '/base';
        mkdir($baseDir, 0775, true);
        file_put_contents($baseDir . '/file.txt', "content\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffDirectories($baseDir, null);

        self::assertCount(1, $result);
        self::assertTrue($result[0]['deleted']);
    }

    public function testDiffDirectoriesHandlesOnlyWorkspace(): void
    {
        $workDir = $this->tmpDir . '/work';
        mkdir($workDir, 0775, true);
        file_put_contents($workDir . '/file.txt', "content\n");

        $diff = new AbilityDirectoryDiff();
        $result = $diff->diffDirectories(null, $workDir);

        self::assertCount(1, $result);
        self::assertStringContainsString('+content', $result[0]['patch']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
