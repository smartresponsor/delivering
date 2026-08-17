<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Message;

use InvalidArgumentException;

final readonly class DeliveringSendPush
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $platform,
        public string $token,
        public string $appKey,
        public string $title,
        public string $body,
        public ?string $actionUrl,
        public array $payload,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if (!in_array($platform, ['ios', 'android'], true)) {
            throw new InvalidArgumentException('Push platform must be ios or android.');
        }
        if ('' === trim($token)) {
            throw new InvalidArgumentException('Push token cannot be empty.');
        }
        if ('' === trim($appKey)) {
            throw new InvalidArgumentException('Push appKey cannot be empty.');
        }
        if ('' === trim($title)) {
            throw new InvalidArgumentException('Push title cannot be empty.');
        }
        if ('' === trim($correlationId)) {
            throw new InvalidArgumentException('Correlation ID cannot be empty.');
        }
        if ('' === trim($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency key cannot be empty.');
        }
    }

    public function provider(): string
    {
        return 'ios' === $this->platform ? 'apns' : 'fcm';
    }
}
