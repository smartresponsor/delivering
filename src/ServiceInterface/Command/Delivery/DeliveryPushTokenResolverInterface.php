<?php

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Delivery;

interface DeliveryPushTokenResolverInterface
{
    public function resolve(string $tokenHash, string $platform, string $appKey): string;
}
