<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Provider\Push\DeliveryApnsPushProvider;
use App\Delivering\Provider\Push\DeliveryFcmPushProvider;
use App\Delivering\Tests\Support\DeliveryTestKeyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    public function testApnsSuccessfulSendUsesSandboxAndProviderId(): void
    {
        $response = new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['apns-id: apns-message-1'],
        ]);
        $provider = new DeliveryApnsPushProvider(
            new MockHttpClient($response),
            'TEAM',
            'KEY',
            DeliveryTestKeyFactory::ecPrivateKey(),
            '{"app":"com.example.app"}',
            'development',
        );

        $messageId = $provider->send(
            'device/token',
            'app',
            'Title',
            'Body',
            'https://example.test/action',
            ['custom' => 'value'],
            'corr',
            'idem',
        );

        self::assertSame('apns-message-1', $messageId);
        self::assertStringContainsString('https://api.sandbox.push.apple.com/3/device/device%2Ftoken', $response->getRequestUrl());
        $options = $response->getRequestOptions();
        self::assertSame('apns-topic: com.example.app', $options['normalized_headers']['apns-topic'][0]);
    }

    public function testApnsPermanentAndTransientFailuresAreNormalized(): void
    {
        $permanent = new DeliveryApnsPushProvider(
            new MockHttpClient(new MockResponse('{"reason":"Unregistered"}', ['http_code' => 410])),
            'TEAM',
            'KEY',
            DeliveryTestKeyFactory::ecPrivateKey(),
            '{"app":"com.example.app"}',
        );
        try {
            $permanent->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
            self::fail('Expected permanent APNs failure.');
        } catch (DeliveryPermanentTransportException $exception) {
            self::assertSame('Unregistered', $exception->reasonCode);
            self::assertTrue($exception->recipientInvalid);
        }

        $transient = new DeliveryApnsPushProvider(
            new MockHttpClient(new MockResponse('{"reason":"ServiceUnavailable"}', [
                'http_code' => 503,
                'response_headers' => ['retry-after: 2'],
            ])),
            'TEAM',
            'KEY',
            DeliveryTestKeyFactory::ecPrivateKey(),
            '{"app":"com.example.app"}',
        );
        try {
            $transient->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
            self::fail('Expected transient APNs failure.');
        } catch (\App\Delivering\Exception\DeliveryTransportException $exception) {
            self::assertSame(2000, $exception->getRetryDelay());
        }
    }

    public function testFcmSuccessfulSendObtainsAndCachesOauthToken(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return match (count($requests)) {
                1 => new MockResponse('{"access_token":"oauth-token","expires_in":3600}', ['http_code' => 200]),
                2 => new MockResponse('{"name":"projects/project/messages/message-1"}', ['http_code' => 200]),
                3 => new MockResponse('{"name":"projects/project/messages/message-2"}', ['http_code' => 200]),
                default => throw new \LogicException('Unexpected extra FCM HTTP call.'),
            };
        });
        $provider = new DeliveryFcmPushProvider($client, $this->serviceAccountJson(), '{"app":"project"}');

        self::assertSame(
            'projects/project/messages/message-1',
            $provider->send('token-1', 'app', 'Title', 'Body', 'https://example.test/action', ['nested' => ['a' => 1]], 'corr-1', 'idem-1'),
        );
        self::assertSame(
            'projects/project/messages/message-2',
            $provider->send('token-2', 'app', 'Title', 'Body', null, ['number' => 42], 'corr-2', 'idem-2'),
        );

        self::assertCount(3, $requests);
        self::assertSame('https://oauth2.googleapis.com/token', $requests[0][1]);
        self::assertSame('https://fcm.googleapis.com/v1/projects/project/messages:send', $requests[1][1]);
        self::assertSame('https://fcm.googleapis.com/v1/projects/project/messages:send', $requests[2][1]);
    }

    public function testFcmPermanentAndTransientFailuresAreNormalized(): void
    {
        $permanent = new DeliveryFcmPushProvider(
            new MockHttpClient([
                new MockResponse('{"access_token":"oauth-token","expires_in":3600}', ['http_code' => 200]),
                new MockResponse('{"error":{"status":"UNREGISTERED"}}', ['http_code' => 404]),
            ]),
            $this->serviceAccountJson(),
            '{"app":"project"}',
        );
        try {
            $permanent->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
            self::fail('Expected permanent FCM failure.');
        } catch (DeliveryPermanentTransportException $exception) {
            self::assertSame('UNREGISTERED', $exception->reasonCode);
            self::assertTrue($exception->recipientInvalid);
        }

        $transient = new DeliveryFcmPushProvider(
            new MockHttpClient([
                new MockResponse('{"access_token":"oauth-token","expires_in":3600}', ['http_code' => 200]),
                new MockResponse('{"error":{"status":"UNAVAILABLE"}}', [
                    'http_code' => 503,
                    'response_headers' => ['retry-after: 3'],
                ]),
            ]),
            $this->serviceAccountJson(),
            '{"app":"project"}',
        );
        try {
            $transient->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
            self::fail('Expected transient FCM failure.');
        } catch (\App\Delivering\Exception\DeliveryTransportException $exception) {
            self::assertSame(3000, $exception->getRetryDelay());
        }
    }

    public function testFcmOauthFailuresAreNormalized(): void
    {
        foreach ([
            new MockResponse('{"error":"unavailable"}', ['http_code' => 503]),
            new MockResponse('{"token_type":"Bearer"}', ['http_code' => 200]),
        ] as $response) {
            $provider = new DeliveryFcmPushProvider(
                new MockHttpClient($response),
                $this->serviceAccountJson(),
                '{"app":"project"}',
            );

            try {
                $provider->send('token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
                self::fail('Expected FCM OAuth failure.');
            } catch (\App\Delivering\Exception\DeliveryTransportException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function serviceAccountJson(): string
    {
        return json_encode([
            'client_email' => 'firebase@example.test',
            'private_key' => DeliveryTestKeyFactory::rsaPrivateKey(),

            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR);
    }

}
