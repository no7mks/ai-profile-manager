<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Automatically saves and restores the working directory around each test method.
 *
 * Use this trait in test classes that call chdir() within individual test methods
 * to ensure the cwd is always restored even if the test fails mid-execution.
 */
trait RestoresCwdTrait
{
    private string $originalCwd;

    #[Before]
    protected function saveCwd(): void
    {
        $this->originalCwd = (string) getcwd();
    }

    #[After]
    protected function restoreCwd(): void
    {
        if (isset($this->originalCwd) && $this->originalCwd !== '') {
            chdir($this->originalCwd);
        }
    }
}
