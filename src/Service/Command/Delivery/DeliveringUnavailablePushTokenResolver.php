<?php

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushTokenResolverInterface;

final class DeliveringUnavailablePushTokenResolver implements DeliveringPushTokenResolverInterface
{
    public function resolve(string $tokenHash, string $platform, string $appKey): string
    {
        throw new DeliveringPermanentTransportException('Push token resolver is not configured for this runtime.');
    }
}
