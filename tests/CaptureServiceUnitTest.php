<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;

final class CaptureServiceUnitTest extends TestCase
{
    public function testDiscoverWorkspaceAbilitiesListsSubdirectories(): void
    {
        $root = sys_get_temp_dir() . '/aipm-disc-' . bin2hex(random_bytes(4));
        mkdir($root . '/abilities/skills/s-a/cursor', 0775, true);
        mkdir($root . '/abilities/rules/r-b/cursor', 0775, true);
        mkdir($root . '/abilities/agents/a-c/cursor', 0775, true);

        $svc = new CaptureService(new CheckService());
        $discovered = $svc->discoverWorkspaceAbilities($root);

        self::assertSame(['s-a'], $discovered['skills']);
        self::assertSame(['r-b'], $discovered['rules']);
        self::assertSame(['a-c'], $discovered['agents']);
    }

    public function testCaptureTypedReturnsErrorWhenBaselineUnresolved(): void
    {
        $composerHome = sys_get_temp_dir() . '/aipm-no-pkg-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['packages' => []]));

        $oldCh = getenv('COMPOSER_HOME');
        $oldBl = getenv('AIPM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('AIPM_BASELINE_ROOT');

        $svc = new CaptureService(new CheckService());
        $result = $svc->captureTyped([
            'skills' => ['x'],
            'rules' => [],
            'agents' => [],
        ], ['cursor'], sys_get_temp_dir());

        if ($oldCh === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldCh);
        }
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(1, $result['exit_code']);
        self::assertNull($result['baseline']);
        self::assertStringContainsString('Could not resolve Composer baseline', implode("\n", $result['lines']));
    }

    public function testBuildCaptureEventIncludesBaselineReferenceWhenPresent(): void
    {
        $svc = new CaptureService(new CheckService());
        $event = $svc->buildCaptureEvent(
            [[
                'type' => 'skill',
                'name' => 'n',
                'target' => 'cursor',
                'status' => 'modified',
                'content_hash' => 'h',
                'files' => [['path' => 'f', 'content' => 'c', 'patch' => 'p']],
            ]],
            'org/r',
            'sha',
            '',
            'e1',
            gmdate(DATE_ATOM),
            [
                'package' => 'no7mks/ai-profile-manager',
                'version' => '1.0.0',
                'install_path' => '/x',
                'reference' => 'ref123',
            ],
        );

        self::assertSame('ref123', $event['base_ref']);
        self::assertSame('ref123', $event['baseline']['reference'] ?? null);
    }
}
