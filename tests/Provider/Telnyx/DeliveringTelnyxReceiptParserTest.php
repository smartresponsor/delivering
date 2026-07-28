<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Enum\DeliveringStatus;
use App\Delivering\Provider\Telnyx\DeliveringTelnyxReceiptParser;
use PHPUnit\Framework\TestCase;

final class DeliveringTelnyxReceiptParserTest extends TestCase
{
    public function testParseFinalizedDeliveryReceipt(): void
    {
        $parser = new DeliveringTelnyxReceiptParser();
        $receipt = $parser->parse(json_encode([
            'data' => [
                'event_type' => 'message.finalized',
                'id' => 'event-123',
                'occurred_at' => '2026-07-26T12:00:00+00:00',
                'payload' => [
                    'id' => 'message-123',
                    'to' => [['status' => 'delivery_failed']],
                    'errors' => [['code' => '40006', 'detail' => 'Temporary carrier failure']],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertNotNull($receipt);
        self::assertSame('event-123', $receipt->eventId);
        self::assertSame('message-123', $receipt->providerMessageId);
        self::assertSame(DeliveringStatus::DeliveryFailed, $receipt->status);
        self::assertSame('40006', $receipt->errorCode);
        self::assertSame('Temporary carrier failure', $receipt->errorDetail);
    }

    public function testIgnoreInboundMessage(): void
    {
        $parser = new DeliveringTelnyxReceiptParser();
        self::assertNull($parser->parse('{"data":{"event_type":"message.received"}}'));
    }
}
