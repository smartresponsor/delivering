<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Enum;

enum DeliveryDeliveryStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case SendingFailed = 'sending_failed';
    case DeliveryFailed = 'delivery_failed';
    case DeliveryUnconfirmed = 'delivery_unconfirmed';
    case Unknown = 'unknown';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Read, self::SendingFailed, self::DeliveryFailed, self::DeliveryUnconfirmed => true,
            default => false,
        };
    }
}
