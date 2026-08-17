<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Query\Push;

use App\Delivering\Service\Query\Push\DeliveringPushReadinessService;
use PHPUnit\Framework\TestCase;

final class DeliveringPushReadinessServiceTest extends TestCase
{
    public function testMissingConfigurationFailsClosed(): void
    {
        $status = (new DeliveringPushReadinessService('', '', '', '{}', 'production', '', '{}'))->status();

        self::assertFalse($status['configured']);
        self::assertFalse($status['apns']['configured']);
        self::assertFalse($status['fcm']['configured']);
        self::assertSame([], $status['apns']['appKeys']);
        self::assertSame([], $status['fcm']['appKeys']);
        self::assertNotEmpty($status['apns']['issues']);
        self::assertNotEmpty($status['fcm']['issues']);
    }

    public function testCompleteConfigurationReportsOnlyApplicationKeys(): void
    {
        $serviceAccount = json_encode([
            'client_email' => 'firebase-admin@example.test',
            'private_key' => '-----BEGIN PRIVATE KEY-----test-----END PRIVATE KEY-----',
        ], JSON_THROW_ON_ERROR);

        $status = (new DeliveringPushReadinessService(
            'TEAM123',
            'KEY123',
            '-----BEGIN PRIVATE KEY-----test-----END PRIVATE KEY-----',
            '{"one-tasker":"com.smartresponsor.mobile.onetasker"}',
            'production',
            $serviceAccount,
            '{"one-tasker":"smartresponsor-onetasker"}',
        ))->status();

        self::assertTrue($status['configured']);
        self::assertTrue($status['apns']['configured']);
        self::assertTrue($status['fcm']['configured']);
        self::assertSame(['one-tasker'], $status['apns']['appKeys']);
        self::assertSame(['one-tasker'], $status['fcm']['appKeys']);
        self::assertSame([], $status['apns']['issues']);
        self::assertSame([], $status['fcm']['issues']);
        self::assertStringNotContainsString('TEAM123', json_encode($status, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('firebase-admin@example.test', json_encode($status, JSON_THROW_ON_ERROR));
    }
}
