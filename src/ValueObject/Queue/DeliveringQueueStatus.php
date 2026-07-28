<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\ValueObject\Queue;

/**
 * Immutable snapshot of Delivering Messenger queue depth.
 */
final readonly class DeliveringQueueStatus
{
    public function __construct(
        public int $queued,
        public int $failed,
        public \DateTimeImmutable $observedAt,
    ) {
        if ($queued < 0 || $failed < 0) {
            throw new \InvalidArgumentException('Queue counts cannot be negative.');
        }
    }

    public function isHealthy(): bool
    {
        return 0 === $this->failed;
    }
}
