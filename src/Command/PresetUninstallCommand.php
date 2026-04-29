<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PresetUninstallCommand extends Command
{
    public function __construct(
        private readonly Installer $installer,
        private readonly CheckService $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('preset:uninstall');
        $this->setDescription('Uninstall all items in a preset from target IDE/CLI tools.');
        $this->addArgument('preset', InputArgument::REQUIRED, 'Preset name to uninstall.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force uninstall even when modified drift is detected.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $preset */
        $preset = $input->getArgument('preset');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));

            return Command::FAILURE;
        }

        $registry = new PresetRegistry((string) getcwd());
        $known = array_keys($registry->allPresets());
        if (!in_array($preset, $known, true)) {
            $io->error(sprintf('Unknown preset: %s. Known presets: %s.', $preset, implode(', ', $known)));

            return Command::FAILURE;
        }

        $spec = $registry->getPreset($preset);
        if ($spec === null) {
            return Command::FAILURE;
        }
        $io->writeln(sprintf('Preset: %s', $preset));

        $check = $this->checker->checkTyped($spec, $targets);
        if (!$input->getOption('force') && $this->checker->hasModified($check)) {
            foreach ($this->checker->renderResults(array_values(array_filter($check, static fn (array $r): bool => $r['status'] === 'modified'))) as $line) {
                $io->writeln($line);
            }
            $io->error('Detected modified items. Re-run with --force to uninstall.');

            return Command::FAILURE;
        }

        $result = $this->installer->uninstallTyped($spec, $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}
