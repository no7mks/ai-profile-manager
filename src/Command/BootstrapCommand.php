<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\ProjectInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class BootstrapCommand extends Command
{
    public function __construct(
        private readonly ?ProjectInitializer $initializer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('bootstrap');
        $this->setDescription('Install project scaffold (docs/, issues/, AGENTS.md) only.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Target IDE/CLI tool. Repeat for multiple values.'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing scaffold files when docs/, issues/, or AGENTS.md already exist.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf(
                'Unknown targets: %s. Known targets: %s.',
                implode(', ', $unknownTargets),
                implode(', ', AppConfig::KNOWN_TARGETS)
            ));

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');

        try {
            $initializer = $this->initializer ?? ProjectInitializer::fromPackageLayout();
            foreach ($initializer->init((string) getcwd(), $force, $targets) as $line) {
                $io->writeln($line);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success("Bootstrap complete. In your agent chat, run '/apm init' to complete SSOT setup.");

        return Command::SUCCESS;
    }
}
