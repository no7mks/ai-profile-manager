<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Support;

use PHPUnit\Framework\Attributes\After;

/**
 * Automatically saves and restores environment variables around each test method.
 *
 * Tracks any env vars modified via the withEnv() helper and restores them in #[After],
 * ensuring no leakage between tests even if assertions fail mid-method.
 */
trait RestoresEnvTrait
{
    /** @var array<string, string|false> Original values keyed by env var name */
    private array $savedEnvVars = [];

    /**
     * Set an environment variable and register it for automatic restoration.
     *
     * @param string $key   Environment variable name
     * @param string|null $value  Value to set, or null to unset
     */
    protected function withEnv(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->savedEnvVars)) {
            $this->savedEnvVars[$key] = getenv($key);
        }

        if ($value === null || $value === '') {
            putenv($key);
        } else {
            putenv("{$key}={$value}");
        }
    }

    #[After]
    protected function restoreEnvVars(): void
    {
        foreach ($this->savedEnvVars as $key => $original) {
            if ($original === false) {
                putenv($key);
            } else {
                putenv("{$key}={$original}");
            }
        }
        $this->savedEnvVars = [];
    }
}
