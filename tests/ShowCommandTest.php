<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\ShowCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowCommandTest extends TestCase
{
    /**
     * AC1: show 命令输出包含 Hooks 分区
     */
    public function testShowOutputContainsHooksSection(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-hooks-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-hooks-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/abilities/skills', 0775, true);
        mkdir($packageRoot . '/abilities/rules', 0775, true);
        mkdir($packageRoot . '/abilities/agents', 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/my-hook.kiro.hook', '{}');

        $registryPath = $packageRoot . '/abilities.yaml';
        file_put_contents($registryPath, implode("\n", [
            'hooks:',
            '  - path: my-hook',
            '    description: A test hook',
            '    targets:',
            '      kiro: .kiro/hooks/my-hook.kiro.hook',
        ]));

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($registryPath);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['kiro']]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('my-hook', $display);
    }

    /**
     * AC2: --type hook 仅输出 hook 分区
     */
    public function testTypeFilterHookShowsOnlyHooksSection(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-type-hook-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-type-hook-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/abilities/skills', 0775, true);
        mkdir($packageRoot . '/abilities/rules', 0775, true);
        mkdir($packageRoot . '/abilities/agents', 0775, true);
        mkdir($packageRoot . '/hooks', 0775, true);
        file_put_contents($packageRoot . '/hooks/my-hook.kiro.hook', '{}');

        $registryPath = $packageRoot . '/abilities.yaml';
        file_put_contents($registryPath, implode("\n", [
            'skills:',
            '  - path: some-skill',
            '    description: A skill',
            '    targets:',
            '      kiro: .kiro/skills/some-skill/',
            'hooks:',
            '  - path: my-hook',
            '    description: A test hook',
            '    targets:',
            '      kiro: .kiro/hooks/my-hook.kiro.hook',
        ]));

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $registry = new AbilityRegistry($registryPath);
        $cmd = new ShowCommand(new Installer(registry: $registry, packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['kiro'], '--type' => 'hook']);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('my-hook', $display);
        self::assertStringNotContainsString('Skills', $display);
        self::assertStringNotContainsString('Agents', $display);
        self::assertStringNotContainsString('Rules', $display);
    }

    /**
     * AC3: 未知类型返回错误并列出已知类型
     */
    public function testUnknownTypeFilterReturnsError(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-unknown-type-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-unknown-type-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/abilities/skills', 0775, true);
        mkdir($packageRoot . '/abilities/rules', 0775, true);
        mkdir($packageRoot . '/abilities/agents', 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new ShowCommand(new Installer(packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['kiro'], '--type' => 'unknown']);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Unknown type', $display);
        self::assertStringContainsString('rule', $display);
        self::assertStringContainsString('agent', $display);
        self::assertStringContainsString('skill', $display);
        self::assertStringContainsString('hook', $display);
        self::assertStringContainsString('gitignore', $display);
        self::assertStringContainsString('preset', $display);
    }

    /**
     * AC4: 无 hook 时显示空状态
     */
    public function testEmptyHooksSectionShowsNonePlaceholder(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-no-hooks-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-no-hooks-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/abilities/skills', 0775, true);
        mkdir($packageRoot . '/abilities/rules', 0775, true);
        mkdir($packageRoot . '/abilities/agents', 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new ShowCommand(new Installer(packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['kiro'], '--type' => 'hook']);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Hooks', $display);
        self::assertStringContainsString('(none)', $display);
    }

    /**
     * AC5: 无过滤时包含所有分区（含 Hooks）
     */
    public function testNoTypeFilterIncludesAllSections(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-show-all-' . bin2hex(random_bytes(4));
        $packageRoot = sys_get_temp_dir() . '/apm-show-all-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        mkdir($packageRoot . '/abilities/skills', 0775, true);
        mkdir($packageRoot . '/abilities/rules', 0775, true);
        mkdir($packageRoot . '/abilities/agents', 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new ShowCommand(new Installer(packageRoot: $packageRoot), new CheckService());
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['kiro']]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Skills', $display);
        self::assertStringContainsString('Agents', $display);
        self::assertStringContainsString('Rules', $display);
        self::assertStringContainsString('Hooks', $display);
    }
}
