<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Core\Application;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testApplicationCanBeConstructed(): void
    {
        self::assertInstanceOf(Application::class, new Application());
    }

    public function testBinApmBinaryExists(): void
    {
        self::assertFileExists(dirname(__DIR__) . '/bin/apm');
    }

    public function testCreateSymfonyApplicationRegistersSameCommandsAsCli(): void
    {
        $app = Application::createSymfonyApplication(new Installer(), new KnowledgeBaseUpdater());

        self::assertTrue($app->has('install'));
        self::assertTrue($app->has('show'));
        self::assertTrue($app->has('ingest'));
        self::assertSame('apm', $app->getName());
    }
}
