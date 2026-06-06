<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\DeployRootResolver;
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
            $root = $resolver->resolve();

            self::assertSame(realpath($workspace) ?: $workspace, $root);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testResolveWithInjectedRootPath(): void
    {
        $workspace = sys_get_temp_dir() . '/apm-ws-' . bin2hex(random_bytes(4));
        mkdir($workspace, 0775, true);

        $resolver = new DeployRootResolver($workspace);
        $root = $resolver->resolve();

        self::assertSame($workspace, $root);
    }

    public function testAbsoluteTargetPathJoinsRootAndRelativeTarget(): void
    {
        $workspace = sys_get_temp_dir() . '/apm-path-' . bin2hex(random_bytes(4));
        mkdir($workspace, 0775, true);

        $resolver = new DeployRootResolver($workspace);
        $path = $resolver->absoluteTargetPath('.cursor/skills/demo/');

        self::assertSame(
            $workspace . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'demo',
            $path,
        );
    }

    public function testAbsoluteTargetPathWithStringOnly(): void
    {
        $workspace = sys_get_temp_dir() . '/apm-path2-' . bin2hex(random_bytes(4));
        mkdir($workspace, 0775, true);

        $resolver = new DeployRootResolver($workspace);
        $path = $resolver->absoluteTargetPath('.cursor/skills/demo/');

        self::assertSame(
            $workspace . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'demo',
            $path,
        );
    }
}
