<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('show');
        $this->setDescription('Show all installable skills, agents, and rules with install status and preset mapping.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
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

        $available = $this->installer->listAvailableItems();
        $results = $this->checker->checkTyped($available, $targets);
        $installedMap = $this->buildInstalledMap($results, $targets);
        $presetMap = $this->buildPresetMap((new PresetRegistry((string) getcwd()))->allPresets());

        $io->writeln('Targets: ' . implode(', ', $targets));
        $io->newLine();

        $this->renderTypeSection($io, 'Skills', 'skill', $available['skills'], $installedMap, $presetMap);
        $this->renderTypeSection($io, 'Agents', 'agent', $available['agents'], $installedMap, $presetMap);
        $this->renderTypeSection($io, 'Rules', 'rule', $available['rules'], $installedMap, $presetMap);

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
     * @param array<string, array{skills: array<int, string>, rules: array<int, string>, agents: array<int, string>}> $presets
     * @return array<string, array<int, string>>
     */
    private function buildPresetMap(array $presets): array
    {
        $map = [];
        foreach ($presets as $presetName => $spec) {
            foreach ($spec['skills'] as $name) {
                $map['skill:' . $name][] = $presetName;
            }
            foreach ($spec['agents'] as $name) {
                $map['agent:' . $name][] = $presetName;
            }
            foreach ($spec['rules'] as $name) {
                $map['rule:' . $name][] = $presetName;
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
