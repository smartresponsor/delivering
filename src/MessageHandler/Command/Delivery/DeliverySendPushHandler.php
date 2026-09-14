<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\MessageHandler\Command\Delivery;

use App\Delivering\Message\Command\Delivery\DeliverySendPush;
use App\Delivering\Service\Command\Delivery\DeliveryPushDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliverySendPushHandler
{
    public function __construct(private DeliveryPushDeliveryService $deliveryService)
    {
    }

    public function __invoke(DeliverySendPush $message): void
    {
        $this->deliveryService->send($message);
    }
}
