<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Service\CaptureService;
use AiProfileManager\Service\CheckService;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PresetManifestCommandsTest extends TestCase
{
    /**
     * Baseline and workspace start from the same manifest; adding a skill produces a manifest diff vs baseline.
     *
     * @return array{ws: string, baseline: string, oldBl: string|false, oldCh: string|false}
     */
    private function workspaceMatchingBaseline(): array
    {
        $baseline = sys_get_temp_dir() . '/aipm-pmb-' . bin2hex(random_bytes(4));
        $ws = sys_get_temp_dir() . '/aipm-pmw-' . bin2hex(random_bytes(4));
        mkdir($baseline . '/abilities', 0775, true);
        mkdir($ws . '/abilities', 0775, true);

        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($baseline . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);
        file_put_contents($ws . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $oldBl = getenv('AIPM_BASELINE_ROOT');
        $oldCh = getenv('COMPOSER_HOME');
        putenv('AIPM_BASELINE_ROOT=' . $baseline);
        putenv('COMPOSER_HOME');

        return ['ws' => $ws, 'baseline' => $baseline, 'oldBl' => $oldBl, 'oldCh' => $oldCh];
    }

    private function restoreEnv(string|false $oldBl, string|false $oldCh): void
    {
        if ($oldBl === false) {
            putenv('AIPM_BASELINE_ROOT');
        } else {
            putenv('AIPM_BASELINE_ROOT=' . $oldBl);
        }
        if ($oldCh === false) {
            putenv('COMPOSER_HOME');
        } else {
            putenv('COMPOSER_HOME=' . $oldCh);
        }
    }

    public function testPresetAddAbilityWritesEventWhenManifestDiffersFromBaseline(): void
    {
        $ctx = $this->workspaceMatchingBaseline();
        $tmpAipm = sys_get_temp_dir() . '/aipm-pevt-' . bin2hex(random_bytes(4));
        mkdir($tmpAipm, 0775, true);
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $tmpAipm);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ctx['ws']);

        $cmd = new PresetAddAbilityCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'gitflow',
            'ability' => 'new-skill-for-test',
            '--skill' => true,
            '--event-id' => '55555555-5555-4555-8555-555555555555',
        ]);

        chdir($old);
        putenv('AIPM_HOME=' . ($oldHome !== false ? $oldHome : ''));
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        }
        $this->restoreEnv($ctx['oldBl'], $ctx['oldCh']);

        self::assertSame(2, $exit);
        self::assertStringContainsString('Event written to events dir', $tester->getDisplay());
        self::assertFileExists($tmpAipm . '/events/55555555-5555-4555-8555-555555555555.json');
    }

    public function testPresetAddAbilityNoOpWhenAbilityAlreadyPresent(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-pnop-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetAddAbilityCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'gitflow',
            'ability' => 'gitflow',
            '--skill' => true,
        ]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('already in preset', $tester->getDisplay());
    }

    public function testPresetRemoveAbilityWritesEventWhenManifestChanges(): void
    {
        $ctx = $this->workspaceMatchingBaseline();
        $tmpAipm = sys_get_temp_dir() . '/aipm-prm-' . bin2hex(random_bytes(4));
        mkdir($tmpAipm, 0775, true);
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $tmpAipm);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ctx['ws']);

        $cmd = new PresetRemoveAbilityCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'gitflow',
            'ability' => 'flow-starter',
            '--agent' => true,
            '--event-id' => '66666666-6666-4666-8666-666666666666',
        ]);

        chdir($old);
        putenv('AIPM_HOME=' . ($oldHome !== false ? $oldHome : ''));
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        }
        $this->restoreEnv($ctx['oldBl'], $ctx['oldCh']);

        self::assertSame(2, $exit);
        self::assertFileExists($tmpAipm . '/events/66666666-6666-4666-8666-666666666666.json');
    }

    public function testPresetCreateWritesEventForNewPresetName(): void
    {
        $ctx = $this->workspaceMatchingBaseline();
        $tmpAipm = sys_get_temp_dir() . '/aipm-pcr-' . bin2hex(random_bytes(4));
        mkdir($tmpAipm, 0775, true);
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $tmpAipm);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ctx['ws']);

        $cmd = new PresetCreateCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'name' => 'fresh-preset-' . bin2hex(random_bytes(2)),
            '--skill' => ['snap'],
            '--event-id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        ]);

        chdir($old);
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $oldHome);
        }
        $this->restoreEnv($ctx['oldBl'], $ctx['oldCh']);

        self::assertSame(2, $exit);
        self::assertFileExists($tmpAipm . '/events/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa.json');
    }

    public function testPresetDeleteWritesEventWhenRemovingPresetFromManifest(): void
    {
        $ctx = $this->workspaceMatchingBaseline();
        $tmpAipm = sys_get_temp_dir() . '/aipm-pdel2-' . bin2hex(random_bytes(4));
        mkdir($tmpAipm, 0775, true);
        $oldHome = getenv('AIPM_HOME');
        putenv('AIPM_HOME=' . $tmpAipm);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($ctx['ws']);

        $cmd = new PresetDeleteCommand(new CaptureService(new CheckService()));
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'name' => 'kiro-spec',
            '--event-id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ]);

        chdir($old);
        if ($oldHome === false) {
            putenv('AIPM_HOME');
        } else {
            putenv('AIPM_HOME=' . $oldHome);
        }
        $this->restoreEnv($ctx['oldBl'], $ctx['oldCh']);

        self::assertSame(2, $exit);
        self::assertFileExists($tmpAipm . '/events/bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb.json');
    }
}
