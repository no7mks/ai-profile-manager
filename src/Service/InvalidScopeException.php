<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class InvalidScopeException extends \InvalidArgumentException
{
    public static function unknownScope(?string $value): self
    {
        $display = $value === null || $value === '' ? '(empty)' : $value;

        return new self("Invalid deploy scope: {$display}. Valid scopes: project, user.");
    }
}
