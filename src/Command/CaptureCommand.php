<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\PresetRegistry;
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
        $this->setName('capture');
        $this->setDescription('Capture preset diff or full workspace abilities vs Composer baseline.');
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name from abilities/_presets.json (merged with defaults); omit for full workspace snapshot.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation for full workspace capture.');
        $this->addOption('source-repo', null, InputOption::VALUE_OPTIONAL, 'Source repository identifier.', 'unknown/unknown');
        $this->addOption('source-commit', null, InputOption::VALUE_OPTIONAL, 'Source commit sha.', 'unknown');
        $this->addOption('base-ref', null, InputOption::VALUE_OPTIONAL, 'Deprecated and ignored; baseline always comes from Composer.', 'unknown');
        $this->addOption('change-id', null, InputOption::VALUE_OPTIONAL, 'Change identifier. Defaults to generated UUID v4.');
        $this->addOption('captured-at', null, InputOption::VALUE_OPTIONAL, 'Capture timestamp (ISO 8601). Defaults to now.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var ?string $presetArg */
        $presetArg = $input->getArgument('preset');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');

        $targets = $targets === [] ? \AiProfileManager\Config\AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, \AiProfileManager\Config\AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', \AiProfileManager\Config\AppConfig::KNOWN_TARGETS)));

            return Command::FAILURE;
        }

        $cwd = (string) getcwd();
        $registry = new PresetRegistry($cwd);

        $presetMissing = $presetArg === null || $presetArg === '';

        if (!$presetMissing) {
            $spec = $registry->getPreset((string) $presetArg);
            if ($spec === null) {
                $io->error(sprintf('Unknown preset: %s. Known presets: %s.', $presetArg, implode(', ', array_keys($registry->allPresets()))));

                return Command::FAILURE;
            }

            $itemsSpec = [
                'skills' => $spec['skills'],
                'rules' => $spec['rules'],
                'agents' => $spec['agents'],
            ];
            $io->writeln(sprintf('Preset: %s', $presetArg));
        } else {
            $discovered = $this->capture->discoverWorkspaceAbilities($cwd);
            $itemsSpec = [
                'skills' => $discovered['skills'],
                'rules' => $discovered['rules'],
                'agents' => $discovered['agents'],
            ];
            $io->writeln('Full workspace snapshot (abilities/* subdirectories).');
        }

        $result = $this->capture->captureTyped($itemsSpec, $targets, $cwd);

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        if ($result['baseline'] === null) {
            $io->error('Could not resolve Composer baseline. Install apm globally or set APM_BASELINE_ROOT for testing.');

            return Command::FAILURE;
        }

        if ($result['results'] === []) {
            return Command::SUCCESS;
        }

        if ($presetMissing && !$input->getOption('yes')) {
            if (!$io->confirm('Generate capture change?', false)) {
                return Command::SUCCESS;
            }
        }

        $persist = $this->capture->persistCaptureChange(
            $result,
            (string) $input->getOption('source-repo'),
            (string) $input->getOption('source-commit'),
            (string) ($input->getOption('change-id') ?: ''),
            (string) ($input->getOption('captured-at') ?: gmdate(DATE_ATOM)),
        );

        if ($persist['path'] !== null) {
            $io->writeln(sprintf('[ok] Change written to changes dir: %s', $persist['path']));
        }

        return $result['exit_code'];
    }
}
