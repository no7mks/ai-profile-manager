<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\InvalidScopeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GlobalSetupCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('global-setup');
        $this->setDescription('[DEPRECATED] Use "apm bootstrap" instead.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->error(InvalidScopeException::globalSetupDeprecated()->getMessage());

        return Command::FAILURE;
    }
}
