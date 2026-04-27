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

final class AgentCaptureCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('agent:capture')
            ->setDescription('Capture modified agents from target IDE/CLI tools (placeholder).')
            ->addArgument('agents', InputArgument::IS_ARRAY, 'Agent names to capture.')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var array<int, string> $agents */
        $agents = $input->getArgument('agents');
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $agents = $agents === [] ? AppConfig::DEFAULT_AGENTS : array_values(array_unique($agents));
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));

        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));
            return Command::FAILURE;
        }

        $result = $this->capture->captureTyped(['skills' => [], 'rules' => [], 'agents' => $agents], $targets);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'];
    }
}
