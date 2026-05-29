<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class ProjectInitializerExtendedTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-projinit-ext-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFromPackageLayoutCreatesInstance(): void
    {
        $init = ProjectInitializer::fromPackageLayout();
        self::assertInstanceOf(ProjectInitializer::class, $init);
    }
}
