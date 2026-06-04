<?php

declare(strict_types=1);

namespace AiProfileManager\Command;

use AiProfileManager\Config\DeployScope;
use AiProfileManager\Service\DeployRootResolver;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\InvalidScopeException;
use AiProfileManager\Service\PresetRegistry;
use AiProfileManager\Service\ScopeGuard;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

trait HandlesDeployScopeOption
{
    protected function configureDeployScopeOption(): void
    {
        $this->addOption(
            'scope',
            null,
            InputOption::VALUE_REQUIRED,
            'Deploy scope (project or user). Default: project.',
        );
    }

    /**
     * @return DeployScope|null null when parse failed (error already written)
     */
    protected function resolveDeployScopeOption(InputInterface $input, SymfonyStyle $io): ?DeployScope
    {
        /** @var string|null $scopeOption */
        $scopeOption = $input->getOption('scope');

        try {
            return (new DeployRootResolver())->parseScopeOption($scopeOption);
        } catch (InvalidScopeException $e) {
            $io->error($e->getMessage());

            return null;
        }
    }

    /**
     * @param array{skills: list<string>, rules: list<string>, agents: list<string>, hooks?: list<string>, prompts?: list<string>} $typed
     */
    protected function guardInstallBatch(Installer $installer, DeployScope $scope, array $typed, SymfonyStyle $io): bool
    {
        try {
            (new ScopeGuard($installer->registry()))->assertBatchAllowed(
                $scope,
                PresetRegistry::typedSpecToBatchRefs($typed),
            );
        } catch (InvalidScopeException $e) {
            $io->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param array{name: string, description: string, includes: list<array{type: string, path: string}>} $presetSpec
     */
    protected function guardPresetInstall(Installer $installer, DeployScope $scope, array $presetSpec, SymfonyStyle $io): bool
    {
        try {
            (new ScopeGuard($installer->registry()))->assertBatchAllowed(
                $scope,
                PresetRegistry::presetIncludesToBatchRefs($presetSpec),
            );
        } catch (InvalidScopeException $e) {
            $io->error($e->getMessage());

            return false;
        }

        return true;
    }
}
