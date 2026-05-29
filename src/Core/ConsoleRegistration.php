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
use AiProfileManager\Service\PresetRegistry;
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
        ?PresetRegistry $presetRegistry = null,
    ): void {
        $installArgs = ['installer' => $installer];
        $showArgs = ['installer' => $installer, 'checker' => $checker];
        $presetUninstallArgs = ['installer' => $installer, 'checker' => $checker];
        $checkArgs = ['checker' => $checker];

        if ($presetRegistry !== null) {
            $installArgs['presetRegistry'] = $presetRegistry;
            $showArgs['presetRegistry'] = $presetRegistry;
            $presetUninstallArgs['presetRegistry'] = $presetRegistry;
            $checkArgs['presetRegistry'] = $presetRegistry;
        }

        $app->addCommand(new InstallCommand(...$installArgs));
        $app->addCommand(new ShowCommand(...$showArgs));
        $app->addCommand(new SkillInstallCommand($installer));
        $app->addCommand(new RuleInstallCommand($installer));
        $app->addCommand(new AgentInstallCommand($installer));
        $app->addCommand(new SkillUninstallCommand($installer, $checker));
        $app->addCommand(new RuleUninstallCommand($installer, $checker));
        $app->addCommand(new AgentUninstallCommand($installer, $checker));
        $app->addCommand(new PresetUninstallCommand(...$presetUninstallArgs));
        $app->addCommand(new SkillCheckCommand($checker));
        $app->addCommand(new RuleCheckCommand($checker));
        $app->addCommand(new AgentCheckCommand($checker));
        $app->addCommand(new CheckCommand(...$checkArgs));
        $app->addCommand(new PresetCreateCommand($presetRegistry));
        $app->addCommand(new PresetAddAbilityCommand($presetRegistry));
        $app->addCommand(new PresetRemoveAbilityCommand($presetRegistry));
        $app->addCommand(new PresetDeleteCommand($presetRegistry));
        $app->addCommand(new UpdateCommand($updater));
    }
}
