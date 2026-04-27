<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

final class CaptureEventSigner
{
    public function sign(string $body, string $timestamp, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . "\n" . $body, $secret);
    }

    public function verify(
        string $body,
        string $timestamp,
        string $signature,
        string $secret,
        int $maxSkewSeconds = 300
    ): bool {
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $timeSkew = abs(time() - (int) $timestamp);
        if ($timeSkew > $maxSkewSeconds) {
            return false;
        }

        return hash_equals($this->sign($body, $timestamp, $secret), $signature);
    }
}
