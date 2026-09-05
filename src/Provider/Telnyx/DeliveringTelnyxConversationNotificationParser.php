<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Provider\Telnyx;

use App\Delivering\Message\Command\Delivery\DeliveringSendSms;
use JsonException;
use UnexpectedValueException;

final readonly class DeliveringTelnyxConversationNotificationParser
{
    private const array FIELD_LABELS = [
        'customer_name' => 'Customer',
        'customer_phone' => 'Phone',
        'service' => 'Service',
        'address' => 'Address',
        'details' => 'Details',
        'preferred_time' => 'Preferred time',
    ];

    public function __construct(private string $managerRecipient)
    {
    }

    public function parse(string $json, string $timestamp): ?DeliveringSendSms
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Telnyx webhook body is not valid JSON.', 0, $exception);
        }

        if (!is_array($document)) {
            throw new UnexpectedValueException('Telnyx webhook body must decode to an object.');
        }

        if (array_key_exists('data', $document)) {
            return null;
        }

        if (!ctype_digit($timestamp)) {
            throw new UnexpectedValueException('Telnyx AI notification timestamp is invalid.');
        }

        $lines = ['New AI lead'];
        foreach (self::FIELD_LABELS as $field => $label) {
            $value = $document[$field] ?? null;
            if (is_string($value) && '' !== $value) {
                $lines[] = $label.': '.$value;
            }
        }

        $transportId = hash('sha256', $timestamp.'|'.$json);

        return new DeliveringSendSms(
            $this->managerRecipient,
            implode("\n", $lines),
            $transportId,
            'telnyx-ai:'.$transportId,
        );
    }
}
