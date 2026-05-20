<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Core\ConsoleRegistration;
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
        $updater = new KnowledgeBaseUpdater();

        $app = new Application();
        ConsoleRegistration::register($app, $installer, $checker, $updater);

        $names = [
            'install',
            'show',
            'skill:install',
            'rule:install',
            'agent:install',
            'skill:check',
            'rule:check',
            'agent:check',
            'check',
            'preset:create',
            'preset:add-ability',
            'preset:remove-ability',
            'preset:delete',
            'update',
        ];

        foreach ($names as $name) {
            self::assertTrue($app->has($name), 'Missing command: ' . $name);
            $cmd = $app->find($name);
            self::assertSame($name, $cmd->getName());
            self::assertNotSame('', $cmd->getDescription());
        }
    }
}
