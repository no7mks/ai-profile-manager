<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\AppConfig;
use AiProfileManager\CheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SkillCheckCommand extends Command
{
    public function __construct(private readonly CheckService $checker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('skill:check')
            ->setDescription('Check installed skills status on target IDE/CLI tools.')
            ->addArgument('skills', InputArgument::IS_ARRAY, 'Skill names to check.')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var array<int, string> $skills */
        $skills = $input->getArgument('skills');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $skills = $skills === [] ? AppConfig::DEFAULT_SKILLS : array_values(array_unique($skills));
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));
            return Command::FAILURE;
        }

        $results = $this->checker->checkTyped(['skills' => $skills, 'rules' => [], 'agents' => []], $targets);
        foreach ($this->checker->renderResults($results) as $line) {
            $io->writeln($line);
        }

        return $this->checker->evaluateExitCode($results);
    }
}
