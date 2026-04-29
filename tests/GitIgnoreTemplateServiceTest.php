<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Service\GitIgnoreTemplateService;
use PHPUnit\Framework\TestCase;

final class GitIgnoreTemplateServiceTest extends TestCase
{
    public function testRenderManagedBlockMatchesAbilityAndTarget(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-gitignore-tpl-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $template = $tmp . '/template.gitignore';
        file_put_contents($template, implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
            '## @apm:end',
            '',
            '## @apm:block ability=gitflow target=*',
            '/.apm/gitflow/',
            '## @apm:end',
            '',
            '## @apm:block ability=rule:git-conventions target=kiro',
            '/.apm/rules-cache/',
            '## @apm:end',
        ]));

        $svc = new GitIgnoreTemplateService();
        $rendered = $svc->renderManagedBlock($template, ['skill:graphify', 'gitflow'], ['cursor']);

        self::assertStringContainsString('/.cache/graphify/', $rendered);
        self::assertStringContainsString('/.apm/gitflow/', $rendered);
        self::assertStringNotContainsString('/.apm/rules-cache/', $rendered);
    }

    public function testMergeManagedSectionReplacesPreviousManagedBlock(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-gitignore-merge-' . bin2hex(random_bytes(4));
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
        self::assertSame(1, substr_count($content, '# BEGIN apm-managed-gitignore v1'));
    }

    public function testRenderManagedBlockReturnsEmptyWhenTemplateMissing(): void
    {
        $svc = new GitIgnoreTemplateService();
        $rendered = $svc->renderManagedBlock('/tmp/apm-no-template-' . bin2hex(random_bytes(4)), ['skill:graphify'], ['cursor']);

        self::assertSame('', $rendered);
    }

    public function testRenderManagedBlockThrowsWhenBlockIsUnclosed(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-gitignore-bad-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $template = $tmp . '/template.gitignore';
        file_put_contents($template, implode("\n", [
            '## @apm:block ability=skill:graphify target=cursor',
            '/.cache/graphify/',
        ]));

        $svc = new GitIgnoreTemplateService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unclosed apm block');
        $svc->renderManagedBlock($template, ['skill:graphify'], ['cursor']);
    }

    public function testMergeManagedSectionNoopWhenManagedBodyEmpty(): void
    {
        $tmp = sys_get_temp_dir() . '/apm-gitignore-empty-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        $gitignore = $tmp . '/.gitignore';
        file_put_contents($gitignore, "/vendor/\n");

        $svc = new GitIgnoreTemplateService();
        $svc->mergeManagedSection($gitignore, "  \n");

        self::assertSame("/vendor/\n", (string) file_get_contents($gitignore));
    }
}
