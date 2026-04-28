<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Capture\CaptureChangeIngestor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class IngestCaptureChangeCommand extends Command
{
    public function __construct(private readonly CaptureChangeIngestor $ingestor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ingest');
        $this->setDescription('Ingest CaptureChange files from ~/.aipm/changes and write-back.');
        $this->addOption('changes-dir', null, InputOption::VALUE_OPTIONAL, 'Changes directory. Defaults to ~/.aipm/changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $changesDir = $input->getOption('changes-dir');
        $result = $this->ingestor->ingestChanges(
            is_string($changesDir) && $changesDir !== '' ? $changesDir : null,
            true
        );

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
