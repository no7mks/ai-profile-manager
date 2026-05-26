<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\HookInstaller;
use AiProfileManager\Service\HookRegistryException;
use PHPUnit\Framework\TestCase;

final class HookInstallerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-hook-installer-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── installKiro ─────────────────────────────────────────────────

    public function testInstallKiroCopiesFileSuccessfully(): void
    {
        $source = $this->tmpDir . '/source.kiro.hook';
        file_put_contents($source, '{"name":"test-hook","version":"1"}');

        $targetDir = $this->tmpDir . '/target/.kiro/hooks';
        mkdir($targetDir, 0775, true);
        $target = $targetDir . '/test-hook.kiro.hook';

        $installer = new HookInstaller();
        $result = $installer->installKiro($source, $target);

        self::assertSame('ok', $result['status']);
        self::assertFileExists($target);
        self::assertSame('{"name":"test-hook","version":"1"}', (string) file_get_contents($target));
    }

    public function testInstallKiroCreatesParentDirectoryAutomatically(): void
    {
        $source = $this->tmpDir . '/source.kiro.hook';
        file_put_contents($source, '{"name":"auto-dir","version":"1"}');

        $target = $this->tmpDir . '/deep/nested/dir/.kiro/hooks/auto-dir.kiro.hook';

        $installer = new HookInstaller();
        $result = $installer->installKiro($source, $target);

        self::assertSame('ok', $result['status']);
        self::assertFileExists($target);
        self::assertSame('{"name":"auto-dir","version":"1"}', (string) file_get_contents($target));
    }

    public function testInstallKiroReturnsFailWhenSourceNotExists(): void
    {
        $source = $this->tmpDir . '/nonexistent.kiro.hook';
        $target = $this->tmpDir . '/target/hook.kiro.hook';

        $installer = new HookInstaller();
        $result = $installer->installKiro($source, $target);

        self::assertSame('fail', $result['status']);
        self::assertStringContainsString($source, $result['message']);
    }

    // ─── uninstallKiro ────────────────────────────────────────────────

    public function testUninstallKiroDeletesFile(): void
    {
        $target = $this->tmpDir . '/hooks/test-hook.kiro.hook';
        mkdir(dirname($target), 0775, true);
        file_put_contents($target, '{"name":"test-hook"}');

        $installer = new HookInstaller();
        $result = $installer->uninstallKiro($target);

        self::assertSame('ok', $result['status']);
        self::assertFileDoesNotExist($target);
    }

    public function testUninstallKiroSkipsWhenFileNotExists(): void
    {
        $target = $this->tmpDir . '/hooks/nonexistent.kiro.hook';

        $installer = new HookInstaller();
        $result = $installer->uninstallKiro($target);

        self::assertSame('skip', $result['status']);
        self::assertStringContainsString($target, $result['message']);
    }

    // ─── installCursor: directory recursive copy ───────────────────────

    public function testInstallCursorCopiesDirectoryRecursively(): void
    {
        $sourceDir = $this->tmpDir . '/source/check-write-length';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/check-write-length.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/check-write-length.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/check-write-length/check-write-length.sh', 'matcher' => 'Write'],
            ],
        ]));
        mkdir($sourceDir . '/sub', 0775, true);
        file_put_contents($sourceDir . '/sub/helper.py', '# helper');

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/check-write-length';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);
        self::assertFileExists($targetDir . '/check-write-length.sh');
        self::assertFileExists($targetDir . '/check-write-length.json');
        self::assertFileExists($targetDir . '/sub/helper.py');
        self::assertSame('#!/bin/bash', file_get_contents($targetDir . '/check-write-length.sh'));
        self::assertSame('# helper', file_get_contents($targetDir . '/sub/helper.py'));
    }

    // ─── installCursor: hooks.json creation (new file) ─────────────────

    public function testInstallCursorCreatesHooksJsonWhenNotExists(): void
    {
        $sourceDir = $this->tmpDir . '/source/my-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/my-hook.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/my-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/my-hook/my-hook.sh', 'matcher' => 'Write'],
            ],
        ]));

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/my-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertFileExists($hookRegistryPath);
        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertSame(1, $data['version']);
        self::assertArrayHasKey('hooks', $data);
        self::assertArrayHasKey('preToolUse', $data['hooks']);
        self::assertCount(1, $data['hooks']['preToolUse']);
        self::assertSame('.cursor/hooks/my-hook/my-hook.sh', $data['hooks']['preToolUse'][0]['command']);
        self::assertSame('Write', $data['hooks']['preToolUse'][0]['matcher']);
    }

    // ─── installCursor: append entries to existing hooks.json ──────────

    public function testInstallCursorAppendsEntriesToExistingHooksJson(): void
    {
        $sourceDir = $this->tmpDir . '/source/new-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/new-hook.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/new-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/new-hook/new-hook.sh', 'matcher' => 'Read'],
            ],
        ]));

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/new-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        // Pre-existing hooks.json with one entry
        mkdir(dirname($hookRegistryPath), 0775, true);
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/old-hook/old-hook.sh', 'matcher' => 'Write'],
                ],
            ],
        ]));

        $installer = new HookInstaller();
        $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertCount(2, $data['hooks']['preToolUse']);
        self::assertSame('.cursor/hooks/old-hook/old-hook.sh', $data['hooks']['preToolUse'][0]['command']);
        self::assertSame('.cursor/hooks/new-hook/new-hook.sh', $data['hooks']['preToolUse'][1]['command']);
    }

    // ─── installCursor: deduplication (skip existing command) ──────────

    public function testInstallCursorSkipsDuplicateEntries(): void
    {
        $sourceDir = $this->tmpDir . '/source/dup-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/dup-hook.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/dup-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/dup-hook/dup-hook.sh', 'matcher' => 'Write'],
            ],
        ]));

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/dup-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        // Pre-existing hooks.json already has this exact command
        mkdir(dirname($hookRegistryPath), 0775, true);
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/dup-hook/dup-hook.sh', 'matcher' => 'Write'],
                ],
            ],
        ]));

        $installer = new HookInstaller();
        $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertCount(1, $data['hooks']['preToolUse']);
    }

    // ─── installCursor: source dir not exists → fail ──────────────────

    public function testInstallCursorReturnsFailWhenSourceDirMissing(): void
    {
        $sourceDir = $this->tmpDir . '/nonexistent';
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/test';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);

        self::assertSame('fail', $result['status']);
    }

    // ─── installCursor: invalid JSON in hooks.json → exception ────────

    public function testInstallCursorThrowsOnInvalidHooksJson(): void
    {
        $sourceDir = $this->tmpDir . '/source/bad-json-hook';
        mkdir($sourceDir, 0775, true);
        file_put_contents($sourceDir . '/bad-json-hook.sh', '#!/bin/bash');
        file_put_contents($sourceDir . '/bad-json-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/bad-json-hook/bad-json-hook.sh', 'matcher' => 'Write'],
            ],
        ]));

        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/bad-json-hook';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        // Write invalid JSON to hooks.json
        mkdir(dirname($hookRegistryPath), 0775, true);
        file_put_contents($hookRegistryPath, '{invalid json content!!!');

        $installer = new HookInstaller();

        $this->expectException(HookRegistryException::class);
        $this->expectExceptionMessage('Invalid JSON in hook registry');
        $installer->installCursor($sourceDir, $targetDir, $hookRegistryPath);
    }

    // ─── uninstallCursor: removes entries from hooks.json ─────────────

    public function testUninstallCursorRemovesMatchingEntries(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/my-hook';
        mkdir($targetDir, 0775, true);
        file_put_contents($targetDir . '/my-hook.sh', '#!/bin/bash');
        file_put_contents($targetDir . '/my-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/my-hook/my-hook.sh', 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        if (!is_dir(dirname($hookRegistryPath))) {
            mkdir(dirname($hookRegistryPath), 0775, true);
        }
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/my-hook/my-hook.sh', 'matcher' => 'Write'],
                    ['command' => '.cursor/hooks/other/other.sh', 'matcher' => 'Read'],
                ],
            ],
        ]));

        $installer = new HookInstaller();
        $result = $installer->uninstallCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result['status']);

        $data = json_decode((string) file_get_contents($hookRegistryPath), true);
        self::assertCount(1, $data['hooks']['preToolUse']);
        self::assertSame('.cursor/hooks/other/other.sh', $data['hooks']['preToolUse'][0]['command']);
    }

    // ─── uninstallCursor: deletes target directory ────────────────────

    public function testUninstallCursorDeletesTargetDirectory(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/del-hook';
        mkdir($targetDir . '/sub', 0775, true);
        file_put_contents($targetDir . '/del-hook.sh', '#!/bin/bash');
        file_put_contents($targetDir . '/del-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/del-hook/del-hook.sh', 'matcher' => 'Write'],
            ],
        ]));
        file_put_contents($targetDir . '/sub/helper.py', '# helper');

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        if (!is_dir(dirname($hookRegistryPath))) {
            mkdir(dirname($hookRegistryPath), 0775, true);
        }
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/del-hook/del-hook.sh', 'matcher' => 'Write'],
                ],
            ],
        ]));

        $installer = new HookInstaller();
        $installer->uninstallCursor($targetDir, $hookRegistryPath);

        self::assertDirectoryDoesNotExist($targetDir);
    }

    // ─── uninstallCursor: target dir not exists → skip ────────────────

    public function testUninstallCursorReturnsSkipWhenTargetDirMissing(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/nonexistent';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $installer = new HookInstaller();
        $result = $installer->uninstallCursor($targetDir, $hookRegistryPath);

        self::assertSame('skip', $result['status']);
    }

    // ─── uninstallCursor: invalid JSON in hooks.json → exception ──────

    public function testUninstallCursorThrowsOnInvalidHooksJson(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/bad-hook';
        mkdir($targetDir, 0775, true);
        file_put_contents($targetDir . '/bad-hook.sh', '#!/bin/bash');
        file_put_contents($targetDir . '/bad-hook.json', json_encode([
            'preToolUse' => [
                ['command' => '.cursor/hooks/bad-hook/bad-hook.sh', 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        if (!is_dir(dirname($hookRegistryPath))) {
            mkdir(dirname($hookRegistryPath), 0775, true);
        }
        file_put_contents($hookRegistryPath, 'not valid json {{{');

        $installer = new HookInstaller();

        $this->expectException(HookRegistryException::class);
        $this->expectExceptionMessage('Invalid JSON in hook registry');
        $installer->uninstallCursor($targetDir, $hookRegistryPath);
    }

    // ─── HookRegistryException ────────────────────────────────────────

    public function testHookRegistryExceptionInvalidJsonMessage(): void
    {
        $ex = HookRegistryException::invalidJson('/path/to/hooks.json');
        self::assertSame('Invalid JSON in hook registry: /path/to/hooks.json', $ex->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $ex);
    }

    // ─── Helper ───────────────────────────────────────────────────────

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }
        rmdir($dir);
    }
}
