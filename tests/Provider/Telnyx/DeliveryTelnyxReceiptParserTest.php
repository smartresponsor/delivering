<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Enum\DeliveryDeliveryStatus;
use App\Delivering\Provider\Telnyx\DeliveryTelnyxReceiptParser;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class DeliveryTelnyxReceiptParserTest extends TestCase
{
    public function testParseFinalizedDeliveryReceipt(): void
    {
        $parser = new DeliveryTelnyxReceiptParser();
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
        self::assertSame(DeliveryDeliveryStatus::DeliveryFailed, $receipt->status);
        self::assertSame('40006', $receipt->errorCode);
        self::assertSame('Temporary carrier failure', $receipt->errorDetail);
    }

    public function testIgnoreInboundMessage(): void
    {
        $parser = new DeliveryTelnyxReceiptParser();
        self::assertNull($parser->parse('{"data":{"event_type":"message.received"}}'));
    }

    public function testRejectInvalidJsonAndNonObjectDocuments(): void
    {
        $parser = new DeliveryTelnyxReceiptParser();

        foreach (['{invalid', 'null'] as $payload) {
            try {
                $parser->parse($payload);
                self::fail('Expected malformed Telnyx receipt payload to be rejected.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectMissingDataPayloadAndIdentifiers(): void
    {
        $parser = new DeliveryTelnyxReceiptParser();
        $invalidPayloads = [
            '{}',
            '{"data":{"event_type":"message.sent"}}',
            '{"data":{"event_type":"message.sent","payload":{}}}',
        ];

        foreach ($invalidPayloads as $payload) {
            try {
                $parser->parse($payload);
                self::fail('Expected incomplete Telnyx receipt payload to be rejected.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDefaultStatusesAndErrorCoercion(): void
    {
        $parser = new DeliveryTelnyxReceiptParser();

        $sent = $parser->parse('{"data":{"event_type":"message.sent","id":"event-sent","occurred_at":"2026-09-13T12:00:00+00:00","payload":{"id":"message-sent"}}}');
        $read = $parser->parse('{"data":{"event_type":"message.read","id":"event-read","occurred_at":"2026-09-13T12:00:00+00:00","payload":{"id":"message-read","errors":[{"code":42,"detail":7}]}}}');
        $unknown = $parser->parse('{"data":{"event_type":"message.finalized","id":"event-final","occurred_at":"2026-09-13T12:00:00+00:00","payload":{"id":"message-final","to":[{"status":123}]}}}');

        self::assertNotNull($sent);
        self::assertNotNull($read);
        self::assertNotNull($unknown);
        self::assertSame(DeliveryDeliveryStatus::Sent, $sent->status);
        self::assertSame(DeliveryDeliveryStatus::Read, $read->status);
        self::assertSame('42', $read->errorCode);
        self::assertNull($read->errorDetail);
        self::assertSame(DeliveryDeliveryStatus::Unknown, $unknown->status);
    }
}
