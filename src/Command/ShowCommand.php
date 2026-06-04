<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\InstallationProbe;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\ShowStatusPresenter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ShowCommand extends Command
{
    /** @var list<string> */
    private const KNOWN_TYPES = ['rule', 'agent', 'skill', 'hook', 'gitignore', 'prompt'];

    public function __construct(
        private readonly ShowStatusPresenter $presenter,
        private readonly DeployRootResolver $scopeResolver = new DeployRootResolver(),
    ) {
        parent::__construct();
    }

    public static function create(Installer $installer, CheckService $checker): self
    {
        $scopeResolver = new DeployRootResolver();
        $registry = $installer->registry();

        return new self(
            new ShowStatusPresenter(
                $registry,
                $checker,
                new InstallationProbe($registry, $scopeResolver),
                $installer->packageRoot(),
                $scopeResolver,
            ),
            $scopeResolver,
        );
    }

    protected function configure(): void
    {
        $this->setName('show');
        $this->setDescription('Show conventional abilities with install status per deploy scope.');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target IDE/CLI tool.');
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Filter by deploy scope (project or user). Omit for merged user+project view.');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by ability type (rule, agent, skill, hook, gitignore, prompt).');
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

        /** @var string|null $scopeOption */
        $scopeOption = $input->getOption('scope');
        $scopeFilter = null;
        if ($scopeOption !== null) {
            try {
                $scopeFilter = $this->scopeResolver->parseScopeOption($scopeOption);
            } catch (InvalidScopeException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        foreach ($this->presenter->lines($scopeFilter, $targets, $typeFilter) as $line) {
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}
