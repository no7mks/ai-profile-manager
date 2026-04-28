<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Service\ProjectInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallCommand extends Command
{
    public function __construct(
        private readonly Installer $installer,
        private readonly ?ProjectInitializer $initializer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('install');
        $this->setDescription('Install preset items, or run project bootstrap when preset is omitted.');
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name to install. Omit to run project bootstrap.');
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
            'Overwrite existing scaffold files when running bootstrap mode (no preset).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $preset */
        $preset = $input->getArgument('preset');
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

        if ($preset === null || $preset === '') {
            return $this->runBootstrap($input, $io, $targets);
        }

        $registry = new PresetRegistry((string) getcwd());
        $known = array_keys($registry->allPresets());
        if (!in_array($preset, $known, true)) {
            $io->error(sprintf(
                'Unknown preset: %s. Known presets: %s.',
                $preset,
                implode(', ', $known)
            ));
            return Command::FAILURE;
        }

        $presetSpec = $registry->getPreset($preset);
        if ($presetSpec === null) {
            return Command::FAILURE;
        }

        $items = $presetSpec;
        $io->writeln("Preset: {$preset}");
        $result = $this->installer->installTyped($items, $targets, $preset);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param array<int, string> $targets
     */
    private function runBootstrap(InputInterface $input, SymfonyStyle $io, array $targets): int
    {
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

        $io->section('Installing default skills');
        $result = $this->installer->installTyped([
            'skills' => ['apm'],
            'rules' => [],
            'agents' => [],
        ], $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        if ($result['exit_code'] !== 0) {
            return Command::FAILURE;
        }

        $io->success("Bootstrap complete. In your agent chat, run '/apm init' to complete SSOT setup.");

        return Command::SUCCESS;
    }
}
