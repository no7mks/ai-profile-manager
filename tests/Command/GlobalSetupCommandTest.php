<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\GlobalSetupCommand;
use AiProfileManager\Config\AppConfig;
use AiProfileManager\Service\GlobalSetupService;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GlobalSetupCommandTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;

    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-global-setup-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testDefinitionHasNoScopeOrPresetArguments(): void
    {
        $command = new GlobalSetupCommand($this->createStub(GlobalSetupService::class));
        $definition = $command->getDefinition();

        self::assertFalse($definition->hasOption('scope'));
        self::assertFalse($definition->hasArgument('preset'));
        self::assertTrue($definition->hasOption('force'));
        self::assertTrue($definition->hasOption('target'));
        self::assertSame('global-setup', $command->getName());
    }

    public function testRejectsUnknownScopeOption(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::never())->method('run');

        $tester = new CommandTester(new GlobalSetupCommand($service));

        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $tester->execute(['--scope' => 'user']);
    }

    public function testRejectsPresetArgument(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::never())->method('run');

        $tester = new CommandTester(new GlobalSetupCommand($service));

        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $tester->execute(['preset' => 'default']);
    }

    public function testDelegatesToServiceWithDefaultTargetsWhenTargetOmitted(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(false, AppConfig::DEFAULT_TARGETS)
            ->willReturn([
                'lines' => ['Global setup complete.', 'Next: run /apm init in your business repository.'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('/apm init', $tester->getDisplay());
    }

    public function testPassesForceFlagToService(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(true, AppConfig::DEFAULT_TARGETS)
            ->willReturn([
                'lines' => ['[ok] Updated skill:apm (user) cursor'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    public function testPassesExplicitTargetsToService(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(false, ['cursor'])
            ->willReturn([
                'lines' => ['[ok] Installed skill:apm (user) cursor'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    public function testSucceedsFromArbitraryWorkingDirectory(): void
    {
        $isolated = $this->tmpDir . '/isolated-cwd';
        mkdir($isolated, 0775, true);
        chdir($isolated);

        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(false, AppConfig::DEFAULT_TARGETS)
            ->willReturn([
                'lines' => ['Global setup complete.', 'Next: run /apm init in your business repository.'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('/apm init', $tester->getDisplay());
    }

    public function testIdempotentRunStillExitsSuccess(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(false, AppConfig::DEFAULT_TARGETS)
            ->willReturn([
                'lines' => ['[ok] skill:apm (user) cursor — already up to date'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('already up to date', $tester->getDisplay());
    }

    public function testMissingTargetEmitsSkipWithoutFailingRun(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->with(false, ['cursor'])
            ->willReturn([
                'lines' => [
                    '[ok] Installed skill:apm (user) cursor',
                    '[skip] agent:kiro-only-agent (cursor): no cursor target in registry',
                ],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('[skip]', $display);
        self::assertStringContainsString('no cursor target', $display);
    }

    public function testSuccessOutputPromptsApmInit(): void
    {
        $service = $this->createStub(GlobalSetupService::class);
        $service->method('run')->willReturn([
            'lines' => [
                '[ok] Installed skill:apm (user) cursor',
                '',
                'Next step: open a business repository and run /apm init.',
            ],
            'exit_code' => 0,
        ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertMatchesRegularExpression('#/apm\s+init#i', $tester->getDisplay());
    }

    public function testServiceFailurePropagatesExitCode(): void
    {
        $service = $this->createStub(GlobalSetupService::class);
        $service->method('run')->willReturn([
            'lines' => ['[fail] Baseline not found.'],
            'exit_code' => 1,
        ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('[fail]', $tester->getDisplay());
    }

    public function testOnlyGlobalSetupListEntriesAreInstalledViaServiceContract(): void
    {
        $registryPath = $this->tmpDir . '/abilities.yaml';
        file_put_contents($registryPath, implode("\n", [
            'global-setup:',
            '  includes:',
            '    - skill:apm',
            '    - agent:code-reviewer',
            'skills:',
            '  - path: apm',
            '    description: apm',
            '    scopes: [user, project]',
            '    targets:',
            '      cursor: .cursor/skills/apm/',
            '  - path: graphify',
            '    description: not in global setup',
            '    scopes: [user, project]',
            '    targets:',
            '      cursor: .cursor/skills/graphify/',
            'agents:',
            '  - path: code-reviewer',
            '    description: reviewer',
            '    scopes: [user, project]',
            '    targets:',
            '      cursor: .cursor/agents/code-reviewer.md',
            'presets:',
            '  - name: php',
            '    description: php preset',
            '    includes:',
            '      - skill:graphify',
        ]) . "\n");

        $expectedIncludes = (new \AiProfileManager\Service\AbilityRegistry($registryPath))->globalSetupIncludes();
        self::assertCount(2, $expectedIncludes);

        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->willReturnCallback(function (bool $force, array $targets) use ($expectedIncludes): array {
                self::assertFalse($force);
                self::assertSame(['cursor'], $targets);

                $lines = [];
                foreach ($expectedIncludes as $include) {
                    $lines[] = sprintf('[ok] Installed %s:%s (user) cursor', $include['type'], $include['path']);
                }

                return ['lines' => $lines, 'exit_code' => 0];
            });

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('skill:apm', $tester->getDisplay());
        self::assertStringContainsString('agent:code-reviewer', $tester->getDisplay());
        self::assertStringNotContainsString('graphify', $tester->getDisplay());
        self::assertStringNotContainsString('preset', strtolower($tester->getDisplay()));
    }

    public function testUserScopeOnlyContractUsesUserNotProjectScope(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::once())
            ->method('run')
            ->willReturn([
                'lines' => ['[ok] Installed skill:apm (user) cursor'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new GlobalSetupCommand($service));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('(user)', $tester->getDisplay());
        self::assertStringNotContainsString('(project)', $tester->getDisplay());
    }

    public function testInteractDoesNotAcceptScopeViaArrayInput(): void
    {
        $service = $this->createMock(GlobalSetupService::class);
        $service->expects(self::never())->method('run');

        $tester = new CommandTester(new GlobalSetupCommand($service));

        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $tester->execute(['--scope' => 'project']);
    }
}
