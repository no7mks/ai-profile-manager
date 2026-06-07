<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\AbilityUpdateService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\ComposerBaselineResolver;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\GlobalInstallDetector;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UpdateCommand extends Command
{
    public function __construct(
        private readonly AbilityUpdateService $updateService,
        private readonly GlobalInstallDetector $globalDetector = new GlobalInstallDetector(),
    ) {
        parent::__construct();
    }

    public static function create(Installer $installer): self
    {
        $registry = $installer->registry();
        $scopeResolver = new DeployRootResolver();
        $baselineResolver = new ComposerBaselineResolver();

        return new self(
            new AbilityUpdateService(
                $registry,
                $baselineResolver,
                new CheckService($baselineResolver),
                new InstallationProbe($registry, $scopeResolver),
                $scopeResolver,
            ),
        );
    }

    protected function configure(): void
    {
        $this->setName('update');
        $this->setDescription('Report or apply baseline updates for installed conventional abilities.');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite differing installed files from baseline.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->globalDetector->isGlobalInvocation()) {
            $io->error(
                'Update must be run from the global apm installation (composer global require). '
                . 'Use the apm binary under your Composer global vendor/bin directory.',
            );

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        $result = $this->updateService->reportChanges($force);

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'];
    }
}
