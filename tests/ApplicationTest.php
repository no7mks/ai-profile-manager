<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Core\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testApplicationCanBeConstructed(): void
    {
        self::assertInstanceOf(Application::class, new Application());
    }

    public function testBinAipmBinaryExists(): void
    {
        self::assertFileExists(dirname(__DIR__) . '/bin/aipm');
    }
}
