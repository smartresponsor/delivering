<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Entity\Delivery;

use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Enum\DeliveringStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeliveringDeliveryTest extends TestCase
{
    public function testIgnoreOutOfOrderReceipt(): void
    {
        $delivery = new DeliveringDelivery('idem-1', 'corr-1', 'sms', 'telnyx', '+13465550100');
        $delivery->markSubmitted('message-1', new DateTimeImmutable('2026-07-26T12:00:00+00:00'));
        $delivery->applyReceipt(DeliveringStatus::Delivered, new DateTimeImmutable('2026-07-26T12:02:00+00:00'));
        $delivery->applyReceipt(DeliveringStatus::Sent, new DateTimeImmutable('2026-07-26T12:01:00+00:00'));

        self::assertSame(DeliveringStatus::Delivered, $delivery->status());
    }

    public function testAcceptNewerReceipt(): void
    {
        $delivery = new DeliveringDelivery('idem-2', 'corr-2', 'sms', 'telnyx', '+13465550101');
        $delivery->markSubmitted('message-2', new DateTimeImmutable('2026-07-26T12:00:00+00:00'));
        $delivery->applyReceipt(DeliveringStatus::DeliveryFailed, new DateTimeImmutable('2026-07-26T12:03:00+00:00'));

        self::assertSame(DeliveringStatus::DeliveryFailed, $delivery->status());
    }
}
