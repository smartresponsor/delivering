<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Delivery;

interface DeliverySmsSenderInterface
{
    public function send(
        string $recipient,
        string $body,
        string $correlationId,
        string $idempotencyKey,
    ): string;
}
