<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Handler\Command\Receipt;

use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\RecorderInterface\Receipt\DeliveryReceiptRecorderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliveryProcessReceiptHandler
{
    public function __construct(private DeliveryReceiptRecorderInterface $recorder)
    {
    }

    public function __invoke(DeliveryProcessReceipt $message): void
    {
        $this->recorder->record($message);
    }
}
