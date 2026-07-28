<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Exception;

use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

final class DeliveringPermanentTransportException extends RuntimeException implements UnrecoverableExceptionInterface
{
}
