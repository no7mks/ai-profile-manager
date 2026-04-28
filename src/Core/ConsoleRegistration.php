<?php

declare(strict_types=1);

namespace AiProfileManager\Core;

use AiProfileManager\Capture\CaptureChangeIngestor;
use AiProfileManager\Command\AgentCaptureCommand;
use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\IngestCaptureChangeCommand;
use AiProfileManager\Command\InitCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
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
use AiProfileManager\Service\ProjectInitializer;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Registers all aipm console commands on a Symfony Application instance.
 *
 * Kept separate from {@see Application} so registration can be covered without running the full CLI runner.
 */
final class ConsoleRegistration
{
    public static function register(
        SymfonyApplication $app,
        Installer $installer,
        CheckService $checker,
        CaptureService $capture,
        CaptureChangeIngestor $ingestor,
        KnowledgeBaseUpdater $updater,
    ): void {
        $app->add(new InstallCommand($installer));
        $app->add(new InitCommand(ProjectInitializer::fromPackageLayout()));
        $app->add(new SkillInstallCommand($installer));
        $app->add(new RuleInstallCommand($installer));
        $app->add(new AgentInstallCommand($installer));
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
        $app->add(new UpdateCommand($updater));
        $app->add(new IngestCaptureChangeCommand($ingestor));
    }
}
