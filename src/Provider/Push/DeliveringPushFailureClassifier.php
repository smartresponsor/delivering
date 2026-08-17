<?php

declare(strict_types=1);

namespace App\Delivering\Provider\Push;

final class DeliveringPushFailureClassifier
{
    /** @var list<string> */
    private const FCM_TRANSIENT_CODES = ['QUOTA_EXCEEDED', 'UNAVAILABLE', 'INTERNAL'];

    /** @var list<string> */
    private const APNS_TRANSIENT_REASONS = ['TooManyRequests', 'InternalServerError', 'ServiceUnavailable', 'Shutdown', 'IdleTimeout'];

    public static function fcmIsTransient(int $statusCode, ?string $errorCode): bool
    {
        if ($statusCode >= 500 || in_array($statusCode, [408, 429], true)) {
            return true;
        }

        return null !== $errorCode && in_array($errorCode, self::FCM_TRANSIENT_CODES, true);
    }

    public static function apnsIsTransient(int $statusCode, ?string $reason): bool
    {
        if ($statusCode >= 500 || in_array($statusCode, [408, 429], true)) {
            return true;
        }

        return null !== $reason && in_array($reason, self::APNS_TRANSIENT_REASONS, true);
    }

    public static function label(string $provider, int $statusCode, ?string $code): string
    {
        return sprintf('%s rejected the push request with HTTP %d%s.', $provider, $statusCode, null === $code ? '' : ' ('.$code.')');
    }
}
