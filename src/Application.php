<?php

declare(strict_types=1);

namespace AiProfileManager;

use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\UpdateCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application
{
    public function __construct(
        private readonly Installer $installer = new Installer(),
        private readonly KnowledgeBaseUpdater $updater = new KnowledgeBaseUpdater(),
    ) {
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $app = new SymfonyApplication('aipm', '0.1.0');
        $app->setDefaultCommand('list');
        $app->add(new InstallCommand($this->installer));
        $app->add(new UpdateCommand($this->updater));

        return $app->run();
    }
}
