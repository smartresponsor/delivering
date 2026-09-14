<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Exception\DeliveryTransportException;
use App\Delivering\Provider\Telnyx\DeliveryTelnyxSmsSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeliveryTelnyxSmsSenderTest extends TestCase
{
    public function testSendReturnsProviderMessageId(): void
    {
        $response = new MockResponse('{"data":{"id":"message-123"}}', ['http_code' => 200]);
        $client = new MockHttpClient($response);
        $sender = new DeliveryTelnyxSmsSender($client, 'test-key', '+13465550100');

        $messageId = $sender->send(
            '+13465550101',
            'New qualified lead.',
            'lead-42',
            'lead:42:manager:7',
        );

        self::assertSame('message-123', $messageId);

        $requestOptions = $response->getRequestOptions();
        self::assertSame('Authorization: Bearer test-key', $requestOptions['normalized_headers']['authorization'][0]);
        self::assertSame('Idempotency-Key: lead:42:manager:7', $requestOptions['normalized_headers']['idempotency-key'][0]);
        self::assertSame('X-Correlation-Id: lead-42', $requestOptions['normalized_headers']['x-correlation-id'][0]);
    }

    public function testServerFailureIsRetryable(): void
    {
        $sender = new DeliveryTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 503])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveryTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testClientRejectionIsPermanent(): void
    {
        $sender = new DeliveryTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 422])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveryPermanentTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testRateLimitIsRetryable(): void
    {
        $sender = new DeliveryTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 429])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveryTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testMissingApiKeyIsPermanent(): void
    {
        $sender = new DeliveryTelnyxSmsSender(new MockHttpClient(), '', '+13465550100');

        $this->expectException(DeliveryPermanentTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testInvalidSenderNumberIsPermanent(): void
    {
        $sender = new DeliveryTelnyxSmsSender(new MockHttpClient(), 'test-key', 'invalid');

        $this->expectException(DeliveryPermanentTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testMissingProviderMessageIdIsRetryable(): void
    {
        $sender = new DeliveryTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"data":{}}', ['http_code' => 200])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveryTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }
}
