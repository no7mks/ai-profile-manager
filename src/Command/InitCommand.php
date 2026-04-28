<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\ProjectInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InitCommand extends Command
{
    public function __construct(private readonly ProjectInitializer $initializer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init');
        $this->setDescription(
            'Install the bundled project scaffold (docs/, issues/, AGENTS.md, PROJECT.md) and optional scope rules.'
        );
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Target project directory (default: current directory). Created if it does not exist.'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing scaffold or scope files.'
        );
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'IDE/tool targets for scope rules (cursor-scope / kiro-scope). Repeat for multiple values.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $pathArg */
        $pathArg = $input->getArgument('path');
        $target = $pathArg !== null && $pathArg !== '' ? $pathArg : (string) getcwd();

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
            foreach ($this->initializer->init($target, $force, $targets) as $line) {
                $io->writeln($line);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
