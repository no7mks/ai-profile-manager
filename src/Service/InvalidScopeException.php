<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class InvalidScopeException extends \InvalidArgumentException
{
    public static function scopeOptionDeprecated(): self
    {
        return new self('The --scope option has been removed. All operations now target project scope only.');
    }

    public static function globalSetupDeprecated(): self
    {
        return new self("The global-setup command has been removed. Use 'apm bootstrap' instead.");
    }

    public static function legacyScopesField(string $entryPath): self
    {
        return new self("Legacy 'scopes' field found on entry '{$entryPath}'. Remove the scopes field — all entries are now project-only.");
    }

    public static function legacyGlobalSetupKey(): self
    {
        return new self("Legacy 'global-setup' key found. Rename to 'bootstrap'.");
    }
}
