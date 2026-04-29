<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\AbilityDiffService;
use PHPUnit\Framework\TestCase;

final class AbilityDiffServiceTest extends TestCase
{
    public function testCaptureAndInstalledDiffUseConsistentModifiedSemantics(): void
    {
        $baseline = sys_get_temp_dir() . '/apm-diff-base-' . bin2hex(random_bytes(4));
        $workspace = sys_get_temp_dir() . '/apm-diff-work-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/demo', 0775, true);
        mkdir($workspace . '/abilities/skills/demo', 0775, true);
        mkdir($workspace . '/.cursor/skills/demo', 0775, true);
        file_put_contents($baseline . '/abilities/skills/demo/SKILL.md', "base\n");
        file_put_contents($workspace . '/abilities/skills/demo/SKILL.md', "cap-modified\n");
        file_put_contents($workspace . '/.cursor/skills/demo/SKILL.md', "check-modified\n");

        $svc = new AbilityDiffService();
        $items = ['skills' => ['demo'], 'rules' => [], 'agents' => []];
        $capture = $svc->diffForCapture($items, ['cursor'], $baseline, $workspace);
        $check = $svc->diffForInstalledTargets($items, ['cursor'], $baseline, $workspace);

        self::assertSame('modified', $capture[0]['status']);
        self::assertSame('modified', $check[0]['status']);
        self::assertNotEmpty($capture[0]['files']);
        self::assertNotEmpty($check[0]['files']);
    }
}
