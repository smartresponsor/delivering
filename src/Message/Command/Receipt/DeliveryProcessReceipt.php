<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Message\Command\Receipt;

use App\Delivering\Enum\DeliveryDeliveryStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DeliveryProcessReceipt
{
    public function __construct(
        public string $eventId,
        public string $providerMessageId,
        public DeliveryDeliveryStatus $status,
        public DateTimeImmutable $occurredAt,
        public ?string $errorCode,
        public ?string $errorDetail,
    ) {
        if ('' === trim($eventId)) {
            throw new InvalidArgumentException('Receipt event ID cannot be empty.');
        }

        if ('' === trim($providerMessageId)) {
            throw new InvalidArgumentException('Provider message ID cannot be empty.');
        }
    }
}
