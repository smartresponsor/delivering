<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Query\Queue;

use App\Delivering\ValueObject\Queue\DeliveryQueueStatus;

/**
 * Provides an operational snapshot of Delivering queues.
 */
interface DeliveryQueueStatusProviderInterface
{
    public function status(): DeliveryQueueStatus;
}
