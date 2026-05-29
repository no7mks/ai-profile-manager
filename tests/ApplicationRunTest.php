<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Core\Application;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationRunTest extends TestCase
{
    public function testCreateSymfonyApplicationRegistersAllCommands(): void
    {
        $app = Application::createSymfonyApplication(new Installer(), new KnowledgeBaseUpdater());
        $app->setAutoExit(false);

        self::assertTrue($app->has('install'));
        self::assertTrue($app->has('show'));
        self::assertTrue($app->has('check'));
        self::assertTrue($app->has('update'));
        self::assertTrue($app->has('skill:install'));
        self::assertTrue($app->has('skill:check'));
        self::assertTrue($app->has('rule:install'));
        self::assertTrue($app->has('rule:check'));
        self::assertTrue($app->has('agent:install'));
        self::assertTrue($app->has('agent:check'));
        self::assertTrue($app->has('skill:uninstall'));
        self::assertTrue($app->has('rule:uninstall'));
        self::assertTrue($app->has('agent:uninstall'));
    }

    public function testCreateSymfonyApplicationCanRunListCommand(): void
    {
        $app = Application::createSymfonyApplication(new Installer(), new KnowledgeBaseUpdater());
        $app->setAutoExit(false);
        $output = new BufferedOutput();
        $exit = $app->run(new ArrayInput(['command' => 'list']), $output);

        self::assertSame(0, $exit);
        self::assertStringContainsString('install', $output->fetch());
    }

    public function testApplicationConstructorAcceptsDefaults(): void
    {
        $app = new Application();
        self::assertInstanceOf(Application::class, $app);
    }

    public function testApplicationConstructorAcceptsCustomDependencies(): void
    {
        $app = new Application(
            installer: new Installer(),
            updater: new KnowledgeBaseUpdater(),
        );
        self::assertInstanceOf(Application::class, $app);
    }
}
