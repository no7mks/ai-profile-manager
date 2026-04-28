<?php

declare(strict_types=1);

namespace AiProfileManager\Config;

/**
 * Resolves paths inside the installed ai-profile-manager package (repository or Composer vendor).
 */
final class PackagePaths
{
    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
