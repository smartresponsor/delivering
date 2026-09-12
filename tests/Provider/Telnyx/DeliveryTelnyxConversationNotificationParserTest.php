<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Provider\Telnyx;

use App\Delivering\Provider\Telnyx\DeliveryTelnyxConversationNotificationParser;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class DeliveryTelnyxConversationNotificationParserTest extends TestCase
{
    public function testBuildManagerSmsFromConfirmedAiFields(): void
    {
        $payload = json_encode([
            'customer_name' => 'Alex Tishchenko',
            'customer_phone' => '+1 (346) 883-2743',
            'service' => 'TV mounting ',
            'address' => '1944 Katy Fort Bend Rd, Apt 5201',
            'details' => '75 inch TV needs to be mounted on top of the fireplace in the living room',
            'preferred_time' => 'July 24 1:35PM',
        ], JSON_THROW_ON_ERROR);
        $parser = new DeliveryTelnyxConversationNotificationParser('+13465550101');

        $message = $parser->parse($payload, '1784944200');
        $transportId = hash('sha256', '1784944200|'.$payload);

        self::assertNotNull($message);
        self::assertSame('+13465550101', $message->recipient);
        self::assertSame(
            "New AI lead\nCustomer: Alex Tishchenko\nPhone: +1 (346) 883-2743\nService: TV mounting \nAddress: 1944 Katy Fort Bend Rd, Apt 5201\nDetails: 75 inch TV needs to be mounted on top of the fireplace in the living room\nPreferred time: July 24 1:35PM",
            $message->body,
        );
        self::assertSame($transportId, $message->correlationId);
        self::assertSame('telnyx-ai:'.$transportId, $message->idempotencyKey);
    }

    public function testMissingOptionalFieldsAreSimplyOmitted(): void
    {
        $parser = new DeliveryTelnyxConversationNotificationParser('+13465550101');

        $message = $parser->parse('{"customer_name":"Alex"}', '1784944200');

        self::assertNotNull($message);
        self::assertSame("New AI lead\nCustomer: Alex", $message->body);
    }

    public function testIgnoreStandardTelnyxEnvelope(): void
    {
        $parser = new DeliveryTelnyxConversationNotificationParser('+13465550101');

        self::assertNull($parser->parse('{"data":{"event_type":"message.finalized"}}', '1784944200'));
    }

    public function testRejectInvalidJson(): void
    {
        $parser = new DeliveryTelnyxConversationNotificationParser('+13465550101');

        $this->expectException(UnexpectedValueException::class);
        $parser->parse('{invalid', '1784944200');
    }
}
