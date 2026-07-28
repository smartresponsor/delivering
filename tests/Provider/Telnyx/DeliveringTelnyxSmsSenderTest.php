<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Exception\DeliveringTransportException;
use App\Delivering\Provider\Telnyx\DeliveringTelnyxSmsSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeliveringTelnyxSmsSenderTest extends TestCase
{
    public function testSendReturnsProviderMessageId(): void
    {
        $response = new MockResponse('{"data":{"id":"message-123"}}', ['http_code' => 200]);
        $client = new MockHttpClient($response);
        $sender = new DeliveringTelnyxSmsSender($client, 'test-key', '+13465550100');

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
        $sender = new DeliveringTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 503])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveringTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testClientRejectionIsPermanent(): void
    {
        $sender = new DeliveringTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 422])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveringPermanentTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }

    public function testRateLimitIsRetryable(): void
    {
        $sender = new DeliveringTelnyxSmsSender(
            new MockHttpClient(new MockResponse('{"errors":[]}', ['http_code' => 429])),
            'test-key',
            '+13465550100',
        );

        $this->expectException(DeliveringTransportException::class);
        $sender->send('+13465550101', 'Body', 'corr-1', 'idem-1');
    }
}
