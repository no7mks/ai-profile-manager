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

final class PresetCreateCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('preset:create');
        $this->setDescription('Create a preset in abilities.yaml.');
        $this->addArgument('name', InputArgument::REQUIRED, 'Preset name.');
        $this->addOption('skill', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skill names (repeatable).', []);
        $this->addOption('rule', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rule names (repeatable).', []);
        $this->addOption('agent', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Agent names (repeatable).', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        /** @var array<int, string> $skills */
        $skills = $input->getOption('skill');
        /** @var array<int, string> $rules */
        $rules = $input->getOption('rule');
        /** @var array<int, string> $agents */
        $agents = $input->getOption('agent');

        $registry = new PresetRegistry(new AbilityRegistry(__DIR__ . '/../../abilities.yaml'));

        // Build includes list from typed options
        $includes = [];
        foreach ($skills as $s) {
            $includes[] = 'skill:' . $s;
        }
        foreach ($rules as $r) {
            $includes[] = 'rule:' . $r;
        }
        foreach ($agents as $a) {
            $includes[] = 'agent:' . $a;
        }

        try {
            $registry->createPreset($name, '', $includes);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(sprintf('[ok] Preset created: %s', $name));

        return Command::SUCCESS;
    }
}
