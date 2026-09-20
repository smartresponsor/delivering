<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Transport;

use App\Delivering\Service\Transport\DeliveryRetryAfterParser;
use PHPUnit\Framework\TestCase;

final class DeliveryRetryAfterParserTest extends TestCase
{
    public function testParsesDeltaSecondsAndHttpDate(): void
    {
        self::assertSame(120_000, DeliveryRetryAfterParser::milliseconds('120'));
        self::assertSame(
            90_000,
            DeliveryRetryAfterParser::milliseconds('Mon, 17 Aug 2026 19:31:30 GMT', 1_786_995_000),
        );
    }

    public function testRejectsMissingOrMalformedValues(): void
    {
        self::assertNull(DeliveryRetryAfterParser::milliseconds(null));
        self::assertNull(DeliveryRetryAfterParser::milliseconds('   '));
        self::assertNull(DeliveryRetryAfterParser::milliseconds('not-a-date'));
    }
}
