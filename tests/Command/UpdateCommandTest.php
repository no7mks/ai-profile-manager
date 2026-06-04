<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\UpdateCommand;
use AiProfileManager\Service\AbilityUpdateService;
use AiProfileManager\Service\GlobalInstallDetector;
use AiProfileManager\Tests\Support\RemovesDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UpdateCommandTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;
    private string|false $oldBaseline;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-update-cmd-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
        $this->oldCwd = getcwd();
        $this->oldBaseline = getenv('APM_BASELINE_ROOT');
    }

    protected function tearDown(): void
    {
        if ($this->oldCwd !== false) {
            chdir($this->oldCwd);
        }
        if ($this->oldBaseline === false) {
            putenv('APM_BASELINE_ROOT');
        } else {
            putenv('APM_BASELINE_ROOT=' . $this->oldBaseline);
        }
        $this->removeDir($this->tmpDir);
    }

    public function testRejectsNonGlobalInvocation(): void
    {
        $detector = $this->createStub(GlobalInstallDetector::class);
        $detector->method('isGlobalInvocation')->willReturn(false);

        $service = $this->createMock(AbilityUpdateService::class);
        $service->expects(self::never())->method('reportChanges');

        $tester = new CommandTester(new UpdateCommand($service, $detector));
        $exit = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('global apm', $tester->getDisplay());
    }

    public function testGlobalInvocationDelegatesToService(): void
    {
        $detector = $this->createStub(GlobalInstallDetector::class);
        $detector->method('isGlobalInvocation')->willReturn(true);

        $service = $this->createMock(AbilityUpdateService::class);
        $service->expects(self::once())
            ->method('reportChanges')
            ->with(false)
            ->willReturn([
                'lines' => ['All installed abilities are up to date.'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new UpdateCommand($service, $detector));
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('up to date', $tester->getDisplay());
    }

    public function testForceOptionPassedToService(): void
    {
        $detector = $this->createStub(GlobalInstallDetector::class);
        $detector->method('isGlobalInvocation')->willReturn(true);

        $service = $this->createMock(AbilityUpdateService::class);
        $service->expects(self::once())
            ->method('reportChanges')
            ->with(true)
            ->willReturn([
                'lines' => ['[ok] Updated skill:demo (project) cursor'],
                'exit_code' => 0,
            ]);

        $tester = new CommandTester(new UpdateCommand($service, $detector));
        $exit = $tester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exit);
    }
}
