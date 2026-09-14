<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Provider\Push\DeliveryApnsPushProvider;
use App\Delivering\Provider\Push\DeliveryFcmPushProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class DeliveryPushProviderValidationTest extends TestCase
{
    public function testPlatformSupportIsProviderSpecific(): void
    {
        $client = new MockHttpClient();
        $apns = new DeliveryApnsPushProvider($client, '', '', '', '{}');
        $fcm = new DeliveryFcmPushProvider($client, '{}', '{}');

        self::assertTrue($apns->supports('ios'));
        self::assertFalse($apns->supports('android'));
        self::assertTrue($fcm->supports('android'));
        self::assertFalse($fcm->supports('ios'));
    }

    public function testApnsRejectsMissingCredentialsBeforeNetworkCall(): void
    {
        $provider = new DeliveryApnsPushProvider(new MockHttpClient(), '', 'key', 'private', '{"app":"topic"}');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('APNs credentials are not configured.');
        $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testApnsRejectsMissingTopicBeforeNetworkCall(): void
    {
        $provider = new DeliveryApnsPushProvider(new MockHttpClient(), 'team', 'key', 'private', '{}');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('APNs topic is not configured');
        $provider->send('token', 'missing', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmRejectsMalformedProjectMapBeforeNetworkCall(): void
    {
        $provider = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', 'not-json');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('FCM project map must be valid JSON object.');
        $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmRejectsMissingProjectBeforeNetworkCall(): void
    {
        $provider = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', '{}');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('FCM project is not configured');
        $provider->send('token', 'missing', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmRejectsIncompleteServiceAccountBeforeNetworkCall(): void
    {
        $provider = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', '{"app":"project"}');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('FCM service account JSON is incomplete.');
        $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testApnsRejectsMalformedTopicMap(): void
    {
        $provider = new DeliveryApnsPushProvider(new MockHttpClient(), 'team', 'key', 'private', 'not-json');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('APNs topic map must be valid JSON object.');
        $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testApnsRejectsInvalidEnvironmentBeforeSigning(): void
    {
        $provider = new DeliveryApnsPushProvider(
            new MockHttpClient(),
            'team',
            'key',
            'private',
            '{"app":"topic"}',
            'staging',
        );

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('APNs environment must be development or production.');
        $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testFcmNormalizesPayloadBeforeServiceAccountValidation(): void
    {
        $provider = new DeliveryFcmPushProvider(new MockHttpClient(), '{}', '{"app":"project"}');

        $this->expectException(DeliveryPermanentTransportException::class);
        $this->expectExceptionMessage('FCM service account JSON is incomplete.');
        $provider->send(
            'token',
            'app',
            'Title',
            'Body',
            'https://example.test/action',
            ['string' => 'value', 'number' => 42, 'nested' => ['a' => 1], 'null' => null],
            'corr',
            'idem',
        );
    }
}
