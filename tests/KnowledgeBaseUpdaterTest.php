<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\KnowledgeBaseUpdater;
use PHPUnit\Framework\TestCase;

final class KnowledgeBaseUpdaterTest extends TestCase
{
    public function testUpdateWritesSnapshotUnderHomeConfig(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);

        $old = getenv('HOME');
        try {
            putenv('HOME=' . $home);

            $updater = new KnowledgeBaseUpdater();
            $path = $updater->update(['s'], ['r'], ['a'], ['p'], ['cursor']);
        } finally {
            if ($old === false) {
                putenv('HOME');
            } else {
                putenv('HOME=' . $old);
            }
        }

        self::assertSame($home . '/.config/apm/knowledge-base.json', $path);
        self::assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['schema_version'] ?? null);
        self::assertSame(['s'], $decoded['skills'] ?? null);
    }
}
