<?php

declare(strict_types=1);

namespace App\Delivering\Event;

final readonly class DeliveryPushSubscriptionInvalidated
{
    public function __construct(
        public string $platform,
        public string $appKey,
        public string $tokenHash,
        public string $reasonCode,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if ('' === $tokenHash || '' === $reasonCode) {
            throw new \InvalidArgumentException('Push invalidation event requires tokenHash and reasonCode.');
        }
    }
}
