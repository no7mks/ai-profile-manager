<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\ComposerBaselineResolver;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class ComposerBaselineResolverTest extends TestCase
{
    use RestoresEnvTrait;

    public function testResolveUsesAipmBaselineRootEnv(): void
    {
        $root = sys_get_temp_dir() . '/apm-bl-env-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);

        $this->withEnv('APM_BASELINE_ROOT', $root);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('no7mks/ai-profile-manager', $out['package']);
        self::assertSame(realpath($root) ?: $root, $out['install_path']);
    }

    public function testResolveFromGlobalInstalledJson(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-ch-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode([
            'packages' => [[
                'name' => 'no7mks/ai-profile-manager',
                'version' => '2.3.4-test',
                'dist' => ['reference' => 'beefcafe'],
            ]],
        ], JSON_UNESCAPED_SLASHES));

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('2.3.4-test', $out['version']);
        self::assertSame('beefcafe', $out['reference'] ?? null);
    }

    public function testConstructorOverrideInstallPath(): void
    {
        $root = sys_get_temp_dir() . '/apm-ovr-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);

        $resolver = new ComposerBaselineResolver('no7mks/ai-profile-manager', $root);
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('override', $out['version']);
    }

    public function testResolveUsesSourceReferenceWhenDistMissing(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-ch-src-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode([
            'packages' => [[
                'name' => 'no7mks/ai-profile-manager',
                'version' => '3.0.0',
                'source' => ['reference' => 'aaaabbbb'],
            ]],
        ], JSON_UNESCAPED_SLASHES));

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('aaaabbbb', $out['reference'] ?? null);
    }

    public function testResolveReturnsNullWhenInstalledJsonUnreadable(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-ch-miss-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNull($out);
    }

    public function testResolveFallsBackToXdgConfigComposerWhenDotComposerMissing(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-xdg-miss-' . bin2hex(random_bytes(4));
        $this->seedXdgInstalledJson($home, '9.9.9-xdg-only');

        $this->withEnv('HOME', $home);
        $this->withEnv('APM_BASELINE_ROOT', null);
        $this->withEnv('COMPOSER_HOME', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('no7mks/ai-profile-manager', $out['package']);
        self::assertSame('9.9.9-xdg-only', $out['version']);
    }

    public function testResolveFallsBackToXdgConfigComposerWhenDotComposerHasNoInstalledJson(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-xdg-empty-' . bin2hex(random_bytes(4));
        mkdir($home . '/.composer/vendor/composer', 0775, true);
        $this->seedXdgInstalledJson($home, '8.8.8-xdg-fallback');

        $this->withEnv('HOME', $home);
        $this->withEnv('APM_BASELINE_ROOT', null);
        $this->withEnv('COMPOSER_HOME', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('8.8.8-xdg-fallback', $out['version']);
    }

    public function testResolvePrefersDotComposerOverXdgConfigWhenBothHaveInstalledJson(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-xdg-prio-' . bin2hex(random_bytes(4));
        $this->seedDotComposerInstalledJson($home, '1.1.1-dot-composer-wins');
        $this->seedXdgInstalledJson($home, '2.2.2-xdg-should-not-win');

        $this->withEnv('HOME', $home);
        $this->withEnv('APM_BASELINE_ROOT', null);
        $this->withEnv('COMPOSER_HOME', null);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('1.1.1-dot-composer-wins', $out['version']);
    }

    public function testResolveFallsBackToAppDataComposerOnWindows(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm-win-appdata-' . bin2hex(random_bytes(4));
        $appData = $base . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Roaming';
        $composerHome = $appData . DIRECTORY_SEPARATOR . 'Composer';
        mkdir($composerHome . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer', 0775, true);
        $this->writeGlobalInstalledJson($composerHome, '7.7.7-appdata-win');

        $this->withEnv('APPDATA', $appData);
        $this->withEnv('USERPROFILE', $base . DIRECTORY_SEPARATOR . 'Users' . DIRECTORY_SEPARATOR . 'test');
        $this->withEnv('HOME', null);
        $this->withEnv('COMPOSER_HOME', null);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver(isWindows: true);
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('7.7.7-appdata-win', $out['version']);
    }

    public function testResolvePrefersComposerHomeOverWindowsFallbacks(): void
    {
        $composerHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm-win-ch-' . bin2hex(random_bytes(4));
        mkdir($composerHome . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer', 0775, true);
        $this->writeGlobalInstalledJson($composerHome, '6.6.6-scoop-home');

        $this->withEnv('COMPOSER_HOME', $composerHome);
        $this->withEnv('APPDATA', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'unused-appdata');
        $this->withEnv('USERPROFILE', sys_get_temp_dir());
        $this->withEnv('HOME', null);
        $this->withEnv('APM_BASELINE_ROOT', null);

        $resolver = new ComposerBaselineResolver(isWindows: true);
        $out = $resolver->resolve();

        self::assertNotNull($out);
        self::assertSame('6.6.6-scoop-home', $out['version']);
    }

    private function seedDotComposerInstalledJson(string $home, string $version): void
    {
        $composerHome = $home . '/.composer';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        $this->writeGlobalInstalledJson($composerHome, $version);
    }

    private function seedXdgInstalledJson(string $home, string $version): void
    {
        $composerHome = $home . '/.config/composer';
        mkdir($composerHome . '/vendor/composer', 0775, true);
        $this->writeGlobalInstalledJson($composerHome, $version);
    }

    private function writeGlobalInstalledJson(string $composerHome, string $version): void
    {
        file_put_contents(
            $composerHome . '/vendor/composer/installed.json',
            json_encode([
                'packages' => [[
                    'name' => 'no7mks/ai-profile-manager',
                    'version' => $version,
                    'dist' => ['reference' => 'ref-' . $version],
                ]],
            ], JSON_UNESCAPED_SLASHES),
        );
    }
}
