<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Message\Command\Delivery;

use App\Delivering\Message\Command\Delivery\DeliverySendPush;
use PHPUnit\Framework\TestCase;

final class DeliverySendPushTest extends TestCase
{
    public function testAsyncMessageContainsOnlyTokenHashReference(): void
    {
        $rawToken = 'device-token-secret';
        $tokenHash = hash('sha256', $rawToken);
        $message = new DeliverySendPush(
            platform: 'android',
            tokenHash: $tokenHash,
            appKey: 'one_tasker',
            title: 'Title',
            body: 'Body',
            actionUrl: null,
            payload: [],
            correlationId: 'correlation',
            idempotencyKey: 'idempotency',
        );

        self::assertSame('token-sha256:'.$tokenHash, $message->tokenReference());
        self::assertStringNotContainsString($rawToken, serialize($message));
        self::assertStringContainsString($tokenHash, serialize($message));
    }
}
