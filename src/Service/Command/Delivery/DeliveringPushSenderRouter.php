<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushProviderInterface;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushSenderInterface;

final readonly class DeliveringPushSenderRouter implements DeliveringPushSenderInterface
{
    /** @param iterable<DeliveringPushProviderInterface> $providers */
    public function __construct(private iterable $providers)
    {
    }

    public function send(
        string $platform,
        string $token,
        string $appKey,
        string $title,
        string $body,
        ?string $actionUrl,
        array $payload,
        string $correlationId,
        string $idempotencyKey,
    ): string {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($platform)) {
                continue;
            }

            return $provider->send(
                $token,
                $appKey,
                $title,
                $body,
                $actionUrl,
                $payload,
                $correlationId,
                $idempotencyKey,
            );
        }

        throw new DeliveringPermanentTransportException(sprintf(
            'Push provider for platform "%s" is not configured.',
            $platform,
        ));
    }
}
