<?php

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushTokenResolverInterface;

final class DeliveryUnavailablePushTokenResolver implements DeliveryPushTokenResolverInterface
{
    public function resolve(string $tokenHash, string $platform, string $appKey): string
    {
        throw new DeliveryPermanentTransportException('Push token resolver is not configured for this runtime.');
    }
}
