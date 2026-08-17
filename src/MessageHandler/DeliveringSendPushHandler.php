<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\MessageHandler;

use App\Delivering\Message\DeliveringSendPush;
use App\Delivering\Service\Command\Delivery\DeliveringPushDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliveringSendPushHandler
{
    public function __construct(private DeliveringPushDeliveryService $deliveryService)
    {
    }

    public function __invoke(DeliveringSendPush $message): void
    {
        $this->deliveryService->send($message);
    }
}
