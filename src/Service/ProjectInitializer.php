<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use AiProfileManager\Config\PackagePaths;
use RuntimeException;

/**
 * Copies the bundled project scaffold (docs, issues, AGENTS.md, PROJECT.md) and optional platform scope rules.
 */
final class ProjectInitializer
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly DirectoryMirrorService $mirror = new DirectoryMirrorService(),
        private readonly ProjectProfileRenderer $profileRenderer = new ProjectProfileRenderer(),
    ) {
    }

    public static function fromPackageLayout(): self
    {
        return new self(PackagePaths::packageRoot());
    }

    /**
     * @param array<int, string> $targets IDE targets (cursor, kiro). Empty means skip installing scope rules.
     * @param array<string, array{detected: string, confirmed: string, confidence: 'high'|'medium'|'low'}>|null $projectProfile
     *
     * @return array<int, string>
     */
    public function init(string $targetDir, bool $force, array $targets, ?array $projectProfile = null): array
    {
        $lines = [];
        $this->prepareTargetDirectory($targetDir);
        $targetDir = $this->normalizedAbsolute($targetDir);

        $scaffoldRoot = $this->join($this->packageRoot, 'scaffold');
        $this->assertPathExists($scaffoldRoot, 'Internal package layout error: scaffold directory missing.');

        $this->assertScaffoldSourcesPresent($scaffoldRoot);

        if (!$force && $this->scaffoldTargetsExist($targetDir)) {
            throw new RuntimeException(
                'Target already contains docs/, issues/, AGENTS.md, or PROJECT.md. Pass --force to overwrite.'
            );
        }

        $lines[] = 'Installing scaffold (docs/, issues/, AGENTS.md, PROJECT.md)...';
        $this->mirror->mirrorDirectory($this->join($scaffoldRoot, 'docs'), $this->join($targetDir, 'docs'));
        $this->mirror->mirrorDirectory($this->join($scaffoldRoot, 'issues'), $this->join($targetDir, 'issues'));
        $this->mirror->copyFile(
            $this->join($scaffoldRoot, 'AGENTS.md'),
            $this->join($targetDir, 'AGENTS.md'),
            $force
        );
        $profile = $projectProfile ?? ProjectProfileRenderer::unknownProfile();
        $this->writeProjectProfile($targetDir, $force, $profile);
        $lines[] = '[ok] Scaffold installed at ' . $targetDir;

        $lines = array_merge($lines, $this->installScopeRules($targetDir, $force, $targets));

        return $lines;
    }

    /**
     * @param array<int, string> $targets
     *
     * @return array<int, string>
     */
    private function installScopeRules(string $targetDir, bool $force, array $targets): array
    {
        if ($targets === []) {
            return ['Skipping scope rules (no targets).'];
        }

        $lines = [];
        $lines[] = 'Installing scope rules for targets: ' . implode(', ', $targets);
        $rulesRoot = $this->join($this->packageRoot, 'abilities', 'rules');
        $this->assertPathExists($rulesRoot, 'Internal package layout error: abilities/rules missing.');

        if (in_array('cursor', $targets, true)) {
            $src = $this->join($rulesRoot, 'cursor-scope.cursor.mdc');
            $dst = $this->join($targetDir, '.cursor', 'rules', 'cursor-scope.mdc');
            $this->assertPathExists($src, 'Internal package layout error: cursor-scope bundle missing.');
            if (!$force && is_file($dst)) {
                throw new RuntimeException(
                    'Target already has .cursor/rules/cursor-scope.mdc. Pass --force to overwrite.'
                );
            }
            $this->mirror->ensureDirectory(dirname($dst));
            $this->mirror->copyFile($src, $dst, true);
            $lines[] = '[ok] Installed Cursor scope rule -> .cursor/rules/cursor-scope.mdc';
        }

        if (in_array('kiro', $targets, true)) {
            $src = $this->join($rulesRoot, 'kiro-scope.kiro.md');
            $dst = $this->join($targetDir, '.kiro', 'steering', 'kiro-scope.md');
            $this->assertPathExists($src, 'Internal package layout error: kiro-scope bundle missing.');
            if (!$force && is_file($dst)) {
                throw new RuntimeException(
                    'Target already has .kiro/steering/kiro-scope.md. Pass --force to overwrite.'
                );
            }
            $this->mirror->ensureDirectory(dirname($dst));
            $this->mirror->copyFile($src, $dst, true);
            $lines[] = '[ok] Installed Kiro scope steering -> .kiro/steering/kiro-scope.md';
        }

        return $lines;
    }

    private function prepareTargetDirectory(string $targetDir): void
    {
        if (is_dir($targetDir)) {
            return;
        }
        if (file_exists($targetDir)) {
            throw new RuntimeException(sprintf('Path exists and is not a directory: %s', $targetDir));
        }
        $this->mirror->ensureDirectory($targetDir);
    }

    private function normalizedAbsolute(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException(sprintf('Not a directory: %s', $path));
        }

        return $resolved;
    }

    private function assertPathExists(string $path, string $message): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertScaffoldSourcesPresent(string $scaffoldRoot): void
    {
        foreach (['docs', 'issues', 'AGENTS.md', 'PROJECT.md'] as $leaf) {
            $p = $this->join($scaffoldRoot, $leaf);
            if (!file_exists($p)) {
                throw new RuntimeException('Internal package layout error: scaffold/' . $leaf . ' missing.');
            }
        }
    }

    private function scaffoldTargetsExist(string $targetDir): bool
    {
        return file_exists($this->join($targetDir, 'docs'))
            || file_exists($this->join($targetDir, 'issues'))
            || file_exists($this->join($targetDir, 'AGENTS.md'))
            || file_exists($this->join($targetDir, 'PROJECT.md'));
    }

    private function join(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * @param array<string, array{detected: string, confirmed: string, confidence: 'high'|'medium'|'low'}> $projectProfile
     */
    private function writeProjectProfile(string $targetDir, bool $force, array $projectProfile): void
    {
        $target = $this->join($targetDir, 'PROJECT.md');
        if (!$force && file_exists($target)) {
            throw new RuntimeException('Target already has PROJECT.md. Pass --force to overwrite.');
        }

        $content = $this->profileRenderer->renderMarkdown($projectProfile);
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException(sprintf('Failed to write %s', $target));
        }
    }
}
