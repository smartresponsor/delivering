<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Provider\Telnyx;

use App\Delivering\Enum\DeliveryDeliveryStatus;
use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use DateTimeImmutable;
use JsonException;
use UnexpectedValueException;

final readonly class DeliveryTelnyxReceiptParser
{
    public function parse(string $json): ?DeliveryProcessReceipt
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Telnyx webhook body is not valid JSON.', 0, $exception);
        }

        if (!is_array($document)) {
            throw new UnexpectedValueException('Telnyx webhook body must decode to an object.');
        }

        $data = $document['data'] ?? null;
        if (!is_array($data)) {
            throw new UnexpectedValueException('Telnyx webhook does not contain data.');
        }

        $eventType = $data['event_type'] ?? null;
        if (!in_array($eventType, ['message.sent', 'message.finalized', 'message.read'], true)) {
            return null;
        }

        $payload = $data['payload'] ?? null;
        if (!is_array($payload)) {
            throw new UnexpectedValueException('Telnyx webhook does not contain a payload.');
        }

        $eventId = $data['id'] ?? null;
        $providerMessageId = $payload['id'] ?? null;
        $occurredAt = $data['occurred_at'] ?? null;
        $statusValue = $payload['to'][0]['status'] ?? match ($eventType) {
            'message.sent' => DeliveryDeliveryStatus::Sent->value,
            'message.read' => DeliveryDeliveryStatus::Read->value,
            default => DeliveryDeliveryStatus::Unknown->value,
        };

        if (!is_string($eventId) || !is_string($providerMessageId) || !is_string($occurredAt)) {
            throw new UnexpectedValueException('Telnyx receipt identifiers or timestamp are missing.');
        }

        $firstError = is_array($payload['errors'][0] ?? null) ? $payload['errors'][0] : [];
        $errorCode = is_scalar($firstError['code'] ?? null) ? (string) $firstError['code'] : null;
        $errorDetail = is_string($firstError['detail'] ?? null) ? $firstError['detail'] : null;

        return new DeliveryProcessReceipt(
            $eventId,
            $providerMessageId,
            DeliveryDeliveryStatus::tryFrom(is_string($statusValue) ? $statusValue : '') ?? DeliveryDeliveryStatus::Unknown,
            new DateTimeImmutable($occurredAt),
            $errorCode,
            $errorDetail,
        );
    }
}
