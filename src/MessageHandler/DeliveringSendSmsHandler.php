<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\MessageHandler;

use App\Delivering\Message\DeliveringSendSms;
use App\Delivering\Service\Command\Delivery\DeliveringSmsDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliveringSendSmsHandler
{
    public function __construct(private DeliveringSmsDeliveryService $deliveryService)
    {
    }

    public function __invoke(DeliveringSendSms $message): void
    {
        $this->deliveryService->send($message);
    }
}
