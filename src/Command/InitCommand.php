<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\ProjectInitializer;
use AiProfileManager\Service\ProjectProfileDetector;
use AiProfileManager\Service\ProjectProfileRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InitCommand extends Command
{
    public function __construct(
        private readonly ProjectInitializer $initializer,
        private readonly ProjectProfileDetector $detector = new ProjectProfileDetector(),
        private readonly ProjectProfileRenderer $renderer = new ProjectProfileRenderer(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init');
        $this->setDescription(
            'Install scaffold and scope rules, then detect/confirm and prefill PROJECT.md.'
        );
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Target project directory (default: current directory). Created if it does not exist.'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing scaffold or scope files.'
        );
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'IDE/tool targets for scope rules (cursor-scope / kiro-scope). Repeat for multiple values.'
        );
        $this->addOption(
            'no-prefill',
            null,
            InputOption::VALUE_NONE,
            'Skip profile detection/confirmation and write UNKNOWN placeholders to PROJECT.md.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $pathArg */
        $pathArg = $input->getArgument('path');
        $target = $pathArg !== null && $pathArg !== '' ? $pathArg : (string) getcwd();

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

        $force = (bool) $input->getOption('force');
        $noPrefill = (bool) $input->getOption('no-prefill');

        if (!$noPrefill && !$input->isInteractive()) {
            $io->error('init requires interactive confirmation to prefill PROJECT.md. Use --no-prefill to skip.');

            return Command::FAILURE;
        }

        $projectProfile = ProjectProfileRenderer::unknownProfile();
        if (!$noPrefill) {
            $detected = $this->detector->detect($target);
            $confirmed = $this->confirmProfileFields($io, $detected);
            $projectProfile = $this->renderer->buildProfile($detected, $confirmed);
        }

        try {
            foreach ($this->initializer->init($target, $force, $targets, $projectProfile) as $line) {
                $io->writeln($line);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{detected: string, confidence: 'high'|'medium'|'low'}> $detected
     * @return array<string, string>
     */
    private function confirmProfileFields(SymfonyStyle $io, array $detected): array
    {
        $io->section('Prefill PROJECT.md');

        $labels = [
            'project_stack' => 'Project Stack',
            'full_test_command' => 'Full Test Command',
            'build_command' => 'Build Command',
            'run_entry' => 'Run Entry',
            'version_locations' => 'Version Locations',
            'sensitive_files' => 'Sensitive Files',
        ];

        $confirmed = [];
        foreach ($labels as $key => $label) {
            $field = $detected[$key] ?? ['detected' => 'UNKNOWN', 'confidence' => 'low'];
            $candidate = $field['detected'];
            $confidence = $field['confidence'];
            $io->writeln(sprintf('%s | detected=%s | confidence=%s', $label, $candidate, $confidence));

            $choice = $io->choice(
                sprintf('How to fill %s?', $label),
                ['accept', 'edit', 'unknown'],
                'accept'
            );

            if ($choice === 'edit') {
                $confirmed[$key] = $io->ask(sprintf('Input confirmed value for %s', $label), $candidate) ?? $candidate;

                continue;
            }
            if ($choice === 'unknown') {
                $confirmed[$key] = 'UNKNOWN';

                continue;
            }

            $confirmed[$key] = $candidate;
        }

        return $confirmed;
    }
}
