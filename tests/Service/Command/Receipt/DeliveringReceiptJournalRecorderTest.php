<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Command\Receipt;

use App\Delivering\Enum\DeliveringStatus;
use App\Delivering\Message\DeliveringProcessReceipt;
use App\Delivering\Service\Command\Receipt\DeliveringReceiptJournalRecorder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeliveringReceiptJournalRecorderTest extends TestCase
{
    public function testRecordIsIdempotentByEventId(): void
    {
        $directory = sys_get_temp_dir().'/delivering-'.bin2hex(random_bytes(6));
        $journalPath = $directory.'/receipt.ndjson';
        $recorder = new DeliveringReceiptJournalRecorder($journalPath);
        $receipt = new DeliveringProcessReceipt(
            'event-123',
            'message-123',
            DeliveringStatus::Delivered,
            new DateTimeImmutable('2026-07-26T12:00:00+00:00'),
            null,
            null,
        );

        $recorder->record($receipt);
        $recorder->record($receipt);

        $lines = file($journalPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertStringContainsString('"event_id":"event-123"', $lines[0]);

        unlink($journalPath);
        rmdir($directory);
    }
}
