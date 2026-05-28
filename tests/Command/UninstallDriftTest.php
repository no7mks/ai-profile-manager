<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\AgentUninstallCommand;
use AiProfileManager\Command\RuleUninstallCommand;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class UninstallDriftTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;
    private string|false $oldBaseline;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-uninstall-drift-' . bin2hex(random_bytes(4));
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

    public function testAgentUninstallBlocksWhenModifiedWithoutForce(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/.cursor/agents', 0775, true);
        mkdir($workspace . '/.cursor/agents', 0775, true);
        file_put_contents($baseline . '/.cursor/agents/reviewer.md', "base\n");
        file_put_contents($workspace . '/.cursor/agents/reviewer.md', "modified\n");

        // abilities.yaml at baseline for AbilityDiffService
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'agents:',
            '  - path: reviewer',
            '    description: reviewer',
            '    targets:',
            '      cursor: .cursor/agents/reviewer.md',
        ]) . "\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $cmd = new AgentUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['reviewer'], '--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Re-run with --force', $tester->getDisplay());
        self::assertFileExists($workspace . '/.cursor/agents/reviewer.md');
    }

    public function testRuleUninstallBlocksWhenModifiedWithoutForce(): void
    {
        $baseline = $this->tmpDir . '/baseline';
        $workspace = $this->tmpDir . '/workspace';
        mkdir($baseline . '/.cursor/rules/git', 0775, true);
        mkdir($workspace . '/.cursor/rules/git', 0775, true);
        file_put_contents($baseline . '/.cursor/rules/git/demo.mdc', "base\n");
        file_put_contents($workspace . '/.cursor/rules/git/demo.mdc', "modified\n");

        // abilities.yaml at baseline for AbilityDiffService
        file_put_contents($baseline . '/abilities.yaml', implode("\n", [
            'rules:',
            '  - path: demo',
            '    description: demo',
            '    targets:',
            '      cursor: .cursor/rules/git/demo.mdc',
        ]) . "\n");

        putenv('APM_BASELINE_ROOT=' . $baseline);
        chdir($workspace);

        $cmd = new RuleUninstallCommand(new Installer(), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['demo'], '--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Re-run with --force', $tester->getDisplay());
        self::assertFileExists($workspace . '/.cursor/rules/git/demo.mdc');
    }

}
