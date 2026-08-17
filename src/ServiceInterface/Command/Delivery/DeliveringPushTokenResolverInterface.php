<?php

declare(strict_types=1);

namespace App\Delivering\ServiceInterface\Command\Delivery;

interface DeliveringPushTokenResolverInterface
{
    public function resolve(string $tokenHash, string $platform, string $appKey): string;
}
