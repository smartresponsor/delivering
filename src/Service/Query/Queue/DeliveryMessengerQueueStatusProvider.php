<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Query\Queue;

use App\Delivering\ServiceInterface\Query\Queue\DeliveryQueueStatusProviderInterface;
use App\Delivering\ValueObject\Queue\DeliveryQueueStatus;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * Reads queue depth from the configured Messenger transports.
 */
final readonly class DeliveryMessengerQueueStatusProvider implements DeliveryQueueStatusProviderInterface
{
    public function __construct(
        private MessageCountAwareInterface $asyncReceiver,
        private MessageCountAwareInterface $failedReceiver,
    ) {
    }

    public function status(): DeliveryQueueStatus
    {
        return new DeliveryQueueStatus(
            queued: $this->asyncReceiver->getMessageCount(),
            failed: $this->failedReceiver->getMessageCount(),
            observedAt: new \DateTimeImmutable(),
        );
    }
}
