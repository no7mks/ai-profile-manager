<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\UserHomeResolver;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class UserHomeResolverTest extends TestCase
{
    use RestoresEnvTrait;

    public function testResolveWindowsUsesUserProfile(): void
    {
        $profile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm-win-profile-' . bin2hex(random_bytes(4));
        $this->withEnv('USERPROFILE', $profile);
        $this->withEnv('HOME', '/c/Users/msys-only');
        $this->withEnv('HOMEDRIVE', null);
        $this->withEnv('HOMEPATH', null);

        $resolver = new UserHomeResolver(isWindows: true);

        self::assertSame($profile, $resolver->resolve());
    }

    public function testResolveWindowsIgnoresMsysHomeWhenUserProfileSet(): void
    {
        $profile = 'C:\\Users\\testuser';
        $this->withEnv('USERPROFILE', $profile);
        $this->withEnv('HOME', '/c/Users/testuser');

        $resolver = new UserHomeResolver(isWindows: true);

        self::assertSame($profile, $resolver->resolve());
    }

    public function testResolveWindowsFallsBackToHomeDriveAndPath(): void
    {
        $this->withEnv('USERPROFILE', null);
        $this->withEnv('HOMEDRIVE', 'C:');
        $this->withEnv('HOMEPATH', '\\Users\\bay');

        $resolver = new UserHomeResolver(isWindows: true);

        self::assertSame('C:\\Users\\bay', $resolver->resolve());
    }

    public function testResolveWindowsAddsLeadingSlashToHomePathWhenMissing(): void
    {
        $this->withEnv('USERPROFILE', null);
        $this->withEnv('HOMEDRIVE', 'D:');
        $this->withEnv('HOMEPATH', 'Users\\other');

        $resolver = new UserHomeResolver(isWindows: true);

        self::assertSame('D:\\Users\\other', $resolver->resolve());
    }

    public function testResolveWindowsThrowsWhenNoProfileOrDrivePath(): void
    {
        $this->withEnv('USERPROFILE', null);
        $this->withEnv('HOMEDRIVE', null);
        $this->withEnv('HOMEPATH', null);
        $this->withEnv('HOME', null);

        $resolver = new UserHomeResolver(isWindows: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('USERPROFILE');

        $resolver->resolve();
    }

    public function testResolveUnixUsesHome(): void
    {
        $home = sys_get_temp_dir() . '/apm-unix-home-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);
        $this->withEnv('HOME', $home);
        $this->withEnv('USERPROFILE', 'C:\\Users\\should-not-use');

        $resolver = new UserHomeResolver(isWindows: false);

        self::assertSame($home, $resolver->resolve());
    }

    public function testResolveUnixThrowsWhenHomeUnset(): void
    {
        $this->withEnv('HOME', null);

        $resolver = new UserHomeResolver(isWindows: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HOME is not set');

        $resolver->resolve();
    }
}
