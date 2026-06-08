<?php

declare(strict_types=1);

namespace AiProfileManager\Core;

use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
    public function __construct(
        private readonly Installer $installer = new Installer(),
    ) {
    }

    /**
     * Builds the same Symfony Console application used by {@see run()} (list default + all apm commands).
     */
    public static function createSymfonyApplication(
        Installer $installer,
        ?PresetRegistry $presetRegistry = null,
    ): SymfonyApplication {
        $checker = new CheckService();

        $app = new SymfonyApplication('apm', '0.10.0');
        $app->setDefaultCommand('list');
        ConsoleRegistration::register($app, $installer, $checker, $presetRegistry);

        return $app;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv, ?OutputInterface $output = null): int
    {
        return self::createSymfonyApplication($this->installer)->run(
            new ArgvInput($argv),
            $output ?? new ConsoleOutput(),
        );
    }
}
