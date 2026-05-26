<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Config\AppConfig;
use AiProfileManager\Command\PresetAddAbilityCommand;
use AiProfileManager\Command\PresetCreateCommand;
use AiProfileManager\Command\PresetDeleteCommand;
use AiProfileManager\Command\PresetRemoveAbilityCommand;
use AiProfileManager\Service\PresetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PresetManifestCommandsTest extends TestCase
{
    public function testPresetAddAbilitySavesNewAbilityToManifest(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pmb-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetAddAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'gitflow',
            'ability' => 'new-skill-for-test',
            '--skill' => true,
        ]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[ok]', $tester->getDisplay());

        $saved = json_decode((string) file_get_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH), true);
        self::assertContains('new-skill-for-test', $saved['gitflow']['skills']);
    }

    public function testPresetAddAbilityNoOpWhenAbilityAlreadyPresent(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pnop-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetAddAbilityCommand();
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

    public function testPresetRemoveAbilityRemovesFromManifest(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-prm-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetRemoveAbilityCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'preset' => 'gitflow',
            'ability' => 'gitflow',
            '--skill' => true,
        ]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[ok]', $tester->getDisplay());

        $saved = json_decode((string) file_get_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH), true);
        self::assertNotContains('gitflow', $saved['gitflow']['skills']);
    }

    public function testPresetCreateSavesNewPreset(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pcr-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $presetName = 'fresh-preset-' . bin2hex(random_bytes(2));
        $cmd = new PresetCreateCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'name' => $presetName,
            '--skill' => ['snap'],
        ]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[ok]', $tester->getDisplay());

        $saved = json_decode((string) file_get_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH), true);
        self::assertArrayHasKey($presetName, $saved);
        self::assertContains('snap', $saved[$presetName]['skills']);
    }

    public function testPresetDeleteRemovesPresetFromManifest(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-pdel2-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/abilities', 0775, true);
        $json = json_encode(AppConfig::PRESET_ITEMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH, $json);

        $old = getcwd();
        self::assertNotFalse($old);
        chdir($tmp);

        $cmd = new PresetDeleteCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute([
            'name' => 'kiro-spec',
        ]);

        chdir($old);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[ok]', $tester->getDisplay());

        $saved = json_decode((string) file_get_contents($tmp . '/' . PresetRegistry::PRESETS_RELATIVE_PATH), true);
        self::assertArrayNotHasKey('kiro-spec', $saved);
    }
}
