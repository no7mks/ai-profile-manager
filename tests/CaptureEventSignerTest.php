<?php

declare(strict_types=1);

namespace AiProfileManager\Tests;

use AiProfileManager\Capture\CaptureEventSigner;
use PHPUnit\Framework\TestCase;

final class CaptureEventSignerTest extends TestCase
{
    public function testSignAndVerifyRoundTrip(): void
    {
        $signer = new CaptureEventSigner();
        $body = '{"x":1}';
        $ts = (string) time();
        $secret = 'sekret';

        $sig = $signer->sign($body, $ts, $secret);

        self::assertTrue($signer->verify($body, $ts, $sig, $secret));
        self::assertFalse($signer->verify($body, $ts, $sig . 'x', $secret));
    }

    public function testVerifyRejectsNonDigitTimestamp(): void
    {
        $signer = new CaptureEventSigner();

        self::assertFalse($signer->verify('{}', 'abc', 'sha256=x', 's'));
    }

    public function testVerifyRejectsLargeClockSkew(): void
    {
        $signer = new CaptureEventSigner();
        $body = '{}';
        $farPast = (string) (time() - 99999);
        $sig = $signer->sign($body, $farPast, 's');

        self::assertFalse($signer->verify($body, $farPast, $sig, 's', 300));
    }
}
