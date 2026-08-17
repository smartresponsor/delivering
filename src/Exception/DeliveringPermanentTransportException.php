<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Exception;

use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

final class DeliveringPermanentTransportException extends RuntimeException implements UnrecoverableExceptionInterface
{
    public function __construct(
        string $message,
        public readonly ?string $reasonCode = null,
        public readonly bool $recipientInvalid = false,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
