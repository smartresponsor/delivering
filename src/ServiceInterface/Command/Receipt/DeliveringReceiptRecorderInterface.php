<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Receipt;

use App\Delivering\Message\Command\Receipt\DeliveringProcessReceipt;

interface DeliveringReceiptRecorderInterface
{
    public function record(DeliveringProcessReceipt $receipt): void;
}
