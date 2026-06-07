<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallCommand extends Command
{
    use HandlesDeployScopeOption;
    private const BARE_INSTALL_GUIDANCE = <<<'MSG'
Install requires a preset name. Bare `apm install` is no longer supported.

Use instead:
  apm bootstrap                            # project scaffold + bootstrap abilities
  apm add skill|rule|agent|preset <name>   # install specific abilities
MSG;

    private const UNTYPED_ADD_GUIDANCE = <<<'MSG'
Adding an ability requires an explicit type prefix.

Use instead:
  apm add skill <name>
  apm add rule <name>
  apm add agent <name>
  apm add <preset-name>              # install a preset by name
MSG;

    private const DEFAULT_PRESET_MIGRATION = <<<'MSG'
Preset "default" was removed. Use the two-step flow instead:
  1. apm bootstrap                 # project scaffold + bootstrap abilities
  2. apm add preset <name>         # project abilities as needed
MSG;

    public function __construct(
        private readonly Installer $installer,
        private readonly PresetRegistry $presetRegistry = new PresetRegistry(new AbilityRegistry(__DIR__ . '/../../abilities.yaml')),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('install');
        $this->setAliases(['add']);
        $this->setDescription('Install a preset to project scope.');
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name to install.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Target IDE/CLI tool. Repeat for multiple values.'
        );
        $this->configureDeployScopeOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->rejectIfScopeOptionPresent($input);
        } catch (InvalidScopeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        /** @var string|null $preset */
        $preset = $input->getArgument('preset');
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

        if ($preset === null || $preset === '') {
            $io->error(self::BARE_INSTALL_GUIDANCE);

            return Command::FAILURE;
        }

        if ($preset === 'default') {
            $io->error(self::DEFAULT_PRESET_MIGRATION);

            return Command::FAILURE;
        }

        $known = array_map(fn(array $p) => $p['name'], $this->presetRegistry->allPresets());
        if (!in_array($preset, $known, true)) {
            $io->error(self::UNTYPED_ADD_GUIDANCE);

            return Command::FAILURE;
        }

        $presetSpec = $this->presetRegistry->getPreset($preset);
        if ($presetSpec === null) {
            return Command::FAILURE;
        }

        $validationErrors = $this->presetRegistry->validatePresetInstall($presetSpec, $targets);
        if ($validationErrors !== []) {
            $io->error($validationErrors[0]);

            return Command::FAILURE;
        }

        $items = PresetRegistry::toTypedSpec($presetSpec);
        $io->writeln("Preset: {$preset}");
        $result = $this->installer->installTyped($items, $targets, $preset);
        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
