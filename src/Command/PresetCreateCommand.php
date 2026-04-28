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

final class PresetCreateCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('preset:create');
        $this->setDescription('Create a preset in abilities/_presets.json and emit a capture change when the manifest differs from baseline.');
        $this->addArgument('name', InputArgument::REQUIRED, 'Preset name.');
        $this->addOption('skill', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Skill names (repeatable).', []);
        $this->addOption('rule', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rule names (repeatable).', []);
        $this->addOption('agent', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Agent names (repeatable).', []);
        $this->addOption('source-repo', null, InputOption::VALUE_OPTIONAL, 'Source repository identifier.', 'unknown/unknown');
        $this->addOption('source-commit', null, InputOption::VALUE_OPTIONAL, 'Source commit sha.', 'unknown');
        $this->addOption('change-id', null, InputOption::VALUE_OPTIONAL, 'Change identifier.');
        $this->addOption('captured-at', null, InputOption::VALUE_OPTIONAL, 'Capture timestamp (ISO 8601).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $cwd = (string) getcwd();

        /** @var array<int, string> $skills */
        $skills = $input->getOption('skill');
        /** @var array<int, string> $rules */
        $rules = $input->getOption('rule');
        /** @var array<int, string> $agents */
        $agents = $input->getOption('agent');

        $registry = new PresetRegistry($cwd);
        $all = $registry->allPresets();
        if (isset($all[$name])) {
            $io->error(sprintf('Preset already exists: %s', $name));

            return Command::FAILURE;
        }

        $all[$name] = [
            'skills' => array_values(array_unique($skills)),
            'rules' => array_values(array_unique($rules)),
            'agents' => array_values(array_unique($agents)),
        ];
        $registry->saveToWorkspace($all);

        $r = $this->capture->persistPresetManifestCapture(
            $cwd,
            (string) $input->getOption('source-repo'),
            (string) $input->getOption('source-commit'),
            (string) ($input->getOption('change-id') ?: ''),
            (string) ($input->getOption('captured-at') ?: gmdate(DATE_ATOM)),
        );

        if ($r['baseline_missing']) {
            $io->error('Could not resolve Composer baseline.');

            return Command::FAILURE;
        }

        if ($r['unchanged']) {
            $io->writeln('[ok] Preset saved; manifest matches baseline (no change written).');

            return Command::SUCCESS;
        }

        if ($r['path'] !== null) {
            $io->writeln(sprintf('[ok] Change written to changes dir: %s', $r['path']));
        }

        return $r['exit_code'];
    }
}
