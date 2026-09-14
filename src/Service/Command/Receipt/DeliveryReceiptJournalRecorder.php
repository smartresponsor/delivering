<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Receipt;

use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\ServiceInterface\Command\Receipt\DeliveryReceiptRecorderInterface;
use JsonException;
use RuntimeException;

final readonly class DeliveryReceiptJournalRecorder implements DeliveryReceiptRecorderInterface
{
    public function __construct(private string $journalPath)
    {
    }

    public function record(DeliveryProcessReceipt $receipt): void
    {
        $directory = dirname($this->journalPath);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create delivery receipt journal directory "%s".', $directory));
        }

        $handle = fopen($this->journalPath, 'c+b');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to open delivery receipt journal "%s".', $this->journalPath));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock delivery receipt journal.');
            }

            $contents = stream_get_contents($handle);
            if (false === $contents) {
                throw new RuntimeException('Unable to read delivery receipt journal.');
            }

            if (str_contains($contents, '"event_id":"'.$receipt->eventId.'"')) {
                return;
            }

            fseek($handle, 0, SEEK_END);
            $line = json_encode([
                'event_id' => $receipt->eventId,
                'provider_message_id' => $receipt->providerMessageId,
                'status' => $receipt->status->value,
                'occurred_at' => $receipt->occurredAt->format(DATE_ATOM),
                'error_code' => $receipt->errorCode,
                'error_detail' => $receipt->errorDetail,
            ], JSON_THROW_ON_ERROR).PHP_EOL;

            if (false === fwrite($handle, $line)) {
                throw new RuntimeException('Unable to append delivery receipt journal.');
            }
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode delivery receipt journal entry.', 0, $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
