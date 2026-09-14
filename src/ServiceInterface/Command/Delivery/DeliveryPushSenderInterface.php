<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Delivery;

interface DeliveryPushSenderInterface
{
    /** @param array<string, mixed> $payload */
    public function send(
        string $platform,
        string $token,
        string $appKey,
        string $title,
        string $body,
        ?string $actionUrl,
        array $payload,
        string $correlationId,
        string $idempotencyKey,
    ): string;
}
