<?php

declare(strict_types=1);

namespace App\Delivering\Resolver\Delivery;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\ResolverInterface\Delivery\DeliveryPushTokenResolverInterface;

final class DeliveryUnavailablePushTokenResolver implements DeliveryPushTokenResolverInterface
{
    public function resolve(string $tokenHash, string $platform, string $appKey): string
    {
        throw new DeliveryPermanentTransportException('Push token resolver is not configured for this runtime.');
    }
}
