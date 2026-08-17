<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Provider\Push\DeliveringApnsPushProvider;
use App\Delivering\Provider\Push\DeliveringFcmPushProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class DeliveringPushProviderConfigurationTest extends TestCase
{
    public function testApnsFailsClosedWithoutCredentials(): void
    {
        $provider = new DeliveringApnsPushProvider(new MockHttpClient(), '', '', '', '{}');
        $this->expectException(DeliveringPermanentTransportException::class);
        $provider->send('token', 'one-tasker', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmFailsClosedWithoutProjectMapping(): void
    {
        $provider = new DeliveringFcmPushProvider(new MockHttpClient(), '{}', '{}');
        $this->expectException(DeliveringPermanentTransportException::class);
        $provider->send('token', 'one-tasker', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testProvidersSupportOnlyTheirPlatform(): void
    {
        $apns = new DeliveringApnsPushProvider(new MockHttpClient(), '', '', '', '{}');
        $fcm = new DeliveringFcmPushProvider(new MockHttpClient(), '{}', '{}');
        self::assertTrue($apns->supports('ios'));
        self::assertFalse($apns->supports('android'));
        self::assertTrue($fcm->supports('android'));
        self::assertFalse($fcm->supports('ios'));
    }
}
