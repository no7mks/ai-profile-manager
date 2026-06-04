<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\InvalidScopeException;
use PHPUnit\Framework\TestCase;

final class InvalidScopeExceptionTest extends TestCase
{
    public function testUnknownScopeIncludesInvalidValueAndValidScopes(): void
    {
        $exception = InvalidScopeException::unknownScope('bogus');

        self::assertStringContainsString('Invalid deploy scope: bogus', $exception->getMessage());
        self::assertStringContainsString('Valid scopes: project, user', $exception->getMessage());
    }

    public function testUnknownScopeEmptyStringShowsEmptyPlaceholder(): void
    {
        $exception = InvalidScopeException::unknownScope('');

        self::assertStringContainsString('Invalid deploy scope: (empty)', $exception->getMessage());
        self::assertStringContainsString('Valid scopes: project, user', $exception->getMessage());
    }

    public function testUnknownScopeNullShowsEmptyPlaceholder(): void
    {
        $exception = InvalidScopeException::unknownScope(null);

        self::assertStringContainsString('Invalid deploy scope: (empty)', $exception->getMessage());
        self::assertStringContainsString('Valid scopes: project, user', $exception->getMessage());
    }

    public function testProjectOnlyInUserScopeNamesAbilityAndValidScopes(): void
    {
        $exception = InvalidScopeException::projectOnlyInUserScope('cursor-scope');

        self::assertStringContainsString('cursor-scope', $exception->getMessage());
        self::assertStringContainsString('Valid scopes: project, user', $exception->getMessage());
        self::assertStringContainsString('user', $exception->getMessage());
        self::assertStringContainsString('Project-only', $exception->getMessage());
    }
}
