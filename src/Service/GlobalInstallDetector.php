<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Detects whether the CLI was invoked from a Composer global vendor/bin entry point.
 */
class GlobalInstallDetector
{
    /**
     * @param list<string>|null $composerHomesOverride For tests only; when set, replaces {@see ComposerBaselineResolver::candidateComposerHomes()}.
     */
    public function __construct(
        private readonly ComposerBaselineResolver $baselineResolver = new ComposerBaselineResolver(),
        private readonly ?array $composerHomesOverride = null,
    ) {
    }

    public function isGlobalInvocation(?string $argv0 = null): bool
    {
        $argv0 ??= $_SERVER['argv'][0] ?? '';
        if ($argv0 === '') {
            return false;
        }

        $resolved = realpath($argv0);
        $executable = $resolved !== false ? $resolved : $argv0;

        $homes = $this->composerHomesOverride ?? $this->baselineResolver->candidateComposerHomes();
        foreach ($homes as $composerHome) {
            $globalBinDir = rtrim($composerHome, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . 'bin';
            $resolvedBinDir = realpath($globalBinDir);
            $prefix = ($resolvedBinDir !== false ? $resolvedBinDir : $globalBinDir) . DIRECTORY_SEPARATOR;
            if (str_starts_with($executable, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
