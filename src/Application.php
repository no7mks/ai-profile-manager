<?php

declare(strict_types=1);

namespace AiProfileManager;

use AiProfileManager\CaptureService;
use AiProfileManager\CheckService;
use AiProfileManager\Command\AgentCaptureCommand;
use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\RuleCaptureCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\SkillCaptureCommand;
use AiProfileManager\Command\SkillCheckCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\UpdateCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

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
    public function run(array $argv): int
    {
        $checker = new CheckService();
        $capture = new CaptureService($checker);

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
        $app->add(new UpdateCommand($this->updater));

        return $app->run();
    }
}
