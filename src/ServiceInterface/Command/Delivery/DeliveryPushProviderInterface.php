<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Delivery;

interface DeliveryPushProviderInterface
{
    public function supports(string $platform): bool;

    /** @param array<string, mixed> $payload */
    public function send(
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
