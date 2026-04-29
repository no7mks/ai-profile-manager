<?php

declare(strict_types=1);

namespace AiProfileManager\Core;

use AiProfileManager\Capture\CaptureChangeIngestor;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
    public function __construct(
        private readonly Installer $installer = new Installer(),
        private readonly KnowledgeBaseUpdater $updater = new KnowledgeBaseUpdater(),
    ) {
    }

    /**
     * Builds the same Symfony Console application used by {@see run()} (list default + all apm commands).
     */
    public static function createSymfonyApplication(
        Installer $installer,
        KnowledgeBaseUpdater $updater,
    ): SymfonyApplication {
        $checker = new CheckService();
        $capture = new CaptureService($checker);
        $ingestor = new CaptureChangeIngestor();

        $app = new SymfonyApplication('apm', '0.4.4');
        $app->setDefaultCommand('list');
        ConsoleRegistration::register($app, $installer, $checker, $capture, $ingestor, $updater);

        return $app;
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv, ?OutputInterface $output = null): int
    {
        return self::createSymfonyApplication($this->installer, $this->updater)->run(
            new ArgvInput($argv),
            $output ?? new ConsoleOutput(),
        );
    }
}
