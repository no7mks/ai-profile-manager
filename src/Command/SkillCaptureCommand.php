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

final class SkillCaptureCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('skill:capture')
            ->setDescription('Capture modified skills from target IDE/CLI tools (placeholder).')
            ->addArgument('skills', InputArgument::IS_ARRAY, 'Skill names to capture.')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.')
            ->addOption('source-repo', null, InputOption::VALUE_OPTIONAL, 'Source repository identifier.', 'unknown/unknown')
            ->addOption('source-commit', null, InputOption::VALUE_OPTIONAL, 'Source commit sha.', 'unknown')
            ->addOption('base-ref', null, InputOption::VALUE_OPTIONAL, 'Base version tag/commit for patch generation.', 'unknown')
            ->addOption('event-id', null, InputOption::VALUE_OPTIONAL, 'Event identifier. Defaults to generated UUID v4.')
            ->addOption('captured-at', null, InputOption::VALUE_OPTIONAL, 'Capture timestamp (ISO 8601). Defaults to now.');
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

        $result = $this->capture->captureTyped(['skills' => $skills, 'rules' => [], 'agents' => []], $targets);
        $event = $this->capture->buildCaptureEvent(
            $result['results'],
            (string) $input->getOption('source-repo'),
            (string) $input->getOption('source-commit'),
            (string) $input->getOption('base-ref'),
            (string) ($input->getOption('event-id') ?: ''),
            (string) ($input->getOption('captured-at') ?: gmdate(DATE_ATOM))
        );
        $path = $this->capture->writeEventToEventsDir($event);
        $io->writeln(sprintf('[ok] Event written to events dir: %s', $path));

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'];
    }
}
