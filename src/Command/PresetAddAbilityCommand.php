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

final class PresetAddAbilityCommand extends Command
{
    public function __construct(private readonly CaptureService $capture)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('preset:add-ability')
            ->setDescription('Add one ability reference to a preset and emit capture when manifest changes.')
            ->addArgument('preset', InputArgument::REQUIRED, 'Preset name.')
            ->addArgument('ability', InputArgument::REQUIRED, 'Ability name.')
            ->addOption('skill', null, InputOption::VALUE_NONE, 'Treat ability as a skill.')
            ->addOption('rule', null, InputOption::VALUE_NONE, 'Treat ability as a rule.')
            ->addOption('agent', null, InputOption::VALUE_NONE, 'Treat ability as an agent.')
            ->addOption('source-repo', null, InputOption::VALUE_OPTIONAL, 'Source repository identifier.', 'unknown/unknown')
            ->addOption('source-commit', null, InputOption::VALUE_OPTIONAL, 'Source commit sha.', 'unknown')
            ->addOption('event-id', null, InputOption::VALUE_OPTIONAL, 'Event identifier.')
            ->addOption('captured-at', null, InputOption::VALUE_OPTIONAL, 'Capture timestamp (ISO 8601).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $presetName = (string) $input->getArgument('preset');
        $ability = (string) $input->getArgument('ability');

        $flags = (int) $input->getOption('skill') + (int) $input->getOption('rule') + (int) $input->getOption('agent');
        if ($flags !== 1) {
            $io->error('Specify exactly one of --skill, --rule, or --agent.');

            return Command::FAILURE;
        }

        $cwd = (string) getcwd();
        $registry = new PresetRegistry($cwd);
        $all = $registry->allPresets();
        if (!isset($all[$presetName])) {
            $io->error(sprintf('Unknown preset: %s', $presetName));

            return Command::FAILURE;
        }

        $spec = $all[$presetName];
        $key = $input->getOption('skill') ? 'skills' : ($input->getOption('rule') ? 'rules' : 'agents');
        if (in_array($ability, $spec[$key], true)) {
            $io->writeln(sprintf('[ok] Ability already in preset (%s): %s', $key, $ability));

            return Command::SUCCESS;
        }

        $spec[$key][] = $ability;
        $spec['skills'] = array_values(array_unique($spec['skills']));
        $spec['rules'] = array_values(array_unique($spec['rules']));
        $spec['agents'] = array_values(array_unique($spec['agents']));
        $all[$presetName] = $spec;
        $registry->saveToWorkspace($all);

        return $this->finishManifestCapture($io, $input, $cwd);
    }

    private function finishManifestCapture(SymfonyStyle $io, InputInterface $input, string $cwd): int
    {
        $r = $this->capture->persistPresetManifestCapture(
            $cwd,
            (string) $input->getOption('source-repo'),
            (string) $input->getOption('source-commit'),
            (string) ($input->getOption('event-id') ?: ''),
            (string) ($input->getOption('captured-at') ?: gmdate(DATE_ATOM)),
        );

        if ($r['baseline_missing']) {
            $io->error('Could not resolve Composer baseline.');

            return Command::FAILURE;
        }

        if ($r['unchanged']) {
            $io->writeln('[ok] Manifest matches baseline (no event written).');

            return Command::SUCCESS;
        }

        if ($r['path'] !== null) {
            $io->writeln(sprintf('[ok] Event written to events dir: %s', $r['path']));
        }

        return $r['exit_code'];
    }
}
