<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushProviderInterface;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushSenderInterface;

final readonly class DeliveryPushSenderRouter implements DeliveryPushSenderInterface
{
    /** @param iterable<DeliveryPushProviderInterface> $providers */
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

        throw new DeliveryPermanentTransportException(sprintf(
            'Push provider for platform "%s" is not configured.',
            $platform,
        ));
    }
}
