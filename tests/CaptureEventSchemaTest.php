<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureEventSchema;
use PHPUnit\Framework\TestCase;

final class CaptureEventSchemaTest extends TestCase
{
    private function minimalValidV2(): array
    {
        return [
            'schema_version' => 2,
            'event_id' => '33333333-3333-4333-8333-333333333333',
            'source_repo' => 'org/repo',
            'source_commit' => 'sha',
            'base_ref' => 'v1',
            'captured_at' => gmdate(DATE_ATOM),
            'target' => 'cursor',
            'baseline' => [
                'package' => 'no7mks/ai-profile-manager',
                'version' => '1.0.0',
                'install_path' => '/tmp/vendor/no7mks/ai-profile-manager',
            ],
            'items' => [[
                'type' => 'skill',
                'name' => 'n',
                'status' => 'modified',
                'content_hash' => 'abc',
                'files' => [[
                    'path' => 'SKILL.md',
                    'content' => 'x',
                    'patch' => 'p',
                ]],
            ]],
        ];
    }

    public function testValidateAcceptsWellFormedV2(): void
    {
        $schema = new CaptureEventSchema();
        $r = $schema->validate($this->minimalValidV2());

        self::assertTrue($r['valid']);
        self::assertSame([], $r['errors']);
    }

    public function testValidateRejectsWrongSchemaVersion(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        $payload['schema_version'] = 1;
        $r = $schema->validate($payload);

        self::assertFalse($r['valid']);
        self::assertStringContainsString('schema_version', $r['errors'][0]);
    }

    public function testValidateRejectsMissingBaselineFields(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        unset($payload['baseline']['install_path']);
        $r = $schema->validate($payload);

        self::assertFalse($r['valid']);
        self::assertNotEmpty($r['errors']);
    }

    public function testValidateRejectsInvalidBaselineReferenceType(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        $payload['baseline']['reference'] = 123;
        $r = $schema->validate($payload);

        self::assertFalse($r['valid']);
        self::assertTrue(array_reduce($r['errors'], fn (bool $acc, string $e): bool => $acc || str_contains($e, 'baseline.reference'), false));
    }

    public function testValidateRejectsInvalidItem(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        $payload['items'][0]['files'] = [];
        $r = $schema->validate($payload);

        self::assertFalse($r['valid']);
        self::assertTrue(array_reduce($r['errors'], fn (bool $acc, string $e): bool => $acc || str_contains($e, 'files'), false));
    }

    public function testValidateRejectsInvalidFileDeletedType(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        $payload['items'][0]['files'][0]['deleted'] = 'yes';
        $r = $schema->validate($payload);

        self::assertFalse($r['valid']);
        self::assertTrue(array_reduce($r['errors'], fn (bool $acc, string $e): bool => $acc || str_contains($e, 'deleted'), false));
    }

    public function testValidateAcceptsDeletedBoolean(): void
    {
        $schema = new CaptureEventSchema();
        $payload = $this->minimalValidV2();
        $payload['items'][0]['files'][0]['deleted'] = true;
        $payload['items'][0]['files'][0]['content'] = '';

        $r = $schema->validate($payload);

        self::assertTrue($r['valid']);
    }
}
