<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class HookRegistryException extends \RuntimeException
{
    public static function invalidJson(string $path): self
    {
        return new self("Invalid JSON in hook registry: {$path}");
    }
}
