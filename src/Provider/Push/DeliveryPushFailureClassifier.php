<?php

declare(strict_types=1);

namespace App\Delivering\Provider\Push;

use App\Delivering\Service\Transport\DeliveryRetryAfterParser;

final class DeliveryPushFailureClassifier
{
    /** @var list<string> */
    private const FCM_TRANSIENT_CODES = ['QUOTA_EXCEEDED', 'UNAVAILABLE', 'INTERNAL'];

    /** @var list<string> */
    private const APNS_TRANSIENT_REASONS = ['TooManyRequests', 'InternalServerError', 'ServiceUnavailable', 'Shutdown', 'IdleTimeout'];

    /** @var list<string> */
    private const FCM_INVALID_RECIPIENT_CODES = ['UNREGISTERED'];

    /** @var list<string> */
    private const APNS_INVALID_RECIPIENT_REASONS = ['BadDeviceToken', 'DeviceTokenNotForTopic', 'ExpiredToken', 'Unregistered'];

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

    public static function fcmRetryDelayMilliseconds(int $statusCode, ?string $errorCode, ?string $retryAfter, ?int $now = null): ?int
    {
        $retryAfterMilliseconds = DeliveryRetryAfterParser::milliseconds($retryAfter, $now);
        if (429 === $statusCode || 'QUOTA_EXCEEDED' === $errorCode) {
            return max(60_000, $retryAfterMilliseconds ?? 0);
        }

        return $retryAfterMilliseconds;
    }

    public static function apnsRetryDelayMilliseconds(?string $retryAfter, ?int $now = null): ?int
    {
        return DeliveryRetryAfterParser::milliseconds($retryAfter, $now);
    }

    public static function fcmInvalidatesRecipient(?string $errorCode): bool
    {
        return null !== $errorCode && in_array($errorCode, self::FCM_INVALID_RECIPIENT_CODES, true);
    }

    public static function apnsInvalidatesRecipient(?string $reason): bool
    {
        return null !== $reason && in_array($reason, self::APNS_INVALID_RECIPIENT_REASONS, true);
    }

    public static function label(string $provider, int $statusCode, ?string $code): string
    {
        return sprintf('%s rejected the push request with HTTP %d%s.', $provider, $statusCode, null === $code ? '' : ' ('.$code.')');
    }
}
