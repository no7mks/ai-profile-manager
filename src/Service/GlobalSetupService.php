<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Installs Global Setup List entries to user scope only (Requirement 5).
 */
interface GlobalSetupService
{
    /**
     * @param list<string> $targets IDE keys (e.g. cursor, kiro); empty means package defaults
     *
     * @return array{lines: list<string>, exit_code: int}
     */
    public function run(bool $force, array $targets): array;
}
