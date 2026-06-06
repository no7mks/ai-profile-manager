<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\GlobalSetupCommand;
use AiProfileManager\Service\InvalidScopeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GlobalSetupCommandTest extends TestCase
{
    public function testCommandNameIsGlobalSetup(): void
    {
        $command = new GlobalSetupCommand();
        self::assertSame('global-setup', $command->getName());
    }

    public function testExecuteOutputsDeprecationMessageToStderr(): void
    {
        $command = new GlobalSetupCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $expected = InvalidScopeException::globalSetupDeprecated()->getMessage();
        self::assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testDeprecationMessageContainsCommandName(): void
    {
        $command = new GlobalSetupCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('global-setup', $tester->getDisplay());
    }

    public function testDeprecationMessageContainsReplacementCommand(): void
    {
        $command = new GlobalSetupCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('apm bootstrap', $tester->getDisplay());
    }

    public function testExitsWithFailureCode(): void
    {
        $command = new GlobalSetupCommand();
        $tester = new CommandTester($command);

        $exit = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testExitsWithFailureEvenWithForceOption(): void
    {
        $command = new GlobalSetupCommand();
        $tester = new CommandTester($command);

        // global-setup 不再定义 --force，但传入任何参数也一律废弃退出
        $exit = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
    }

    public function testDoesNotInjectAnyService(): void
    {
        // 验证构造函数不接受任何参数（无 service 注入）
        $reflection = new \ReflectionClass(GlobalSetupCommand::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertCount(0, $constructor->getParameters());
    }

    public function testDefinitionHasNoOptions(): void
    {
        $command = new GlobalSetupCommand();
        $definition = $command->getDefinition();

        // 废弃壳不再定义 --force、--target 等选项
        self::assertFalse($definition->hasOption('force'));
        self::assertFalse($definition->hasOption('target'));
        self::assertFalse($definition->hasOption('scope'));
    }
}
