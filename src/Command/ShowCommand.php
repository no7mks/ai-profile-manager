<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ShowCommand extends Command
{
    public function __construct(
        private readonly Installer $installer,
        private readonly CheckService $checker,
        private readonly PresetRegistry $presetRegistry = new PresetRegistry(new AbilityRegistry(__DIR__ . '/../../abilities.yaml')),
    ) {
        parent::__construct();
    }

    private const KNOWN_TYPES = ['rule', 'agent', 'skill', 'hook', 'gitignore', 'preset'];

    protected function configure(): void
    {
        $this->setName('show');
        $this->setDescription('Show all installable skills, agents, and rules with install status and preset mapping.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by ability type (rule, agent, skill, hook, gitignore, preset).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var array<int, string> $targets */
        $targets = $input->getOption('target');
        $targets = $targets === [] ? AppConfig::DEFAULT_TARGETS : array_values(array_unique($targets));
        $unknownTargets = array_values(array_diff($targets, AppConfig::KNOWN_TARGETS));
        if ($unknownTargets !== []) {
            $io->error(sprintf('Unknown targets: %s. Known targets: %s.', implode(', ', $unknownTargets), implode(', ', AppConfig::KNOWN_TARGETS)));

            return Command::FAILURE;
        }

        /** @var string|null $typeFilter */
        $typeFilter = $input->getOption('type');
        if ($typeFilter !== null && !in_array($typeFilter, self::KNOWN_TYPES, true)) {
            $io->error(sprintf('Unknown type: %s. Known types: %s.', $typeFilter, implode(', ', self::KNOWN_TYPES)));

            return Command::FAILURE;
        }

        $available = $this->installer->listAvailableItems();
        $results = $this->checker->checkTyped($available, $targets);
        $installedMap = $this->buildInstalledMap($results, $targets);
        $presetMap = $this->buildPresetMap($this->presetRegistry->allPresets());

        $io->writeln('Targets: ' . implode(', ', $targets));
        $io->newLine();

        if ($typeFilter === null || $typeFilter === 'skill') {
            $this->renderTypeSection($io, 'Skills', 'skill', $available['skills'], $installedMap, $presetMap);
        }
        if ($typeFilter === null || $typeFilter === 'agent') {
            $this->renderTypeSection($io, 'Agents', 'agent', $available['agents'], $installedMap, $presetMap);
        }
        if ($typeFilter === null || $typeFilter === 'rule') {
            $this->renderTypeSection($io, 'Rules', 'rule', $available['rules'], $installedMap, $presetMap);
        }
        if ($typeFilter === null || $typeFilter === 'hook') {
            $this->renderTypeSection($io, 'Hooks', 'hook', $available['hooks'] ?? [], $installedMap, $presetMap);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array{type: string, name: string, target: string, status: string}> $results
     * @param array<int, string> $targets
     * @return array<string, bool>
     */
    private function buildInstalledMap(array $results, array $targets): array
    {
        $map = [];
        foreach ($results as $result) {
            $key = $result['type'] . ':' . $result['name'];
            $isInstalled = $result['status'] === 'unchanged' || $result['status'] === 'modified';
            if ($isInstalled) {
                $map[$key] = true;
                continue;
            }
            if (!isset($map[$key])) {
                $map[$key] = false;
            }
        }

        foreach ($map as $key => $installed) {
            if ($installed) {
                continue;
            }
            [$type, $name] = explode(':', $key, 2);
            foreach ($targets as $target) {
                if ($this->installer->isInstalledOnTarget($type, $name, $target)) {
                    $map[$key] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param list<array{name: string, description: string, includes: list<array{type: string, path: string}>}> $presets
     * @return array<string, array<int, string>>
     */
    private function buildPresetMap(array $presets): array
    {
        $map = [];
        foreach ($presets as $preset) {
            $presetName = $preset['name'];
            foreach ($preset['includes'] as $include) {
                $key = $include['type'] . ':' . $include['path'];
                $map[$key][] = $presetName;
            }
        }

        foreach ($map as &$presetNames) {
            $presetNames = array_values(array_unique($presetNames));
            sort($presetNames);
        }
        unset($presetNames);

        return $map;
    }

    /**
     * @param array<int, string> $names
     * @param array<string, bool> $installedMap
     * @param array<string, array<int, string>> $presetMap
     */
    private function renderTypeSection(
        SymfonyStyle $io,
        string $title,
        string $type,
        array $names,
        array $installedMap,
        array $presetMap
    ): void {
        $io->section($title);
        if ($names === []) {
            $io->writeln('  (none)');

            return;
        }

        foreach ($names as $name) {
            $key = $type . ':' . $name;
            $installed = $installedMap[$key] ?? false;
            $statusText = $installed ? '<info>[installed]</info>' : '[not-installed]';
            $presets = $presetMap[$key] ?? [];
            $presetText = $presets === [] ? '-' : implode(', ', $presets);
            $line = sprintf(
                '  %s %s  presets: %s',
                $statusText,
                OutputFormatter::escape($name),
                OutputFormatter::escape($presetText)
            );
            $io->writeln($line);
        }
    }
}
