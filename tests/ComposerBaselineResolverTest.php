<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\ComposerBaselineResolver;
use PHPUnit\Framework\TestCase;

final class ComposerBaselineResolverTest extends TestCase
{
    public function testResolveUsesAipmBaselineRootEnv(): void
    {
        $root = sys_get_temp_dir() . '/apm-bl-env-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);

        $old = getenv('APM_BASELINE_ROOT');
        putenv('APM_BASELINE_ROOT=' . $root);

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        if ($old === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $old);
        }

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

        $oldHome = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        if ($oldHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldHome);
        }
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

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

        $oldHome = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        if ($oldHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldHome);
        }
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertNotNull($out);
        self::assertSame('aaaabbbb', $out['reference'] ?? null);
    }

    public function testResolveReturnsNullWhenInstalledJsonUnreadable(): void
    {
        $composerHome = sys_get_temp_dir() . '/apm-ch-miss-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);

        $oldHome = getenv('COMPOSER_HOME');
        $oldBl = getenv('APM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('APM_BASELINE_ROOT');

        $resolver = new ComposerBaselineResolver();
        $out = $resolver->resolve();

        if ($oldHome === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldHome);
        }
        if ($oldBl === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertNull($out);
    }
}
