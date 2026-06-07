<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\InvalidScopeException;
use PHPUnit\Framework\TestCase;

final class InvalidScopeExceptionTest extends TestCase
{
    public function testScopeOptionDeprecatedMessage(): void
    {
        $exception = InvalidScopeException::scopeOptionDeprecated();

        self::assertSame(
            'The --scope option has been removed. All operations now target project scope only.',
            $exception->getMessage(),
        );
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testGlobalSetupDeprecatedMessage(): void
    {
        $exception = InvalidScopeException::globalSetupDeprecated();

        self::assertSame(
            "The global-setup command has been removed. Use 'apm bootstrap' instead.",
            $exception->getMessage(),
        );
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testLegacyScopesFieldIncludesEntryPath(): void
    {
        $exception = InvalidScopeException::legacyScopesField('skill:my-skill');

        self::assertSame(
            "Legacy 'scopes' field found on entry 'skill:my-skill'. Remove the scopes field — all entries are now project-only.",
            $exception->getMessage(),
        );
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testLegacyGlobalSetupKeyMessage(): void
    {
        $exception = InvalidScopeException::legacyGlobalSetupKey();

        self::assertSame(
            "Legacy 'global-setup' key found. Rename to 'bootstrap'.",
            $exception->getMessage(),
        );
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
