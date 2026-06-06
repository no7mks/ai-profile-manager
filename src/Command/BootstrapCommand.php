<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Config\PackagePaths;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\ProjectInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class BootstrapCommand extends Command
{
    public function __construct(
        private readonly ?ProjectInitializer $initializer = null,
        private readonly ?AbilityRegistry $registry = null,
        private readonly ?Installer $installer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('bootstrap');
        $this->setDescription('Install project scaffold and bootstrap abilities.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Target IDE/CLI tool. Repeat for multiple values.'
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing scaffold files and force-reinstall abilities.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        // --- Phase 1: Scaffold ---
        try {
            $initializer = $this->initializer ?? ProjectInitializer::fromPackageLayout();
            foreach ($initializer->init((string) getcwd(), $force, $targets) as $line) {
                $io->writeln($line);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // --- Phase 2: Ability installation ---
        $registry = $this->registry ?? new AbilityRegistry(PackagePaths::packageRoot() . '/abilities.yaml');
        $includes = $registry->bootstrapIncludes();

        // AC 8: empty includes → scaffold only, exit 0
        if ($includes === []) {
            $io->success("Bootstrap complete. In your agent chat, run '/apm init' to complete SSOT setup.");

            return Command::SUCCESS;
        }

        // Validate references exist in registry (fail-fast)
        try {
            $registry->validateBootstrapIncludes($includes);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // Install each include item
        $installer = $this->installer ?? new Installer(registry: $registry);
        $hasFailure = false;

        foreach ($includes as $include) {
            $type = $include['type'];
            $path = $include['path'];

            // Build typed items array with only this entry
            $items = ['skills' => [], 'rules' => [], 'agents' => [], 'hooks' => []];
            $section = $type . 's'; // skill → skills, rule → rules, etc.
            if (isset($items[$section])) {
                $items[$section] = [$path];
            }

            $result = $installer->installTyped(
                $items,
                $targets,
                null,
                skipExisting: !$force,
            );

            // Output installer lines
            foreach ($result['lines'] as $line) {
                $io->writeln($line);
            }

            // Check for failure
            if ($result['exit_code'] !== 0) {
                $io->writeln(sprintf('[fail] %s:%s', $type, $path));
                $hasFailure = true;
            }
        }

        if ($hasFailure) {
            return Command::FAILURE;
        }

        $io->success("Bootstrap complete. In your agent chat, run '/apm init' to complete SSOT setup.");

        return Command::SUCCESS;
    }
}
