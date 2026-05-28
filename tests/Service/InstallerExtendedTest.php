<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Service;

use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\HookChecker;
use AiProfileManager\Service\HookInstaller;
use AiProfileManager\Service\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerExtendedTest extends TestCase
{
    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-inst-ext-' . bin2hex(random_bytes(4));
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

    public function testUninstallHookKiroWithDriftBlocksWithoutForce(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/my-hook.kiro.hook', '{"version":"1"}');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj . '/.kiro/hooks', 0775, true);
        file_put_contents($proj . '/.kiro/hooks/my-hook.kiro.hook', '{"version":"2-drifted"}');
        chdir($proj);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro'], false);

        self::assertSame(1, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('drift', $output);
        self::assertStringContainsString('--force', $output);
        self::assertFileExists($proj . '/.kiro/hooks/my-hook.kiro.hook');
    }

    public function testUninstallHookKiroMissingReportsSkip(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/hooks', 0775, true);
        file_put_contents($pkg . '/hooks/my-hook.kiro.hook', '{"version":"1"}');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['my-hook'],
        ], ['kiro'], false);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip]', $output);
    }

    public function testUninstallHookCursorMissingReportsSkip(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            hookInstaller: new HookInstaller(),
            hookChecker: new HookChecker(),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => [],
            'agents' => [],
            'hooks' => ['nonexistent'],
        ], ['cursor'], false);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('[skip]', $output);
    }

    public function testInstallRuleResolvesFromTargetsOnCursor(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        // Source file at the targets path relative to package root
        mkdir($pkg . '/.cursor/rules/git', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/git/demo.mdc', "rule content\n");

        // Create abilities.yaml with targets mapping
        $yaml = <<<YAML
version: "1"
rules:
  - path: "git:demo"
    description: "test rule"
    targets:
      cursor: .cursor/rules/git/demo.mdc
skills: []
agents: []
hooks: []
YAML;
        file_put_contents($pkg . '/abilities.yaml', $yaml);

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            registry: new AbilityRegistry($pkg . '/abilities.yaml'),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => ['git:demo'],
            'agents' => [],
        ], ['cursor']);

        self::assertSame(0, $result['exit_code']);
        self::assertFileExists($proj . '/.cursor/rules/git/demo.mdc');
        self::assertSame("rule content\n", file_get_contents($proj . '/.cursor/rules/git/demo.mdc'));
    }

    public function testInstallRuleResolvesFromTargetsOnKiro(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        // Source file at the targets path relative to package root
        mkdir($pkg . '/.kiro/steering/spec', 0775, true);
        file_put_contents($pkg . '/.kiro/steering/spec/demo.md', "steering content\n");

        // Create abilities.yaml with targets mapping
        $yaml = <<<YAML
version: "1"
rules:
  - path: "spec:demo"
    description: "test steering"
    targets:
      kiro: .kiro/steering/spec/demo.md
skills: []
agents: []
hooks: []
YAML;
        file_put_contents($pkg . '/abilities.yaml', $yaml);

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            registry: new AbilityRegistry($pkg . '/abilities.yaml'),
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->installTyped([
            'skills' => [],
            'rules' => ['spec:demo'],
            'agents' => [],
        ], ['kiro']);

        self::assertSame(0, $result['exit_code']);
        self::assertFileExists($proj . '/.kiro/steering/spec/demo.md');
        self::assertSame("steering content\n", file_get_contents($proj . '/.kiro/steering/spec/demo.md'));
    }

    public function testIsInstalledOnTargetReturnsFalseWhenRuleDirMissing(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        // No .cursor/rules directory exists
        self::assertFalse($installer->isInstalledOnTarget('rule', 'demo', 'cursor'));
    }

    public function testUninstallRuleOnKiroReportsCorrectLabel(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);
        $proj = $this->tmpDir . '/proj';
        mkdir($proj . '/.kiro/steering/spec', 0775, true);
        file_put_contents($proj . '/.kiro/steering/spec/demo.md', "x\n");
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => ['demo'],
            'agents' => [],
        ], ['kiro']);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Uninstalled steering demo from kiro', $output);
    }

    public function testUninstallRuleOnKiroMissReportsCorrectLabel(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg, 0775, true);
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $installer = new Installer(
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $result = $installer->uninstallTyped([
            'skills' => [],
            'rules' => ['nonexistent'],
            'agents' => [],
        ], ['kiro']);

        self::assertSame(0, $result['exit_code']);
        $output = implode("\n", $result['lines']);
        self::assertStringContainsString('Steering nonexistent not found on kiro', $output);
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
