<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureChangeIngestor;
use AiProfileManager\Core\ConsoleRegistration;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

final class ConsoleRegistrationTest extends TestCase
{
    public function testRegisterAddsAllNamedCommands(): void
    {
        $installer = new Installer();
        $checker = new CheckService();
        $capture = new CaptureService($checker);
        $ingestor = new CaptureChangeIngestor();
        $updater = new KnowledgeBaseUpdater();

        $app = new Application();
        ConsoleRegistration::register($app, $installer, $checker, $capture, $ingestor, $updater);

        $names = [
            'install',
            'init',
            'skill:install',
            'rule:install',
            'agent:install',
            'skill:check',
            'rule:check',
            'agent:check',
            'skill:capture',
            'rule:capture',
            'agent:capture',
            'check',
            'capture',
            'preset:create',
            'preset:add-ability',
            'preset:remove-ability',
            'preset:delete',
            'update',
            'ingest',
        ];

        foreach ($names as $name) {
            self::assertTrue($app->has($name), 'Missing command: ' . $name);
            $cmd = $app->find($name);
            self::assertSame($name, $cmd->getName());
            self::assertNotSame('', $cmd->getDescription());
        }
    }
}
