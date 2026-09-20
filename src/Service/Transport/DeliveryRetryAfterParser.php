<?php

declare(strict_types=1);

namespace App\Delivering\Service\Transport;

final class DeliveryRetryAfterParser
{
    public static function milliseconds(?string $retryAfter, ?int $now = null): ?int
    {
        if (null === $retryAfter || '' === trim($retryAfter)) {
            return null;
        }

        $retryAfter = trim($retryAfter);
        if (ctype_digit($retryAfter)) {
            return max(0, (int) $retryAfter) * 1000;
        }

        $timestamp = strtotime($retryAfter);
        if (false === $timestamp) {
            return null;
        }

        return max(0, $timestamp - ($now ?? time())) * 1000;
    }
}
