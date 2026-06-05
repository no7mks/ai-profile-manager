<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\HandlesDeployScopeOption;
use AiProfileManager\Service\InvalidScopeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class HandlesDeployScopeOptionTest extends TestCase
{
    private function createCommandUsingTrait(): Command
    {
        return new class extends Command {
            use HandlesDeployScopeOption;

            protected static $defaultName = 'test:scope';

            protected function configure(): void
            {
                $this->configureDeployScopeOption();
            }

            public function publicRejectIfScopeOptionPresent(InputInterface $input): void
            {
                $this->rejectIfScopeOptionPresent($input);
            }
        };
    }

    public function testConfigureDeployScopeOptionRegistersOption(): void
    {
        $command = $this->createCommandUsingTrait();

        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('scope'));
        $option = $definition->getOption('scope');
        self::assertTrue($option->isValueRequired());
    }

    public function testRejectIfScopeOptionPresentPassesSilentlyWhenNull(): void
    {
        $command = $this->createCommandUsingTrait();
        $definition = $command->getDefinition();

        // No --scope provided → getOption('scope') returns null
        $input = new ArrayInput([], $definition);

        // Should not throw
        $command->publicRejectIfScopeOptionPresent($input);

        // If we reach here, no exception was thrown
        self::assertTrue(true);
    }

    public function testRejectIfScopeOptionPresentThrowsWhenScopeProvided(): void
    {
        $command = $this->createCommandUsingTrait();
        $definition = $command->getDefinition();

        $input = new ArrayInput(['--scope' => 'project'], $definition);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('The --scope option has been removed. All operations now target project scope only.');

        $command->publicRejectIfScopeOptionPresent($input);
    }

    public function testRejectIfScopeOptionPresentThrowsForAnyValue(): void
    {
        $command = $this->createCommandUsingTrait();
        $definition = $command->getDefinition();

        $input = new ArrayInput(['--scope' => 'user'], $definition);

        $this->expectException(InvalidScopeException::class);

        $command->publicRejectIfScopeOptionPresent($input);
    }

    public function testTraitNoLongerProvidesResolveMethod(): void
    {
        $command = $this->createCommandUsingTrait();

        self::assertFalse(method_exists($command, 'resolveDeployScopeOption'));
    }

    public function testTraitNoLongerProvidesGuardInstallBatch(): void
    {
        $command = $this->createCommandUsingTrait();

        self::assertFalse(method_exists($command, 'guardInstallBatch'));
    }

    public function testTraitNoLongerProvidesGuardPresetInstall(): void
    {
        $command = $this->createCommandUsingTrait();

        self::assertFalse(method_exists($command, 'guardPresetInstall'));
    }
}
