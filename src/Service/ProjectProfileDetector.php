<?php

declare(strict_types=1);

namespace AiProfileManager\Service;

use RuntimeException;

/**
 * Best-effort project profile detector for PROJECT.md prefill.
 *
 * @phpstan-type Field array{detected: string, confidence: 'high'|'medium'|'low'}
 * @phpstan-type Profile array{
 *   project_stack: Field,
 *   full_test_command: Field,
 *   build_command: Field,
 *   run_entry: Field,
 *   version_locations: Field,
 *   sensitive_files: Field
 * }
 */
final class ProjectProfileDetector
{
    /**
     * @return Profile
     */
    public function detect(string $projectRoot): array
    {
        if (!is_dir($projectRoot)) {
            throw new RuntimeException(sprintf('Not a directory: %s', $projectRoot));
        }

        $packageJson = $this->readJson($this->join($projectRoot, 'package.json'));
        $composerJson = $this->readJson($this->join($projectRoot, 'composer.json'));
        $makefile = $this->readText($this->join($projectRoot, 'Makefile'));
        $pyproject = $this->readText($this->join($projectRoot, 'pyproject.toml'));
        $cargoToml = $this->readText($this->join($projectRoot, 'Cargo.toml'));
        $goMod = $this->readText($this->join($projectRoot, 'go.mod'));
        $gitignore = $this->readText($this->join($projectRoot, '.gitignore'));

        $stack = $this->detectProjectStack($packageJson, $composerJson, $pyproject, $cargoToml, $goMod);
        $test = $this->detectTestCommand($packageJson, $composerJson, $makefile);
        $build = $this->detectBuildCommand($packageJson, $composerJson, $makefile);
        $run = $this->detectRunEntry($packageJson, $composerJson, $makefile);
        $versions = $this->detectVersionLocations($packageJson, $composerJson, $pyproject, $cargoToml);
        $sensitive = $this->detectSensitiveFiles($projectRoot, $gitignore);

        return [
            'project_stack' => $stack,
            'full_test_command' => $test,
            'build_command' => $build,
            'run_entry' => $run,
            'version_locations' => $versions,
            'sensitive_files' => $sensitive,
        ];
    }

    /**
     * @param array<string, mixed>|null $packageJson
     * @param array<string, mixed>|null $composerJson
     * @return Field
     */
    private function detectProjectStack(?array $packageJson, ?array $composerJson, ?string $pyproject, ?string $cargoToml, ?string $goMod): array
    {
        $items = [];
        $confidence = 'low';

        if ($packageJson !== null) {
            $items[] = 'Node.js';
            $items[] = 'npm';
            $confidence = 'high';
        }
        if ($composerJson !== null) {
            $items[] = 'PHP';
            $items[] = 'Composer';
            $confidence = 'high';
        }
        if ($pyproject !== null) {
            $items[] = 'Python';
            $confidence = $confidence === 'high' ? 'high' : 'medium';
        }
        if ($cargoToml !== null) {
            $items[] = 'Rust';
            $items[] = 'Cargo';
            $confidence = $confidence === 'high' ? 'high' : 'medium';
        }
        if ($goMod !== null) {
            $items[] = 'Go';
            $confidence = $confidence === 'high' ? 'high' : 'medium';
        }

        if ($items === []) {
            return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
        }

        return ['detected' => implode(', ', array_values(array_unique($items))), 'confidence' => $confidence];
    }

    /**
     * @param array<string, mixed>|null $packageJson
     * @param array<string, mixed>|null $composerJson
     * @return Field
     */
    private function detectTestCommand(?array $packageJson, ?array $composerJson, ?string $makefile): array
    {
        $npmTest = $this->jsonScript($packageJson, 'test');
        if ($npmTest !== null) {
            return ['detected' => 'npm test', 'confidence' => 'high'];
        }

        $composerTest = $this->jsonScript($composerJson, 'test');
        if ($composerTest !== null) {
            return ['detected' => 'composer test', 'confidence' => 'high'];
        }

        if ($this->makeTargetExists($makefile, 'test')) {
            return ['detected' => 'make test', 'confidence' => 'medium'];
        }

        return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
    }

