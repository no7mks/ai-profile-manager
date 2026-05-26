<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PresetDeleteCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('preset:delete');
        $this->setDescription('Remove a preset from abilities/_presets.json.');
        $this->addArgument('name', InputArgument::REQUIRED, 'Preset name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $cwd = (string) getcwd();

        $registry = new PresetRegistry($cwd);
        $all = $registry->allPresets();
        if (!isset($all[$name])) {
            $io->error(sprintf('Unknown preset: %s', $name));

            return Command::FAILURE;
        }

        unset($all[$name]);
        $registry->saveToWorkspace($all);

        $io->writeln(sprintf('[ok] Preset deleted: %s', $name));

        return Command::SUCCESS;
    }
}
