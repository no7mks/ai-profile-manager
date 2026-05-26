<?php

declare(strict_types=1);

namespace AiProfileManager\Core;

use AiProfileManager\Command\AgentCheckCommand;
use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\CheckCommand;
use AiProfileManager\Command\InstallCommand;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Command\PresetUninstallCommand;
use AiProfileManager\Command\RuleCheckCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Command\SkillCheckCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Command\SkillUninstallCommand;
use AiProfileManager\Command\UpdateCommand;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\KnowledgeBaseUpdater;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Registers all apm console commands on a Symfony Application instance.
 *
 * Kept separate from {@see Application} so registration can be covered without running the full CLI runner.
 */
final class ConsoleRegistration
{
    public static function register(
        SymfonyApplication $app,
        Installer $installer,
        CheckService $checker,
        KnowledgeBaseUpdater $updater,
    ): void {
        $app->addCommand(new InstallCommand($installer));
        $app->addCommand(new ShowCommand($installer, $checker));
        $app->addCommand(new SkillInstallCommand($installer));
        $app->addCommand(new RuleInstallCommand($installer));
        $app->addCommand(new AgentInstallCommand($installer));
        $app->addCommand(new SkillUninstallCommand($installer, $checker));
        $app->addCommand(new RuleUninstallCommand($installer, $checker));
        $app->addCommand(new AgentUninstallCommand($installer, $checker));
        $app->addCommand(new PresetUninstallCommand($installer, $checker));
        $app->addCommand(new SkillCheckCommand($checker));
        $app->addCommand(new RuleCheckCommand($checker));
        $app->addCommand(new AgentCheckCommand($checker));
        $app->addCommand(new CheckCommand($checker));
        $app->addCommand(new PresetCreateCommand());
        $app->addCommand(new PresetAddAbilityCommand());
        $app->addCommand(new PresetRemoveAbilityCommand());
        $app->addCommand(new PresetDeleteCommand());
        $app->addCommand(new UpdateCommand($updater));
    }
}
