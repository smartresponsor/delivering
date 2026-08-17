<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveringTransportException;
use App\Delivering\Provider\Push\DeliveringPushFailureClassifier;
use PHPUnit\Framework\TestCase;

final class DeliveringPushFailureClassifierTest extends TestCase
{
    public function testFcmTransientFailuresAreRetryable(): void
    {
        self::assertTrue(DeliveringPushFailureClassifier::fcmIsTransient(429, 'QUOTA_EXCEEDED'));
        self::assertTrue(DeliveringPushFailureClassifier::fcmIsTransient(503, 'UNAVAILABLE'));
        self::assertTrue(DeliveringPushFailureClassifier::fcmIsTransient(500, 'INTERNAL'));
        self::assertFalse(DeliveringPushFailureClassifier::fcmIsTransient(404, 'UNREGISTERED'));
        self::assertFalse(DeliveringPushFailureClassifier::fcmIsTransient(403, 'SENDER_ID_MISMATCH'));
    }

    public function testApnsTransientFailuresAreRetryable(): void
    {
        self::assertTrue(DeliveringPushFailureClassifier::apnsIsTransient(429, 'TooManyRequests'));
        self::assertTrue(DeliveringPushFailureClassifier::apnsIsTransient(503, 'ServiceUnavailable'));
        self::assertTrue(DeliveringPushFailureClassifier::apnsIsTransient(503, 'Shutdown'));
        self::assertFalse(DeliveringPushFailureClassifier::apnsIsTransient(410, 'Unregistered'));
        self::assertFalse(DeliveringPushFailureClassifier::apnsIsTransient(400, 'BadDeviceToken'));
        self::assertFalse(DeliveringPushFailureClassifier::apnsIsTransient(400, 'DeviceTokenNotForTopic'));
    }

    public function testOnlyInvalidDeviceTokensInvalidateRecipients(): void
    {
        self::assertTrue(DeliveringPushFailureClassifier::fcmInvalidatesRecipient('UNREGISTERED'));
        self::assertFalse(DeliveringPushFailureClassifier::fcmInvalidatesRecipient('SENDER_ID_MISMATCH'));
        self::assertFalse(DeliveringPushFailureClassifier::fcmInvalidatesRecipient('PERMISSION_DENIED'));
        self::assertTrue(DeliveringPushFailureClassifier::apnsInvalidatesRecipient('Unregistered'));
        self::assertTrue(DeliveringPushFailureClassifier::apnsInvalidatesRecipient('BadDeviceToken'));
        self::assertTrue(DeliveringPushFailureClassifier::apnsInvalidatesRecipient('DeviceTokenNotForTopic'));
        self::assertFalse(DeliveringPushFailureClassifier::apnsInvalidatesRecipient('Forbidden'));
    }

    public function testRetryAfterAndQuotaBackoffAreConvertedForMessenger(): void
    {
        self::assertSame(60_000, DeliveringPushFailureClassifier::fcmRetryDelayMilliseconds(429, 'QUOTA_EXCEEDED', null));
        self::assertSame(120_000, DeliveringPushFailureClassifier::fcmRetryDelayMilliseconds(429, 'QUOTA_EXCEEDED', '120'));
        self::assertSame(15_000, DeliveringPushFailureClassifier::fcmRetryDelayMilliseconds(503, 'UNAVAILABLE', '15'));
        self::assertNull(DeliveringPushFailureClassifier::fcmRetryDelayMilliseconds(503, 'UNAVAILABLE', null));
        self::assertSame(30_000, DeliveringPushFailureClassifier::apnsRetryDelayMilliseconds('30'));
        self::assertSame(
            90_000,
            DeliveringPushFailureClassifier::fcmRetryDelayMilliseconds(
                503,
                'UNAVAILABLE',
                'Mon, 17 Aug 2026 19:31:30 GMT',
                1_786_995_000,
            ),
        );
        self::assertNull(DeliveringPushFailureClassifier::apnsRetryDelayMilliseconds('not-a-date'));
    }

    public function testRecoverableTransportExceptionExposesMessengerRetryDelay(): void
    {
        $exception = new DeliveringTransportException('retry later', retryDelay: 60_000);

        self::assertSame(60_000, $exception->getRetryDelay());
        self::assertFalse($exception->forceRetry());
    }

    public function testFailureLabelsDoNotContainProviderResponseBodies(): void
    {
        self::assertSame('FCM rejected the push request with HTTP 404 (UNREGISTERED).', DeliveringPushFailureClassifier::label('FCM', 404, 'UNREGISTERED'));
        self::assertSame('APNs rejected the push request with HTTP 503 (Shutdown).', DeliveringPushFailureClassifier::label('APNs', 503, 'Shutdown'));
        self::assertSame('FCM rejected the push request with HTTP 500.', DeliveringPushFailureClassifier::label('FCM', 500, null));
    }
}
