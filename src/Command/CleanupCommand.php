<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Service\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException as ConsoleInvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanupCommand extends Command
{
    public function __construct(
        private readonly Installer $installer,
    ) {
        parent::__construct();
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
        $this->setName('cleanup');
        $this->setDescription(
            'Uninstall project-scope conventional abilities (registry enumeration).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->installer->uninstallProjectScope();

        foreach ($result['lines'] as $line) {
            $output->writeln($line);
        }

        if ($result['exit_code'] === 0) {
            $output->writeln('');
            $output->writeln(
                'To reinstall project abilities, run /apm init (user-scope global-setup is unchanged).',
            );
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
