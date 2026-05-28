<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\AgentInstallCommand;
use AiProfileManager\Command\RuleInstallCommand;
use AiProfileManager\Command\SkillInstallCommand;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TypedInstallCommandsTest extends TestCase
{
    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-typed-inst-' . bin2hex(random_bytes(4));
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

    public function testAgentInstallRejectsUnknownTarget(): void
    {
        $cmd = new AgentInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testRuleInstallRejectsUnknownTarget(): void
    {
        $cmd = new RuleInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testSkillInstallRejectsUnknownTarget(): void
    {
        $cmd = new SkillInstallCommand(new Installer());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['x'], '--target' => ['bad']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testAgentInstallSucceedsWithValidAgent(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.cursor/agents', 0775, true);
        file_put_contents($pkg . '/.cursor/agents/demo.md', "agent\n");

        // Create abilities.yaml with the agent entry
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'agents:',
            '  - path: demo',
            '    description: demo agent',
            '    targets:',
            '      cursor: .cursor/agents/demo.md',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new AgentInstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['agents' => ['demo'], '--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Installed agent demo', $tester->getDisplay());
    }

    public function testRuleInstallSucceedsWithValidRule(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.kiro/steering', 0775, true);
        file_put_contents($pkg . '/.kiro/steering/demo.md', "rule\n");

        // Create abilities.yaml with the rule entry
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'rules:',
            '  - path: demo',
            '    description: demo rule',
            '    targets:',
            '      kiro: .kiro/steering/demo.md',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new RuleInstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['rules' => ['demo'], '--target' => ['kiro']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Installed steering demo', $tester->getDisplay());
    }

    public function testSkillInstallSucceedsWithValidSkill(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/.kiro/skills/demo', 0775, true);
        file_put_contents($pkg . '/.kiro/skills/demo/SKILL.md', "skill\n");

        // Create abilities.yaml with the skill entry
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: demo',
            '    description: demo skill',
            '    targets:',
            '      kiro: .kiro/skills/demo',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $registry = new \AiProfileManager\Service\AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new SkillInstallCommand($installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['skills' => ['demo'], '--target' => ['kiro']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Installed skill demo', $tester->getDisplay());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
