<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\GitIgnoreTemplateService;
use PHPUnit\Framework\TestCase;

final class GitIgnoreTemplateServiceTest extends TestCase
{
    public function testRenderManagedBlockMatchesAbilityAndTarget(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-gitignore-tpl-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $template = $tmp . '/template.gitignore';
        file_put_contents($template, implode("\n", [
            '## @aipm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @aipm:end',
            '',
            '## @aipm:block ability=gitflow target=*',
            '/.aipm/gitflow/',
            '## @aipm:end',
            '',
            '## @aipm:block ability=rule:git-conventions target=kiro',
            '/.aipm/rules-cache/',
            '## @aipm:end',
        ]));

        $svc = new GitIgnoreTemplateService();
        $rendered = $svc->renderManagedBlock($template, ['skill:graphify', 'gitflow'], ['cursor']);

        self::assertStringContainsString('/.cache/graphify/', $rendered);
        self::assertStringContainsString('/.aipm/gitflow/', $rendered);
        self::assertStringNotContainsString('/.aipm/rules-cache/', $rendered);
    }

    public function testMergeManagedSectionReplacesPreviousManagedBlock(): void
    {
        $tmp = sys_get_temp_dir() . '/aipm-gitignore-merge-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $gitignore = $tmp . '/.gitignore';

        file_put_contents($gitignore, "/vendor/\n");
        $svc = new GitIgnoreTemplateService();
        $svc->mergeManagedSection($gitignore, "/.cache/one/\n/.cache/two/");
        $svc->mergeManagedSection($gitignore, "/.cache/new/");

        $content = (string) file_get_contents($gitignore);
        self::assertStringContainsString('/vendor/', $content);
        self::assertStringContainsString('/.cache/new/', $content);
        self::assertStringNotContainsString('/.cache/one/', $content);
        self::assertSame(1, substr_count($content, '# BEGIN aipm-managed-gitignore v1'));
    }
}
