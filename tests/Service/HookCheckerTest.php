<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\HookChecker;
use PHPUnit\Framework\TestCase;

final class HookCheckerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-hook-checker-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── checkKiro: ok ───────────────────────────────────────────────

    public function testCheckKiroReturnsOkWhenFilesMatch(): void
    {
        $content = '{"name":"test-hook","version":"1","when":{"type":"preToolUse"},"then":{"type":"runCommand"}}';
        $source = $this->tmpDir . '/source/test-hook.kiro.hook';
        $target = $this->tmpDir . '/target/.kiro/hooks/test-hook.kiro.hook';

        mkdir(dirname($source), 0775, true);
        mkdir(dirname($target), 0775, true);
        file_put_contents($source, $content);
        file_put_contents($target, $content);

        $checker = new HookChecker();
        $result = $checker->checkKiro($source, $target);

        self::assertSame('ok', $result);
    }

    // ─── checkKiro: drift ────────────────────────────────────────────

    public function testCheckKiroReturnsDriftWhenFilesContentDiffers(): void
    {
        $source = $this->tmpDir . '/source/test-hook.kiro.hook';
        $target = $this->tmpDir . '/target/.kiro/hooks/test-hook.kiro.hook';

        mkdir(dirname($source), 0775, true);
        mkdir(dirname($target), 0775, true);
        file_put_contents($source, '{"name":"test-hook","version":"1"}');
        file_put_contents($target, '{"name":"test-hook","version":"2"}');

        $checker = new HookChecker();
        $result = $checker->checkKiro($source, $target);

        self::assertSame('drift', $result);
    }

    // ─── checkKiro: missing ──────────────────────────────────────────

    public function testCheckKiroReturnsMissingWhenTargetNotExists(): void
    {
        $source = $this->tmpDir . '/source/test-hook.kiro.hook';
        $target = $this->tmpDir . '/target/.kiro/hooks/nonexistent.kiro.hook';

        mkdir(dirname($source), 0775, true);
        file_put_contents($source, '{"name":"test-hook","version":"1"}');

        $checker = new HookChecker();
        $result = $checker->checkKiro($source, $target);

        self::assertSame('missing', $result);
    }

    // ─── checkCursor: ok ─────────────────────────────────────────────

    public function testCheckCursorReturnsOkWhenAllChecksPass(): void
    {
        $name = 'check-write-length';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
                ],
            ],
        ]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result);
    }

    // ─── checkCursor: missing — directory not exists ──────────────────

    public function testCheckCursorReturnsMissingWhenDirectoryNotExists(): void
    {
        $targetDir = $this->tmpDir . '/workspace/.cursor/hooks/nonexistent';
        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('missing', $result);
    }

    // ─── checkCursor: missing — entry script not exists ───────────────

    public function testCheckCursorReturnsMissingWhenEntryScriptNotExists(): void
    {
        $name = 'my-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
                ],
            ],
        ]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('missing', $result);
    }

    // ─── checkCursor: missing — hooks.json entry not exists ───────────

    public function testCheckCursorReturnsMissingWhenHooksJsonEntryNotExists(): void
    {
        $name = 'my-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => '.cursor/hooks/other-hook/other-hook.sh', 'matcher' => 'Read'],
                ],
            ],
        ]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('missing', $result);
    }

    // ─── checkCursor: missing — hooks.json file not exists ────────────

    public function testCheckCursorReturnsMissingWhenHooksJsonFileNotExists(): void
    {
        $name = 'my-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/nonexistent-hooks.json';

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('missing', $result);
    }

    // ─── checkCursor: ok with multiple event types ────────────────────

    public function testCheckCursorReturnsOkWithMultipleEventTypes(): void
    {
        $name = 'multi-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
            'postToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Read'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
                ],
                'postToolUse' => [
                    ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Read'],
                ],
            ],
        ]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('ok', $result);
    }

    // ─── checkCursor: missing — one event type entry missing ──────────

    public function testCheckCursorReturnsMissingWhenOneEventTypeEntryMissing(): void
    {
        $name = 'multi-hook';
        $targetDir = $this->tmpDir . "/workspace/.cursor/hooks/{$name}";
        mkdir($targetDir, 0775, true);

        file_put_contents("{$targetDir}/{$name}.sh", '#!/bin/bash');
        file_put_contents("{$targetDir}/{$name}.json", json_encode([
            'preToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
            ],
            'postToolUse' => [
                ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Read'],
            ],
        ]));

        $hookRegistryPath = $this->tmpDir . '/workspace/.cursor/hooks.json';
        file_put_contents($hookRegistryPath, json_encode([
            'version' => 1,
            'hooks' => [
                'preToolUse' => [
                    ['command' => ".cursor/hooks/{$name}/{$name}.sh", 'matcher' => 'Write'],
                ],
            ],
        ]));

        $checker = new HookChecker();
        $result = $checker->checkCursor($targetDir, $hookRegistryPath);

        self::assertSame('missing', $result);
    }

    // ─── Helper ───────────────────────────────────────────────────────

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
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
