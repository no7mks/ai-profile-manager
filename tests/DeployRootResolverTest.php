<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\UserHomeResolver;
use AiProfileManager\Tests\Support\RestoresEnvTrait;
use PHPUnit\Framework\TestCase;

final class DeployRootResolverTest extends TestCase
{
    use RestoresEnvTrait;

    public function testResolveProjectReturnsWorkspaceRoot(): void
    {
        $workspace = sys_get_temp_dir() . '/apm-ws-' . bin2hex(random_bytes(4));
        mkdir($workspace, 0775, true);

        $oldCwd = getcwd();
        self::assertNotFalse($oldCwd);
        chdir($workspace);

        try {
            $resolver = new DeployRootResolver();
            $root = $resolver->resolve(DeployScope::Project);

            self::assertSame(realpath($workspace) ?: $workspace, $root);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testResolveUserReturnsHomeDirectory(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);
        $this->withEnv('HOME', $home);

        $resolver = new DeployRootResolver();
        $root = $resolver->resolve(DeployScope::User);

        self::assertSame($home, $root);
    }

    public function testParseScopeOptionDefaultsToProjectWhenNull(): void
    {
        $resolver = new DeployRootResolver();

        self::assertSame(DeployScope::Project, $resolver->parseScopeOption(null));
    }

    public function testParseScopeOptionAcceptsProjectAndUser(): void
    {
        $resolver = new DeployRootResolver();

        self::assertSame(DeployScope::Project, $resolver->parseScopeOption('project'));
        self::assertSame(DeployScope::User, $resolver->parseScopeOption('user'));
    }

    public function testParseScopeOptionThrowsForInvalidScope(): void
    {
        $resolver = new DeployRootResolver();

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Invalid deploy scope: bogus');

        $resolver->parseScopeOption('bogus');
    }

    public function testParseScopeOptionThrowsForEmptyString(): void
    {
        $resolver = new DeployRootResolver();

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Invalid deploy scope: (empty)');

        $resolver->parseScopeOption('');
    }

    public function testAbsoluteTargetPathJoinsRootAndRelativeTarget(): void
    {
        $home = sys_get_temp_dir() . '/apm-home-path-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);
        $this->withEnv('HOME', $home);

        $resolver = new DeployRootResolver();
        $path = $resolver->absoluteTargetPath(DeployScope::User, '.cursor/skills/demo/');

        self::assertSame(
            $home . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'demo',
            $path,
        );
    }

    public function testResolveUserOnWindowsUsesUserProfileNotMsysHome(): void
    {
        $profile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm-win-deploy-' . bin2hex(random_bytes(4));
        mkdir($profile, 0775, true);
        $this->withEnv('USERPROFILE', $profile);
        $this->withEnv('HOME', '/c/Users/msys');
        $this->withEnv('HOMEDRIVE', null);
        $this->withEnv('HOMEPATH', null);

        $resolver = new DeployRootResolver(new UserHomeResolver(isWindows: true));
        $root = $resolver->resolve(DeployScope::User);

        self::assertSame($profile, $root);
    }

    public function testAbsoluteTargetPathOnWindowsUsesUserProfile(): void
    {
        $profile = 'C:\\Users\\testuser';
        $this->withEnv('USERPROFILE', $profile);
        $this->withEnv('HOME', '/c/Users/testuser');

        $resolver = new DeployRootResolver(new UserHomeResolver(isWindows: true));
        $path = $resolver->absoluteTargetPath(DeployScope::User, '.cursor/skills/demo/');

        self::assertSame(
            $profile . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'demo',
            $path,
        );
    }
}
