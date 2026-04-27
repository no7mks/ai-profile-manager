<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\AppConfig;
use AiProfileManager\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallCommand extends Command
{
    public function __construct(private readonly Installer $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('install')
            ->setDescription('Install abilities into target IDE/CLI tools.')
            ->addArgument('abilities', InputArgument::IS_ARRAY, 'Ability names to install.')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Target IDE/CLI tool. Repeat for multiple values.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var array<int, string> $abilities */
        $abilities = $input->getArgument('abilities');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');

        $abilities = $abilities === [] ? AppConfig::DEFAULT_ABILITIES : array_values(array_unique($abilities));
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownAbilities = array_values(array_diff($abilities, AppConfig::KNOWN_ABILITIES));
        if ($unknownAbilities !== []) {
            $io->error(sprintf(
                'Unknown abilities: %s. Known abilities: %s.',
                implode(', ', $unknownAbilities),
                implode(', ', AppConfig::KNOWN_ABILITIES)
            ));
            return Command::FAILURE;
        }

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf(
                'Unknown targets: %s. Known targets: %s.',
                implode(', ', $unknownTargets),
                implode(', ', AppConfig::KNOWN_TARGETS)
            ));
            return Command::FAILURE;
        }

        foreach ($this->installer->install($abilities, $targets) as $line) {
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}
