<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\InvalidScopeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SkillInstallCommand extends Command
{
    use HandlesDeployScopeOption;
    public function __construct(private readonly Installer $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('skill:install');
        $this->setAliases(['skill:add']);
        $this->setDescription('Install skills into target IDE/CLI tools.');
        $this->addArgument('skills', InputArgument::IS_ARRAY, 'Skill names to install.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Target IDE/CLI tool. Repeat for multiple values.'
        );
        $this->configureDeployScopeOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->rejectIfScopeOptionPresent($input);
        } catch (InvalidScopeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        /** @var array<int, string> $skills */
        $skills = $input->getArgument('skills');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');

        $skills = $skills === [] ? AppConfig::DEFAULT_SKILLS : array_values(array_unique($skills));
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

        $typed = ['skills' => $skills, 'rules' => [], 'agents' => []];

        $result = $this->installer->installTyped($typed, $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
