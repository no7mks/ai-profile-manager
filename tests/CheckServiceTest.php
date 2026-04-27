<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\CheckService;
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
    }
}
