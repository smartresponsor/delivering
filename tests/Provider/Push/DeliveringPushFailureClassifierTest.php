<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

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

    public function testFailureLabelsDoNotContainProviderResponseBodies(): void
    {
        self::assertSame('FCM rejected the push request with HTTP 404 (UNREGISTERED).', DeliveringPushFailureClassifier::label('FCM', 404, 'UNREGISTERED'));
        self::assertSame('APNs rejected the push request with HTTP 503 (Shutdown).', DeliveringPushFailureClassifier::label('APNs', 503, 'Shutdown'));
        self::assertSame('FCM rejected the push request with HTTP 500.', DeliveringPushFailureClassifier::label('FCM', 500, null));
    }
}
