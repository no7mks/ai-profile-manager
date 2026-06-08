<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

/**
 * Resolves the globally installed Composer package directory and metadata from vendor/composer/installed.json.
 */
final class ComposerBaselineResolver
{
    /**
     * @param ?string $overrideInstallPath When set (DI / tests), skip installed.json after env check.
     */
    public function __construct(
        private readonly string $packageName = 'no7mks/ai-profile-manager',
        private readonly ?string $overrideInstallPath = null,
        private readonly ?bool $isWindows = null,
    ) {
    }

    /**
     * @return array{
     *     package: string,
     *     version: string,
     *     install_path: string,
     *     reference?: string
     * }|null
     */
    public function resolve(): ?array
    {
        $envRoot = (string) (getenv('APM_BASELINE_ROOT') ?: '');
        if ($envRoot !== '' && is_dir($envRoot)) {
            $real = realpath($envRoot);

            return [
                'package' => $this->packageName,
                'version' => 'env',
                'install_path' => $real !== false ? $real : $envRoot,
            ];
        }

        if ($this->overrideInstallPath !== null && is_dir($this->overrideInstallPath)) {
            $real = realpath($this->overrideInstallPath);

            return [
                'package' => $this->packageName,
                'version' => 'override',
                'install_path' => $real !== false ? $real : $this->overrideInstallPath,
            ];
        }

        $installedPath = $this->installedJsonPath();
        if ($installedPath === null || !is_readable($installedPath)) {
            return null;
        }

        $raw = file_get_contents($installedPath);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $packages */
        $packages = isset($decoded['packages']) && is_array($decoded['packages'])
            ? $decoded['packages']
            : [];

        foreach ($packages as $pkg) {
            if (($pkg['name'] ?? '') !== $this->packageName) {
                continue;
            }

            $vendorDir = dirname($installedPath, 2);
            $relativeInstall = str_replace('/', DIRECTORY_SEPARATOR, $this->packageName);
            $installPath = $vendorDir . DIRECTORY_SEPARATOR . $relativeInstall;
            $installPath = realpath($installPath) ?: $installPath;

            $version = is_string($pkg['version'] ?? null) ? $pkg['version'] : '';
            $reference = null;
            if (isset($pkg['dist']) && is_array($pkg['dist']) && isset($pkg['dist']['reference']) && is_string($pkg['dist']['reference'])) {
                $reference = $pkg['dist']['reference'];
            } elseif (isset($pkg['source']) && is_array($pkg['source']) && isset($pkg['source']['reference']) && is_string($pkg['source']['reference'])) {
                $reference = $pkg['source']['reference'];
            }

            $out = [
                'package' => $this->packageName,
                'version' => $version !== '' ? $version : '0',
                'install_path' => $installPath,
            ];

            if ($reference !== null && $reference !== '') {
                $out['reference'] = $reference;
            }

            /** @var array{package: string, version: string, install_path: string, reference?: string} */
            return $out;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function candidateComposerHomes(): array
    {
        $composerHome = rtrim((string) (getenv('COMPOSER_HOME') ?: ''), DIRECTORY_SEPARATOR);
        if ($composerHome !== '') {
            return [$composerHome];
        }

        if ($this->isWindowsPlatform()) {
            return $this->windowsComposerHomeCandidates();
        }

        $home = $this->resolveUserHome();
        if ($home === null) {
            return [];
        }

        return [
            $home . DIRECTORY_SEPARATOR . '.composer',
            $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'composer',
        ];
    }

    /**
     * @return list<string>
     */
    private function windowsComposerHomeCandidates(): array
    {
        $candidates = [];

        $appData = rtrim((string) (getenv('APPDATA') ?: ''), DIRECTORY_SEPARATOR);
        if ($appData !== '') {
            $candidates[] = $appData . DIRECTORY_SEPARATOR . 'Composer';
        }

        try {
            $userHome = $this->resolveWindowsUserHome();
            if ($userHome !== null) {
                $candidates[] = $userHome . DIRECTORY_SEPARATOR . '.composer';
            }
        } catch (\RuntimeException) {
            // USERPROFILE / HOMEDRIVE+HOMEPATH unavailable — APPDATA-only fallback remains.
        }

        return $candidates;
    }

    private function isWindowsPlatform(): bool
    {
        return $this->isWindows ?? PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Resolves user home directory (Unix: HOME env; Windows: USERPROFILE or HOMEDRIVE+HOMEPATH).
     */
    private function resolveUserHome(): ?string
    {
        if ($this->isWindowsPlatform()) {
            return $this->resolveWindowsUserHome();
        }

        $home = (string) (getenv('HOME') ?: '');

        return $home !== '' ? $home : null;
    }

    /**
     * Resolves user home on Windows (USERPROFILE, then HOMEDRIVE+HOMEPATH).
     */
    private function resolveWindowsUserHome(): ?string
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

        return null;
    }

    private function installedJsonPath(): ?string
    {
        foreach ($this->candidateComposerHomes() as $composerHome) {
            $path = $composerHome . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
