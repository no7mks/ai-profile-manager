<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

final class AbilityRegistryException extends \RuntimeException
{
    /** @param list<string> $errors */
    public static function validationErrors(array $errors): self
    {
        return new self("Ability registry validation failed:\n" . implode("\n", $errors));
    }

    public static function fileNotFound(string $path): self
    {
        return new self("Ability registry not found: {$path}");
    }

    public static function invalidYaml(string $path, string $reason): self
    {
        return new self("Invalid YAML in {$path}: {$reason}");
    }
}
