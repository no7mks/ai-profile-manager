<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;

final class CheckServiceTest extends TestCase
{
    public function testCheckTypedReturnsUnknownPlaceholderResults(): void
    {
        $service = new CheckService();

        $results = $service->checkTyped([
            'skills' => ['graphify'],
            'rules' => ['spec-core'],
            'agents' => ['gatekeeper'],
        ], ['cursor']);

        self::assertCount(3, $results);
        foreach ($results as $result) {
            self::assertSame('unknown', $result['status']);
        }
    }

    public function testEvaluateExitCodeReturnsTwoForModifiedOrMissing(): void
    {
        $service = new CheckService();

        $exitCode = $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'graphify', 'target' => 'cursor', 'status' => 'modified'],
        ]);

        self::assertSame(2, $exitCode);

        self::assertSame(2, $service->evaluateExitCode([
            ['type' => 'rule', 'name' => 'spec-core', 'target' => 'cursor', 'status' => 'missing'],
        ]));
    }

    public function testEvaluateExitCodeReturnsZeroWhenNoModifiedOrMissing(): void
    {
        $service = new CheckService();

        self::assertSame(0, $service->evaluateExitCode([]));

        self::assertSame(0, $service->evaluateExitCode([
            ['type' => 'skill', 'name' => 'x', 'target' => 'cursor', 'status' => 'unchanged'],
            ['type' => 'skill', 'name' => 'y', 'target' => 'cursor', 'status' => 'unknown'],
        ]));
    }

    public function testRenderResultsPrefixesByStatus(): void
    {
        $service = new CheckService();
        $lines = $service->renderResults([
            ['type' => 'skill', 'name' => 'a', 'target' => 'cursor', 'status' => 'unchanged'],
            ['type' => 'rule', 'name' => 'b', 'target' => 'cursor', 'status' => 'modified'],
            ['type' => 'agent', 'name' => 'c', 'target' => 'cursor', 'status' => 'missing'],
            ['type' => 'skill', 'name' => 'd', 'target' => 'cursor', 'status' => 'unknown'],
        ]);

        self::assertStringContainsString('[ok]', $lines[0]);
        self::assertStringContainsString('[drift]', $lines[1]);
        self::assertStringContainsString('[miss]', $lines[2]);
        self::assertStringContainsString('[todo]', $lines[3]);
    }
}
