<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\DeployScope;

/**
 * Installs {@see AbilityRegistry::globalSetupIncludes()} entries to user scope only.
 */
final class DefaultGlobalSetupService implements GlobalSetupService
{
    private readonly InstallationProbe $probe;

    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly string $packageRoot,
        ?InstallationProbe $probe = null,
        private readonly DeployRootResolver $rootResolver = new DeployRootResolver(),
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
        private readonly HookInstaller $hookInstaller = new HookInstaller(),
    ) {
        $this->probe = $probe ?? new InstallationProbe($this->registry, $this->rootResolver);
    }

    /**
     * @param list<string> $targets
     *
     * @return array{lines: list<string>, exit_code: int}
     */
    public function run(bool $force, array $targets): array
    {
        $scope = DeployScope::User;
        $includes = $this->registry->globalSetupIncludes();
        $lines = [];
        $exitCode = 0;
        $installedOrUpdated = 0;

        foreach ($includes as $include) {
            $type = $include['type'];
            $path = $include['path'];

            foreach ($targets as $target) {
                $entry = $this->registry->getEntry($type, $path);
                if ($entry === null) {
                    $lines[] = sprintf('[fail] Missing registry entry for %s:%s', $type, $path);
                    $exitCode = 1;

                    continue;
                }

                if (!isset($entry->targets[$target])) {
                    $lines[] = sprintf(
                        '[skip] %s:%s (%s): no %s target in registry',
                        $type,
                        $path,
                        $target,
                        $target,
                    );

                    continue;
                }

                if ($this->probe->isPresent($type, $path, $target, $scope) && !$force) {
                    $lines[] = sprintf('[ok] %s:%s (user) %s — already up to date', $type, $path, $target);

                    continue;
                }

                $applyLines = $this->applyInstall($type, $path, $target, $scope);
                foreach ($applyLines as $line) {
                    $lines[] = $line;
                    if (str_starts_with($line, '[fail]')) {
                        $exitCode = 1;
                    } elseif (str_starts_with($line, '[ok]')) {
                        ++$installedOrUpdated;
                    }
                }
            }
        }

        if ($exitCode === 0 && $lines !== []) {
            $hasAction = $installedOrUpdated > 0
                || $this->hasOnlyIdempotentLines($lines);

            if ($hasAction) {
                $lines[] = '';
                $lines[] = 'Next step: open a business repository and run /apm init.';
            }
        } elseif ($exitCode === 0 && $lines === []) {
            $lines[] = 'Global setup complete.';
            $lines[] = 'Next: run /apm init in your business repository.';
        }

        return ['lines' => $lines, 'exit_code' => $exitCode];
    }

    /**
     * @param list<string> $lines
     */
    private function hasOnlyIdempotentLines(array $lines): bool
    {
        foreach ($lines as $line) {
            if (str_contains($line, 'already up to date') || str_starts_with($line, '[skip]')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function applyInstall(string $type, string $name, string $target, DeployScope $scope): array
    {
        $entry = $this->registry->getEntry($type, $name);
        if ($entry === null || !isset($entry->targets[$target])) {
            return [sprintf('[fail] Missing registry entry for %s:%s on %s', $type, $name, $target)];
        }

        $relativePath = $entry->targets[$target];
        $workspaceRoot = $this->rootResolver->resolve($scope);

        if ($type === 'hook') {
            return $this->applyHookInstall($name, $target, $workspaceRoot);
        }

        $wasPresent = $this->probe->isPresent($type, $name, $target, $scope);

        $src = $this->packageRoot . '/' . $relativePath;
        $dst = $this->rootResolver->absoluteTargetPath($scope, $relativePath);

        try {
            if ($type === 'skill') {
                if (!is_dir($src)) {
                    return [sprintf('[fail] Baseline skill missing: %s', $src)];
                }
                $this->mirror->mirrorDirectory($src, $dst);
            } else {
                if (!is_file($src)) {
                    return [sprintf('[fail] Baseline file missing: %s', $src)];
                }
                $this->mirror->ensureDirectory(dirname($dst));
                $this->mirror->copyFile($src, $dst, true);
            }
        } catch (\Throwable $e) {
            return [sprintf('[fail] Install %s:%s (user) %s: %s', $type, $name, $target, $e->getMessage())];
        }

        $verb = $wasPresent ? 'Updated' : 'Installed';

        return [sprintf('[ok] %s %s:%s (user) %s', $verb, $type, $name, $target)];
    }

    /**
     * @return list<string>
     */
    private function applyHookInstall(string $hookName, string $target, string $workspaceRoot): array
    {
        if ($target === 'kiro') {
            $sourcePath = $this->packageRoot . '/hooks/' . $hookName . '.kiro.hook';
            $targetPath = $workspaceRoot . '/.kiro/hooks/' . $hookName . '.kiro.hook';
            $result = $this->hookInstaller->installKiro($sourcePath, $targetPath);
        } else {
            $sourceDir = $this->packageRoot . '/hooks/' . $hookName;
            $targetDir = $workspaceRoot . '/.cursor/hooks/' . $hookName;
            $hookRegistryPath = $workspaceRoot . '/.cursor/hooks.json';
            $result = $this->hookInstaller->installCursor($sourceDir, $targetDir, $hookRegistryPath);
        }

        if ($result['status'] === 'fail') {
            return [sprintf('[fail] Hook %s -> %s: %s', $hookName, $target, $result['message'])];
        }

        return [sprintf('[ok] Installed hook:%s (user) %s', $hookName, $target)];
    }
}
