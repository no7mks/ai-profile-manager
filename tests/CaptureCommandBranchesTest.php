<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Command\CaptureCommand;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CaptureCommandBranchesTest extends TestCase
{
    public function testUnknownPresetFails(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-cap-pre-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new CaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'no-such-preset-xyz', '--target' => ['cursor']]);

        chdir($old);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    public function testBaselineUnresolvedFails(): void
    {
        $composerHome = sys_get_temp_dir() . '/aipm-cap-bl-miss-' . bin2hex(random_bytes(4));
        mkdir($composerHome . '/vendor/composer', 0775, true);
        file_put_contents($composerHome . '/vendor/composer/installed.json', json_encode(['packages' => []]));

        $oldCh = getenv('COMPOSER_HOME');
        $oldBl = getenv('AIPM_BASELINE_ROOT');
        putenv('COMPOSER_HOME=' . $composerHome);
        putenv('AIPM_BASELINE_ROOT');

        $cmd = new CaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['preset' => 'gitflow', '--target' => ['cursor']]);

        if ($oldCh === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldCh);
        }
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Composer baseline', $tester->getDisplay());
    }

    public function testFullWorkspaceCaptureWithYesWritesEvent(): void
    {
        $baseline = sys_get_temp_dir() . '/aipm-cap-fw-bl-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/aipm-cap-fw-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/ws-cap/cursor', 0775, true);
        mkdir($ws . '/abilities/skills/ws-cap/cursor', 0775, true);
        file_put_contents($baseline . '/abilities/skills/ws-cap/cursor/SKILL.md', "b\n");
        file_put_contents($ws . '/abilities/skills/ws-cap/cursor/SKILL.md', "w\n");

        $home = sys_get_temp_dir() . '/aipm-cap-fw-h-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);

        $oldBl = getenv('AIPM_BASELINE_ROOT');
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_BASELINE_ROOT=' . $baseline);
        putenv('AIPM_HOME=' . $home);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ws);

        $cmd = new CaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            '--target' => ['cursor'],
            '--yes' => true,
            '--event-id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            '--source-repo' => 'acme/x',
            '--source-commit' => 'sha',
        ]);

        chdir($old);
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $oldHome);
        }

        self::assertSame(2, $exit);
        self::assertFileExists($home . '/events/cccccccc-cccc-4ccc-8ccc-cccccccccccc.json');
    }

    public function testFullWorkspaceCaptureDeclineConfirmSkipsPersistence(): void
    {
        $baseline = sys_get_temp_dir() . '/aipm-cap-fw2-bl-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/aipm-cap-fw2-ws-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities/skills/ws-cap2/cursor', 0775, true);
        mkdir($ws . '/abilities/skills/ws-cap2/cursor', 0775, true);
        file_put_contents($baseline . '/abilities/skills/ws-cap2/cursor/SKILL.md', "b\n");
        file_put_contents($ws . '/abilities/skills/ws-cap2/cursor/SKILL.md', "w\n");

        $home = sys_get_temp_dir() . '/aipm-cap-fw2-h-' . bin2hex(random_bytes(4));
        mkdir($home, 0775, true);

        $oldBl = getenv('AIPM_BASELINE_ROOT');
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_BASELINE_ROOT=' . $baseline);
        putenv('AIPM_HOME=' . $home);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ws);

        $cmd = new CaptureCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $tester->setInputs(['n']);
        $exit = $tester->execute([
            '--target' => ['cursor'],
            '--event-id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        ], ['interactive' => true]);

        chdir($old);
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $oldHome);
        }

        self::assertSame(Command::SUCCESS, $exit);
        self::assertFileDoesNotExist($home . '/events/dddddddd-dddd-4ddd-8ddd-dddddddddddd.json');
    }
}
