<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\AppConfig;
use AiProfileManager\CaptureService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CaptureCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('capture')
            ->setDescription('Capture modified status for a preset on target IDE/CLI tools (placeholder).')
            ->addArgument('preset', InputArgument::REQUIRED, 'Preset name to capture.')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $preset */
        $preset = $input->getArgument('preset');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');

        if (!in_array($preset, AppConfig::KNOWN_PRESETS, true)) {
            $io->error(sprintf('Unknown preset: %s. Known presets: %s.', $preset, implode(', ', AppConfig::KNOWN_PRESETS)));
            return Command::FAILURE;
        }

        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));
        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));
            return Command::FAILURE;
        }

        $io->writeln("Preset: {$preset}");
        $result = $this->capture->captureTyped(AppConfig::PRESET_ITEMS[$preset], $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'];
    }
}
