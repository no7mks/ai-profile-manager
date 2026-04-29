<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SkillUninstallCommand extends Command
{
    public function __construct(
        private readonly Installer $installer,
        private readonly CheckService $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('skill:uninstall');
        $this->setDescription('Uninstall skills from target IDE/CLI tools.');
        $this->addArgument('skills', InputArgument::IS_ARRAY, 'Skill names to uninstall.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force uninstall even when modified drift is detected.');
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

        $items = ['skills' => $skills, 'rules' => [], 'agents' => []];
        $check = $this->checker->checkTyped($items, $targets);
        if (!$input->getOption('force') && $this->checker->hasModified($check)) {
            foreach ($this->checker->renderResults(array_values(array_filter($check, static fn (array $r): bool => $r['status'] === 'modified'))) as $line) {
                $io->writeln($line);
            }
            $io->error('Detected modified items. Re-run with --force to uninstall.');

            return Command::FAILURE;
        }

        $result = $this->installer->uninstallTyped($items, $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}
