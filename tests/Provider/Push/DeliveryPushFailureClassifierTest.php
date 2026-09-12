<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveryTransportException;
use App\Delivering\Provider\Push\DeliveryPushFailureClassifier;
use PHPUnit\Framework\TestCase;

final class DeliveryPushFailureClassifierTest extends TestCase
{
    public function testFcmTransientFailuresAreRetryable(): void
    {
        self::assertTrue(DeliveryPushFailureClassifier::fcmIsTransient(429, 'QUOTA_EXCEEDED'));
        self::assertTrue(DeliveryPushFailureClassifier::fcmIsTransient(503, 'UNAVAILABLE'));
        self::assertTrue(DeliveryPushFailureClassifier::fcmIsTransient(500, 'INTERNAL'));
        self::assertFalse(DeliveryPushFailureClassifier::fcmIsTransient(404, 'UNREGISTERED'));
        self::assertFalse(DeliveryPushFailureClassifier::fcmIsTransient(403, 'SENDER_ID_MISMATCH'));
    }

    public function testApnsTransientFailuresAreRetryable(): void
    {
        self::assertTrue(DeliveryPushFailureClassifier::apnsIsTransient(429, 'TooManyRequests'));
        self::assertTrue(DeliveryPushFailureClassifier::apnsIsTransient(503, 'ServiceUnavailable'));
        self::assertTrue(DeliveryPushFailureClassifier::apnsIsTransient(503, 'Shutdown'));
        self::assertFalse(DeliveryPushFailureClassifier::apnsIsTransient(410, 'Unregistered'));
        self::assertFalse(DeliveryPushFailureClassifier::apnsIsTransient(400, 'BadDeviceToken'));
        self::assertFalse(DeliveryPushFailureClassifier::apnsIsTransient(400, 'DeviceTokenNotForTopic'));
    }

    public function testOnlyInvalidDeviceTokensInvalidateRecipients(): void
    {
        self::assertTrue(DeliveryPushFailureClassifier::fcmInvalidatesRecipient('UNREGISTERED'));
        self::assertFalse(DeliveryPushFailureClassifier::fcmInvalidatesRecipient('SENDER_ID_MISMATCH'));
        self::assertFalse(DeliveryPushFailureClassifier::fcmInvalidatesRecipient('PERMISSION_DENIED'));
        self::assertTrue(DeliveryPushFailureClassifier::apnsInvalidatesRecipient('Unregistered'));
        self::assertTrue(DeliveryPushFailureClassifier::apnsInvalidatesRecipient('BadDeviceToken'));
        self::assertTrue(DeliveryPushFailureClassifier::apnsInvalidatesRecipient('DeviceTokenNotForTopic'));
        self::assertFalse(DeliveryPushFailureClassifier::apnsInvalidatesRecipient('Forbidden'));
    }

    public function testRetryAfterAndQuotaBackoffAreConvertedForMessenger(): void
    {
        self::assertSame(60_000, DeliveryPushFailureClassifier::fcmRetryDelayMilliseconds(429, 'QUOTA_EXCEEDED', null));
        self::assertSame(120_000, DeliveryPushFailureClassifier::fcmRetryDelayMilliseconds(429, 'QUOTA_EXCEEDED', '120'));
        self::assertSame(15_000, DeliveryPushFailureClassifier::fcmRetryDelayMilliseconds(503, 'UNAVAILABLE', '15'));
        self::assertNull(DeliveryPushFailureClassifier::fcmRetryDelayMilliseconds(503, 'UNAVAILABLE', null));
        self::assertSame(30_000, DeliveryPushFailureClassifier::apnsRetryDelayMilliseconds('30'));
        self::assertSame(
            90_000,
            DeliveryPushFailureClassifier::fcmRetryDelayMilliseconds(
                503,
                'UNAVAILABLE',
                'Mon, 17 Aug 2026 19:31:30 GMT',
                1_786_995_000,
            ),
        );
        self::assertNull(DeliveryPushFailureClassifier::apnsRetryDelayMilliseconds('not-a-date'));
    }

    public function testRecoverableTransportExceptionExposesMessengerRetryDelay(): void
    {
        $exception = new DeliveryTransportException('retry later', retryDelay: 60_000);

        self::assertSame(60_000, $exception->getRetryDelay());
        self::assertFalse($exception->forceRetry());
    }

    public function testFailureLabelsDoNotContainProviderResponseBodies(): void
    {
        self::assertSame('FCM rejected the push request with HTTP 404 (UNREGISTERED).', DeliveryPushFailureClassifier::label('FCM', 404, 'UNREGISTERED'));
        self::assertSame('APNs rejected the push request with HTTP 503 (Shutdown).', DeliveryPushFailureClassifier::label('APNs', 503, 'Shutdown'));
        self::assertSame('FCM rejected the push request with HTTP 500.', DeliveryPushFailureClassifier::label('FCM', 500, null));
    }
}
