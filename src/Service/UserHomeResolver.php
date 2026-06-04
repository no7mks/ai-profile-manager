<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Resolves the user home directory for deploy scope and Composer fallbacks.
 *
 * On Windows uses USERPROFILE (then HOMEDRIVE+HOMEPATH), ignoring HOME (e.g. Git Bash MSYS paths).
 * On Unix uses HOME.
 */
final class UserHomeResolver
{
    public function __construct(
        private readonly ?bool $isWindows = null,
    ) {
    }

    public function resolve(): string
    {
        if ($this->isWindowsPlatform()) {
            return $this->resolveWindows();
        }

        return $this->resolveUnix();
    }

    private function isWindowsPlatform(): bool
    {
        return $this->isWindows ?? PHP_OS_FAMILY === 'Windows';
    }

    private function resolveWindows(): string
    {
        $userProfile = rtrim((string) (getenv('USERPROFILE') ?: ''), "\\/");
        if ($userProfile !== '') {
            return $userProfile;
        }

        $drive = rtrim((string) (getenv('HOMEDRIVE') ?: ''), "\\/");
        $path = (string) (getenv('HOMEPATH') ?: '');
        if ($drive !== '' && $path !== '') {
            if ($path[0] !== '\\' && $path[0] !== '/') {
                $path = '\\' . $path;
            }

            return $drive . $path;
        }

        throw new \RuntimeException(
            'Unable to resolve user root: USERPROFILE is not set (and HOMEDRIVE/HOMEPATH are unavailable).',
        );
    }

    private function resolveUnix(): string
    {
        $home = (string) (getenv('HOME') ?: '');
        if ($home === '') {
            throw new \RuntimeException('Unable to resolve user root: HOME is not set.');
        }

        return $home;
    }
}
