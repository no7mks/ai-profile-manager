<?php

declare(strict_types=1);

namespace AiProfileManager\Tests\Command;

use AiProfileManager\Command\BootstrapCommand;
use AiProfileManager\Service\AbilityRegistry;
use AiProfileManager\Service\DirectoryMirrorService;
use AiProfileManager\Service\GitIgnoreTemplateService;
use AiProfileManager\Service\Installer;
use AiProfileManager\Service\ProjectInitializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use AiProfileManager\Tests\Support\RemovesDirTrait;

final class BootstrapCommandTest extends TestCase
{
    use RemovesDirTrait;

    private string $tmpDir;
    private string|false $oldCwd;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apm-bootstrap-cmd-' . bin2hex(random_bytes(4));
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

    public function testBootstrapCommandRejectsUnknownTarget(): void
    {
        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $cmd = new BootstrapCommand();
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['bad-target']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Unknown targets', $tester->getDisplay());
    }

    public function testBootstrapInstallsScaffoldOnly(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        // Empty abilities.yaml (no bootstrap.includes)
        file_put_contents($pkg . '/abilities.yaml', "skills: []\nrules: []\nagents: []\nhooks: []\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );
        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Installing scaffold', $display);
        self::assertStringContainsString('Scaffold installed', $display);
        self::assertStringContainsString('Bootstrap complete', $display);
        self::assertStringNotContainsString('Installing default preset', $display);
        self::assertStringNotContainsString('Installed skill', $display);

        self::assertFileExists($proj . '/docs/README.md');
        self::assertFileExists($proj . '/issues/README.md');
        self::assertFileExists($proj . '/AGENTS.md');
    }

    public function testBootstrapBlockedWhenScaffoldExists(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj . '/docs', 0775, true);
        file_put_contents($proj . '/docs/README.md', "existing\n");
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $cmd = new BootstrapCommand($initializer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--force', $tester->getDisplay());
    }

    // --- AC 1, 2, 9: scaffold 先执行，然后 ability 安装成功 → exit 0 ---

    public function testBootstrapScaffoldThenAbilityInstallSuccessExitsZero(): void
    {
        $pkg = $this->createPackageWithAbility('skill', 'my-skill');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        // Scaffold happened first
        self::assertStringContainsString('Scaffold installed', $display);
        // Ability install output present
        self::assertStringContainsString('Installed skill my-skill', $display);
    }

    // --- AC 8: 空 includes 仅 scaffold → exit 0 ---

    public function testBootstrapEmptyIncludesOnlyScaffoldExitsZero(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");
        // abilities.yaml without bootstrap section
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills: []',
            'rules: []',
            'agents: []',
            'hooks: []',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Scaffold installed', $display);
        self::assertStringContainsString('Bootstrap complete', $display);
    }

    // --- AC 3: 已安装 + no --force → [skip] ---

    public function testBootstrapSkipsExistingAbilityWithoutForce(): void
    {
        $pkg = $this->createPackageWithAbility('rule', 'my-rule');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        // Pre-install the ability on disk before running bootstrap
        mkdir($proj . '/.cursor/rules/my-rule', 0775, true);
        file_put_contents($proj . '/.cursor/rules/my-rule/my-rule.mdc', "# pre-installed\n");
        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        // No --force → skipExisting = true → already installed ability is skipped
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[skip]', $tester->getDisplay());
    }

    // --- AC 4: 已安装 + --force → 覆盖 ---

    public function testBootstrapOverwritesExistingAbilityWithForce(): void
    {
        $pkg = $this->createPackageWithAbility('skill', 'overwrite-skill');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        // Pre-install the ability on disk
        mkdir($proj . '/.cursor/skills/overwrite-skill', 0775, true);
        file_put_contents($proj . '/.cursor/skills/overwrite-skill/SKILL.md', "# old content\n");

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        // --force → skipExisting = false → ability is overwritten
        $exit = $tester->execute(['--target' => ['cursor'], '--force' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        self::assertStringNotContainsString('[skip] skill:overwrite-skill', $display);
        self::assertStringContainsString('Installed skill overwrite-skill', $display);
    }

    // --- AC 5, 6: 安装失败 → [fail] + 继续剩余 → exit 1 ---

    public function testBootstrapFailedAbilityOutputsFailAndContinuesExitsOne(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        // Create abilities.yaml with two entries: one broken (no source file), one valid
        // good-rule: source file exists
        mkdir($pkg . '/.cursor/rules/good-rule', 0775, true);
        file_put_contents($pkg . '/.cursor/rules/good-rule/good-rule.mdc', "# Good\n");

        // bad-skill: source dir does NOT exist (will fail)

        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: bad-skill',
            '    description: broken skill',
            '    targets:',
            '      cursor: .cursor/skills/bad-skill',
            'rules:',
            '  - path: good-rule',
            '    description: good rule',
            '    targets:',
            '      cursor: .cursor/rules/good-rule/good-rule.mdc',
            'agents: []',
            'hooks: []',
            'bootstrap:',
            '  includes:',
            '    - skill:bad-skill',
            '    - rule:good-rule',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        $display = $tester->getDisplay();
        // [fail] marker for failed item
        self::assertStringContainsString('[fail]', $display);
        self::assertStringContainsString('skill:bad-skill', $display);
        // Continued: second item installed successfully
        self::assertStringContainsString('Installed rule good-rule', $display);
    }

    // --- AC 7: scaffold 保留（不回滚）---

    public function testBootstrapPreservesScaffoldOnAbilityFailure(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        // ability with no source → will fail
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills:',
            '  - path: fail-skill',
            '    description: fail skill',
            '    targets:',
            '      cursor: .cursor/skills/fail-skill',
            'rules: []',
            'agents: []',
            'hooks: []',
            'bootstrap:',
            '  includes:',
            '    - skill:fail-skill',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        // Scaffold still exists (not rolled back)
        self::assertFileExists($proj . '/docs/README.md');
        self::assertFileExists($proj . '/AGENTS.md');
    }

    // --- validateBootstrapIncludes 失败 → exit FAILURE ---

    public function testBootstrapValidateFailsExitsFailure(): void
    {
        $pkg = $this->tmpDir . '/pkg';
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        // bootstrap.includes references a skill that doesn't exist in registry
        file_put_contents($pkg . '/abilities.yaml', implode("\n", [
            'skills: []',
            'rules: []',
            'agents: []',
            'hooks: []',
            'bootstrap:',
            '  includes:',
            '    - skill:nonexistent',
        ]) . "\n");

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        $exit = $tester->execute(['--target' => ['cursor']]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('nonexistent', $tester->getDisplay());
    }

    // --- AC 2: 默认 targets 为 ['cursor', 'kiro'] ---

    public function testBootstrapDefaultTargetsAreCursorAndKiro(): void
    {
        $pkg = $this->createPackageWithAbilityMultiTarget('skill', 'dual-skill');

        $proj = $this->tmpDir . '/proj';
        mkdir($proj, 0775, true);
        chdir($proj);

        $initializer = new ProjectInitializer($pkg);
        $registry = new AbilityRegistry($pkg . '/abilities.yaml');
        $installer = new Installer(
            registry: $registry,
            gitIgnore: new GitIgnoreTemplateService(),
            packageRoot: $pkg,
            mirror: new DirectoryMirrorService(),
        );

        $cmd = new BootstrapCommand($initializer, $registry, $installer);
        $tester = new CommandTester($cmd);
        // No --target option → use defaults (cursor, kiro)
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        // Both targets should be served
        self::assertStringContainsString('cursor', $display);
        self::assertStringContainsString('kiro', $display);
    }

    // --- Helpers ---

    /**
     * Create a package directory with scaffold, a single ability, and bootstrap.includes referencing it.
     */
    private function createPackageWithAbility(string $type, string $name): string
    {
        $pkg = $this->tmpDir . '/pkg-' . $name;
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        $section = $type . 's'; // skill → skills

        // For skills, target is a directory; for rules/agents, target is a file
        if ($type === 'skill') {
            $targetPath = '.cursor/skills/' . $name;
            mkdir($pkg . '/' . $targetPath, 0775, true);
            file_put_contents($pkg . '/' . $targetPath . '/SKILL.md', "# $name content\n");
        } elseif ($type === 'rule') {
            $targetPath = '.cursor/rules/' . $name . '/' . $name . '.mdc';
            mkdir($pkg . '/.cursor/rules/' . $name, 0775, true);
            file_put_contents($pkg . '/' . $targetPath, "# $name content\n");
        } elseif ($type === 'agent') {
            $targetPath = '.cursor/agents/' . $name . '.md';
            mkdir($pkg . '/.cursor/agents', 0775, true);
            file_put_contents($pkg . '/' . $targetPath, "# $name content\n");
        } else {
            $targetPath = '.cursor/' . $type . 's/' . $name;
            mkdir($pkg . '/' . $targetPath, 0775, true);
            file_put_contents($pkg . '/' . $targetPath . '/content.md', "# $name content\n");
        }

        // Create abilities.yaml
        $yaml = implode("\n", [
            "$section:",
            "  - path: $name",
            "    description: $name ability",
            "    targets:",
            "      cursor: $targetPath",
            ...($section !== 'skills' ? ["skills: []"] : []),
            ...($section !== 'rules' ? ["rules: []"] : []),
            ...($section !== 'agents' ? ["agents: []"] : []),
            "hooks: []",
            "bootstrap:",
            "  includes:",
            "    - $type:$name",
        ]) . "\n";
        file_put_contents($pkg . '/abilities.yaml', $yaml);

        return $pkg;
    }

    /**
     * Create a package with a skill that has both cursor and kiro targets.
     */
    private function createPackageWithAbilityMultiTarget(string $type, string $name): string
    {
        $pkg = $this->tmpDir . '/pkg-' . $name;
        mkdir($pkg . '/docs', 0775, true);
        file_put_contents($pkg . '/docs/README.md', "# Docs\n");
        mkdir($pkg . '/issues', 0775, true);
        file_put_contents($pkg . '/issues/README.md', "# Issues\n");
        file_put_contents($pkg . '/AGENTS.md', "# Agents\n");

        $cursorDir = '.cursor/skills/' . $name;
        $kiroDir = '.kiro/skills/' . $name;

        mkdir($pkg . '/' . $cursorDir, 0775, true);
        file_put_contents($pkg . '/' . $cursorDir . '/SKILL.md', "# $name cursor\n");
        mkdir($pkg . '/' . $kiroDir, 0775, true);
        file_put_contents($pkg . '/' . $kiroDir . '/SKILL.md', "# $name kiro\n");

        $yaml = implode("\n", [
            'skills:',
            "  - path: $name",
            "    description: $name ability",
            '    targets:',
            "      cursor: $cursorDir",
            "      kiro: $kiroDir",
            'rules: []',
            'agents: []',
            'hooks: []',
            'bootstrap:',
            '  includes:',
            "    - $type:$name",
        ]) . "\n";
        file_put_contents($pkg . '/abilities.yaml', $yaml);

        return $pkg;
    }
}