    /**
     * @param array<string, mixed>|null $packageJson
     * @param array<string, mixed>|null $composerJson
     * @return Field
     */
    private function detectBuildCommand(?array $packageJson, ?array $composerJson, ?string $makefile): array
    {
        $npmBuild = $this->jsonScript($packageJson, 'build');
        if ($npmBuild !== null) {
            return ['detected' => 'npm run build', 'confidence' => 'high'];
        }

        $composerBuild = $this->jsonScript($composerJson, 'build');
        if ($composerBuild !== null) {
            return ['detected' => 'composer build', 'confidence' => 'medium'];
        }

        if ($this->makeTargetExists($makefile, 'build')) {
            return ['detected' => 'make build', 'confidence' => 'medium'];
        }

        return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
    }

    /**
     * @param array<string, mixed>|null $packageJson
     * @param array<string, mixed>|null $composerJson
     * @return Field
     */
    private function detectRunEntry(?array $packageJson, ?array $composerJson, ?string $makefile): array
    {
        if ($this->jsonScript($packageJson, 'dev') !== null) {
            return ['detected' => 'npm run dev', 'confidence' => 'high'];
        }
        if ($this->jsonScript($packageJson, 'start') !== null) {
            return ['detected' => 'npm start', 'confidence' => 'high'];
        }
        if ($this->jsonScript($composerJson, 'start') !== null) {
            return ['detected' => 'composer start', 'confidence' => 'medium'];
        }
        if ($this->makeTargetExists($makefile, 'run')) {
            return ['detected' => 'make run', 'confidence' => 'medium'];
        }

        return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
    }

    /**
     * @param array<string, mixed>|null $packageJson
     * @param array<string, mixed>|null $composerJson
     * @return Field
     */
    private function detectVersionLocations(?array $packageJson, ?array $composerJson, ?string $pyproject, ?string $cargoToml): array
    {
        $items = [];
        $confidence = 'low';

        if ($packageJson !== null && isset($packageJson['version']) && is_string($packageJson['version'])) {
            $items[] = 'package.json#version';
            $confidence = 'high';
        }
        if ($composerJson !== null && isset($composerJson['version']) && is_string($composerJson['version'])) {
            $items[] = 'composer.json#version';
            $confidence = 'high';
        }
        if ($pyproject !== null && preg_match('/^\s*version\s*=\s*".+?"\s*$/m', $pyproject) === 1) {
            $items[] = 'pyproject.toml (version)';
            $confidence = $confidence === 'high' ? 'high' : 'medium';
        }
        if ($cargoToml !== null && preg_match('/^\s*version\s*=\s*".+?"\s*$/m', $cargoToml) === 1) {
            $items[] = 'Cargo.toml (package.version)';
            $confidence = $confidence === 'high' ? 'high' : 'medium';
        }

        if ($items === []) {
            return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
        }

        return ['detected' => implode(', ', $items), 'confidence' => $confidence];
    }

    /**
     * @return Field
     */
    private function detectSensitiveFiles(string $projectRoot, ?string $gitignore): array
    {
        $items = [];

        foreach (['.env', '.env.local', '.env.production', '.env.development', 'secrets.json'] as $candidate) {
            if (file_exists($this->join($projectRoot, $candidate))) {
                $items[] = $candidate;
            }
        }

        if ($gitignore !== null) {
            foreach (preg_split('/\R/', $gitignore) ?: [] as $line) {
                $trim = trim($line);
                if ($trim === '' || str_starts_with($trim, '#')) {
                    continue;
                }
                if (str_contains($trim, '.env') || str_contains($trim, 'secret') || str_contains($trim, '.pem')) {
                    $items[] = $trim;
                }
            }
        }

        if ($items === []) {
            return ['detected' => 'UNKNOWN', 'confidence' => 'low'];
        }

        $items = array_values(array_unique($items));
        $confidence = count($items) >= 2 ? 'high' : 'medium';

        return ['detected' => implode(', ', $items), 'confidence' => $confidence];
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function jsonScript(?array $json, string $name): ?string
    {
        if ($json === null || !isset($json['scripts']) || !is_array($json['scripts'])) {
            return null;
        }

        $scripts = $json['scripts'];

        if (!array_key_exists($name, $scripts) || !is_string($scripts[$name])) {
            return null;
        }

        return $scripts[$name];
    }

    private function makeTargetExists(?string $makefile, string $target): bool
    {
        if ($makefile === null) {
            return false;
        }

        return preg_match('/^' . preg_quote($target, '/') . '\s*:/m', $makefile) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function readText(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        return $raw;
    }

    private function join(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
