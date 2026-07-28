<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\MessageHandler;

use App\Delivering\Message\DeliveringProcessReceipt;
use App\Delivering\ServiceInterface\Command\Receipt\DeliveringReceiptRecorderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeliveringProcessReceiptHandler
{
    public function __construct(private DeliveringReceiptRecorderInterface $recorder)
    {
    }

    public function __invoke(DeliveringProcessReceipt $message): void
    {
        $this->recorder->record($message);
    }
}
