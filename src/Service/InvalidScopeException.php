<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class InvalidScopeException extends \InvalidArgumentException
{
    private const VALID_SCOPES = 'Valid scopes: project, user.';

    public static function unknownScope(?string $value): self
    {
        $display = $value === null || $value === '' ? '(empty)' : $value;

        return new self("Invalid deploy scope: {$display}. " . self::VALID_SCOPES);
    }

    public static function projectOnlyInUserScope(string $abilityRef): self
    {
        return new self(
            "Project-only ability \"{$abilityRef}\" cannot be deployed to user scope. " . self::VALID_SCOPES,
        );
    }
}
