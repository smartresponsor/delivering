<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Provider\Push\DeliveryApnsPushProvider;
use App\Delivering\Provider\Push\DeliveryFcmPushProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class DeliveryPushProviderConfigurationTest extends TestCase
{
    public function testApnsFailsClosedWithoutCredentials(): void
    {
        $provider = new DeliveryApnsPushProvider(new MockHttpClient(), '', '', '', '{}');
        $this->expectException(DeliveryPermanentTransportException::class);
        $provider->send('token', 'one_tasker', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmFailsClosedWithoutProjectMapping(): void
    {
        $provider = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', '{}');
        $this->expectException(DeliveryPermanentTransportException::class);
        $provider->send('token', 'one_tasker', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testProvidersSupportOnlyTheirPlatform(): void
    {
        $apns = new DeliveryApnsPushProvider(new MockHttpClient(), '', '', '', '{}');
        $fcm = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', '{}');
        self::assertTrue($apns->supports('ios'));
        self::assertFalse($apns->supports('android'));
        self::assertTrue($fcm->supports('android'));
        self::assertFalse($fcm->supports('ios'));
    }
}
