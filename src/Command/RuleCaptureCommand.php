<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\CaptureService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RuleCaptureCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('rule:capture');
        $this->setDescription('Capture rule changes vs Composer-installed baseline.');
        $this->addArgument('rules', InputArgument::IS_ARRAY, 'Rule names to capture.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('source-repo', null, InputOption::VALUE_OPTIONAL, 'Source repository identifier.', 'unknown/unknown');
        $this->addOption('source-commit', null, InputOption::VALUE_OPTIONAL, 'Source commit sha.', 'unknown');
        $this->addOption('base-ref', null, InputOption::VALUE_OPTIONAL, 'Ignored; baseline comes from Composer (legacy option).', 'unknown');
        $this->addOption('change-id', null, InputOption::VALUE_OPTIONAL, 'Change identifier. Defaults to generated UUID v4.');
        $this->addOption('captured-at', null, InputOption::VALUE_OPTIONAL, 'Capture timestamp (ISO 8601). Defaults to now.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var array<int, string> $rules */
        $rules = $input->getArgument('rules');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $rules = $rules === [] ? AppConfig::DEFAULT_RULES : array_values(array_unique($rules));
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));

            return Command::FAILURE;
        }

        $cwd = (string) getcwd();
        $result = $this->capture->captureTyped(['skills' => [], 'rules' => $rules, 'agents' => []], $targets, $cwd);

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        if ($result['baseline'] === null) {
            $io->error('Could not resolve Composer baseline. Install apm globally or set APM_BASELINE_ROOT for testing.');

            return Command::FAILURE;
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
