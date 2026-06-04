<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DefaultGlobalSetupService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GlobalSetupService;
use AiProfileManager\Service\HookInstaller;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GlobalSetupCommand extends Command
{
    public function __construct(
        private readonly GlobalSetupService $setupService,
    ) {
        parent::__construct();
    }

    public static function create(Installer $installer): self
    {
        $registry = $installer->registry();
        $scopeResolver = new DeployRootResolver();

        return new self(
            new DefaultGlobalSetupService(
                $registry,
                $installer->packageRoot(),
                new InstallationProbe($registry, $scopeResolver),
                $scopeResolver,
                new DirectoryMirrorService(),
                new HookInstaller(),
            ),
        );
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (InvalidOptionException|ConsoleInvalidArgumentException $e) {
            throw new ConsoleRuntimeException($e->getMessage(), 0, $e);
        }
    }

    protected function configure(): void
    {
        $this->setName('global-setup');
        $this->setDescription('Install Global Setup List abilities to user scope (HOME).');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing user-scope installs from package baseline.');
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Target IDE/CLI tool. Repeat for multiple values.',
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
                implode(', ', AppConfig::KNOWN_TARGETS),
            ));

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        $result = $this->setupService->run($force, $targets);

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
