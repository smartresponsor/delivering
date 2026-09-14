<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Push;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Provider\Push\DeliveryApnsPushProvider;
use App\Delivering\Provider\Push\DeliveryFcmPushProvider;
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
            $this->ecPrivateKey(),
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
            $this->ecPrivateKey(),
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
            $this->ecPrivateKey(),
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
            'private_key' => <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCmpinQ9ZC7Fz1O
RLzJXl2h5YZkouohI6AbRb0ocHHTPW2MyQzPCEktWsSg7D7Ni4vh9tfCNxmYBY/Z
R3hv8gbrZ7c9kAHa07w95SLvrkHY3wVgagjDnNoSdis+X6gLbACB/u227Bjak1ts
B02CbF4aHWZ5pNh9R3TBACrmfPhIn20qRkTLfIUJSgK7/8XpQZQ5mHg7ztcWPzzu
URdLojuT9DokQ5wnIQAgZQD8erxo5oCPg4Ete4GK2ev6ILT9kIE10gx7zBIhzotp
r0RI+PkxQZIJxedQWpiY8EeJjtq6u/yIpSkssttsJcv1ErdxJCmfETHN+PbRrto8
PSUtwN+VAgMBAAECggEADI/dpWrquDJWGeEe6qj/3YqBOR3PZfnFrGNIkSn6EpEr
VwpNZQp8BadgHLyiNqP3F+yfsqas+bjVm+yCHMzS47TY6xLraN+UGDBTvrB/4IVd
5jjSpKLdYg3hUEgtUSsY1gkYSd/TNyAWK0xY9eS4VTdJIxCfjGtcrDMYiMZRKvF7
xNcrOOhFiSWSgCvfbTLM8d/zBdO9JmTsEQt7HIYchEnleAsByVa9ri5A5bur9ioY
y02KFcjMPmpFx6cxKA/yVVBst7YcocWYWStLrxms1ERVHwQwxXpNF1BDU46dMUPT
x7tJBiZr3JgUyGGJ6DscltAdhTJ0LLTVgvTPYXMOwwKBgQDVg/257Z9WSxKreyaF
/7UathaVGukuiJk0cvfZeGiVOZve7uZhr1KEicagQsAwe1BnI4OcI4P1tBd0LjRJ
DobbxzgqsHH+totPnI31ajGFFZ2WuKKafW5AYwawBSfaBA7SvYMwtFtRFwDsH5U5
HKcc2cJgeNgCsQ62WJWfxKonqwKBgQDHzuVzU+bicTBJINs0ghhqhRxTOhx+cUya
Lza4oUAbormeMGhwYtx27bkatWXsQvLDswcWEeSUILKMkCIZj6YWgd1ksYuvOOG4
HNkPqemh7+44RhwmujfyUVMBBgz8qR2lJfGtovVMJcz5/xXJV8ucIFhg5rf7OiWB
VydEI2TVvwKBgQCpZLA1hBn3glPrjCaCBN6PtIqx/Mmmy2SQwe10sRx3116cPXi1
YzzaPdxBZPPJAuxFB13w0BRvKFO7LrT4iPfhAWrEI3wtEnHv1Uqiu39SEFYYL5+B
ZaXEm0vA9jYptzJzazrbtxsDeHaY3m2rA9po/zJBC16EtCfx7tG2EXbVRQKBgQCu
I/X6Y5+Qr5Gjyo0B4HijLcwYBUecM+bNYmTQ2UjkTRh1dD8x5Be9V0bCrmJcXaTz
Ru7gH0wWhcDXnS77FCVu7FQmVE8nse2X5xyO+El1J4V5ajFS1223NYWgGMPs2P/L
VZyi9qnPagqRv+4fAvOj6NTd73dd77mMVocUbbyORQKBgD5WH7TPPLTgd4FgumB5
ODLXBqUjjteKURD2+EC6UBI58q3dMiSj35yH93L0IPbQxn4QY8gPe24XXXVTbZUu
PiJxRs27GTo4/UrwPMFalytdh42q9UXRXYVMa1BV1POEesUTIlZyRznY67TERoSx
A+Q6e7vn/daVY5ezU9NV67tB
-----END PRIVATE KEY-----
PEM,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR);
    }

    private function ecPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg3AonhNxooQPe7SSb
R5pXvJZphkqcYTbAILO2YNcmv1qhRANCAAQpexmx1AimwyAw3iOGECbkNU3SH7mU
fysI+oJiBwLPnNBk1aOqAoLezeO2H1ipBE4r5VDwG/NwXjqsnUV+Te+A
-----END PRIVATE KEY-----
PEM;
    }
}
