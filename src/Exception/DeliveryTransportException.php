<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Exception;

use RuntimeException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;

final class DeliveryTransportException extends RuntimeException implements RecoverableExceptionInterface
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?int $retryDelay = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryDelay(): ?int
    {
        return $this->retryDelay;
    }

    public function forceRetry(): bool
    {
        return false;
    }
}
