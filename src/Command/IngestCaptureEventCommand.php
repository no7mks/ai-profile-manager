<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Capture\CaptureEventIngestor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class IngestCaptureEventCommand extends Command
{
    public function __construct(private readonly CaptureEventIngestor $ingestor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ingest');
        $this->setDescription('Ingest CaptureEvent files from ~/.aipm/events and write-back.');
        $this->addOption('events-dir', null, InputOption::VALUE_OPTIONAL, 'Events directory. Defaults to ~/.aipm/events.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eventsDir = $input->getOption('events-dir');
        $result = $this->ingestor->ingestEvents(
            is_string($eventsDir) && $eventsDir !== '' ? $eventsDir : null,
            true
        );

        foreach ($result['lines'] as $line) {
            $io->writeln($line);
        }

        return $result['exit_code'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
