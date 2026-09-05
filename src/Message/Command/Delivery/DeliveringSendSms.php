<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Message\Command\Delivery;

use InvalidArgumentException;

final readonly class DeliveringSendSms
{
    public function __construct(
        public string $recipient,
        public string $body,
        public string $correlationId,
        public string $idempotencyKey,
    ) {
        if (1 !== preg_match('/^\+[1-9]\d{7,14}$/', $recipient)) {
            throw new InvalidArgumentException('SMS recipient must use E.164 format.');
        }

        if ('' === trim($body)) {
            throw new InvalidArgumentException('SMS body cannot be empty.');
        }

        if ('' === trim($correlationId)) {
            throw new InvalidArgumentException('Correlation ID cannot be empty.');
        }

        if ('' === trim($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency key cannot be empty.');
        }
    }
}
