<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PresetRemoveAbilityCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('preset:remove-ability');
        $this->setDescription('Remove one ability reference from a preset.');
        $this->addArgument('preset', InputArgument::REQUIRED, 'Preset name.');
        $this->addArgument('ability', InputArgument::REQUIRED, 'Ability name.');
        $this->addOption('skill', null, InputOption::VALUE_NONE, 'Treat ability as a skill.');
        $this->addOption('rule', null, InputOption::VALUE_NONE, 'Treat ability as a rule.');
        $this->addOption('agent', null, InputOption::VALUE_NONE, 'Treat ability as an agent.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $presetName = (string) $input->getArgument('preset');
        $ability = (string) $input->getArgument('ability');

        $flags = (int) $input->getOption('skill') + (int) $input->getOption('rule') + (int) $input->getOption('agent');
        if ($flags !== 1) {
            $io->error('Specify exactly one of --skill, --rule, or --agent.');

            return Command::FAILURE;
        }

        $registry = new PresetRegistry(new AbilityRegistry(__DIR__ . '/../../abilities.yaml'));
        $key = $input->getOption('skill') ? 'skill' : ($input->getOption('rule') ? 'rule' : 'agent');

        try {
            $registry->removeAbility($presetName, $key, $ability);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(sprintf('[ok] Removed %s from preset %s (%s).', $ability, $presetName, $key));

        return Command::SUCCESS;
    }
}
