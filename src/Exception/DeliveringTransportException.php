<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Exception;

use RuntimeException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;

final class DeliveringTransportException extends RuntimeException implements RecoverableExceptionInterface
{
    public function getRetryDelay(): ?int
    {
        return null;
    }

    public function forceRetry(): bool
    {
        return false;
    }
}
