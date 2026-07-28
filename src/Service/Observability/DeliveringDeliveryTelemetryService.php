<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Observability;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Exception\DeliveringTransportException;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class DeliveringDeliveryTelemetryService
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function duplicate(string $correlationId, string $idempotencyKey): void
    {
        $this->logger->info('delivering.delivery.duplicate', $this->context(
            correlationId: $correlationId,
            idempotencyKey: $idempotencyKey,
            outcome: 'duplicate',
        ));
    }

    public function succeeded(
        string $correlationId,
        string $idempotencyKey,
        string $providerMessageId,
        float $latencyMilliseconds,
    ): void {
        $context = $this->context(
            correlationId: $correlationId,
            idempotencyKey: $idempotencyKey,
            outcome: 'succeeded',
            latencyMilliseconds: $latencyMilliseconds,
        );
        $context['provider_message_id'] = $providerMessageId;

        $this->logger->info('delivering.delivery.succeeded', $context);
    }

    public function failed(
        string $correlationId,
        string $idempotencyKey,
        Throwable $exception,
        float $latencyMilliseconds,
    ): void {
        $classification = match (true) {
            $exception instanceof DeliveringPermanentTransportException => 'permanent',
            $exception instanceof DeliveringTransportException => 'transient',
            default => 'unknown',
        };
        $context = $this->context(
            correlationId: $correlationId,
            idempotencyKey: $idempotencyKey,
            outcome: 'failed',
            latencyMilliseconds: $latencyMilliseconds,
        );
        $context['failure_classification'] = $classification;
        $context['exception_class'] = $exception::class;
        $context['exception_message'] = $exception->getMessage();

        $this->logger->error('delivering.delivery.failed', $context);
    }

    /** @return array<string, bool|float|int|string> */
    private function context(
        string $correlationId,
        string $idempotencyKey,
        string $outcome,
        ?float $latencyMilliseconds = null,
    ): array {
        $context = [
            'component' => 'delivering',
            'channel' => 'sms',
            'provider' => 'telnyx',
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'outcome' => $outcome,
            'attempt' => 1,
        ];

        if (null !== $latencyMilliseconds) {
            $context['latency_ms'] = round($latencyMilliseconds, 3);
        }

        return $context;
    }
}
