<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\MessageHandler\Command\Delivery;

use App\Delivering\Message\Command\Delivery\DeliverySendSms;
use App\Delivering\Service\Command\Delivery\DeliverySmsDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliverySendSmsHandler
{
    public function __construct(private DeliverySmsDeliveryService $deliveryService)
    {
    }

    public function __invoke(DeliverySendSms $message): void
    {
        $this->deliveryService->send($message);
    }
}
