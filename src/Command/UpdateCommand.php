<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UpdateCommand extends Command
{
    public function __construct(private readonly KnowledgeBaseUpdater $updater)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('update');
        $this->setDescription('Update aipm local ability knowledge base.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $this->updater->update(
            AppConfig::DEFAULT_SKILLS,
            AppConfig::DEFAULT_RULES,
            AppConfig::DEFAULT_AGENTS,
            AppConfig::KNOWN_PRESETS,
            AppConfig::KNOWN_TARGETS
        );
        $io->success("Knowledge base updated at: {$path}");

        return Command::SUCCESS;
    }
}
