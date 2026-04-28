<?php

declare(strict_types=1);

namespace AiProfileManager\Core;

use AiProfileManager\Capture\CaptureEventIngestor;
use AiProfileManager\Command\AgentCaptureCommand;
use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Command\IngestCaptureEventCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\RuleCaptureCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\SkillCaptureCommand;
use AiProfileManager\Command\SkillCheckCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\UpdateCommand;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
    public function __construct(
        private readonly Installer $installer = new Installer(),
        private readonly KnowledgeBaseUpdater $updater = new KnowledgeBaseUpdater(),
    ) {
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv, ?OutputInterface $output = null): int
    {
        $checker = new CheckService();
        $capture = new CaptureService($checker);
        $ingestor = new CaptureEventIngestor();

        $app = new SymfonyApplication('aipm', '0.1.0');
        $app->setDefaultCommand('list');
        $app->add(new InstallCommand($this->installer));
        $app->add(new SkillInstallCommand($this->installer));
        $app->add(new RuleInstallCommand($this->installer));
        $app->add(new AgentInstallCommand($this->installer));
        $app->add(new SkillCheckCommand($checker));
        $app->add(new RuleCheckCommand($checker));
        $app->add(new AgentCheckCommand($checker));
        $app->add(new SkillCaptureCommand($capture));
        $app->add(new RuleCaptureCommand($capture));
        $app->add(new AgentCaptureCommand($capture));
        $app->add(new CheckCommand($checker));
        $app->add(new CaptureCommand($capture));
        $app->add(new PresetCreateCommand($capture));
        $app->add(new PresetAddAbilityCommand($capture));
        $app->add(new PresetRemoveAbilityCommand($capture));
        $app->add(new PresetDeleteCommand($capture));
        $app->add(new UpdateCommand($this->updater));
        $app->add(new IngestCaptureEventCommand($ingestor));

        return $app->run(new ArgvInput($argv), $output ?? new ConsoleOutput());
    }
}
