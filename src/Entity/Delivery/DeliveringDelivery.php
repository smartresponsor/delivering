<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Entity\Delivery;

use App\Delivering\Enum\DeliveringDeliveryStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivering_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_delivering_delivery_idempotency', columns: ['idempotency_key'])]
#[ORM\UniqueConstraint(name: 'uniq_delivering_delivery_provider_message', columns: ['provider_message_id'])]
class DeliveringDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'idempotency_key', length: 191)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'correlation_id', length: 191)]
    private string $correlationId;

    #[ORM\Column(length: 32)]
    private string $channel;

    #[ORM\Column(length: 64)]
    private string $provider;

    #[ORM\Column(length: 191)]
    private string $recipient;

    #[ORM\Column(name: 'provider_message_id', length: 191, nullable: true)]
    private ?string $providerMessageId = null;

    #[ORM\Column(enumType: DeliveringDeliveryStatus::class)]
    private DeliveringDeliveryStatus $status;

    #[ORM\Column(name: 'status_occurred_at', type: 'datetime_immutable')]
    private DateTimeImmutable $statusOccurredAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $idempotencyKey, string $correlationId, string $channel, string $provider, string $recipient)
    {
        $this->id = Uuid::v7();
        $this->idempotencyKey = $idempotencyKey;
        $this->correlationId = $correlationId;
        $this->channel = $channel;
        $this->provider = $provider;
        $this->recipient = $recipient;
        $this->status = DeliveringDeliveryStatus::Queued;
        $this->statusOccurredAt = new DateTimeImmutable('@0');
        $this->createdAt = new DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function providerMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function status(): DeliveringDeliveryStatus
    {
        return $this->status;
    }

    public function markSending(DateTimeImmutable $occurredAt): void
    {
        $this->advanceStatus(DeliveringDeliveryStatus::Sending, $occurredAt);
    }

    public function markSubmitted(string $providerMessageId, DateTimeImmutable $occurredAt): void
    {
        $this->providerMessageId = $providerMessageId;
        $this->advanceStatus(DeliveringDeliveryStatus::Sent, $occurredAt);
    }

    public function markSendingFailed(DateTimeImmutable $occurredAt): void
    {
        $this->advanceStatus(DeliveringDeliveryStatus::SendingFailed, $occurredAt);
    }

    public function applyReceipt(DeliveringDeliveryStatus $status, DateTimeImmutable $occurredAt): void
    {
        $this->advanceStatus($status, $occurredAt);
    }

    private function advanceStatus(DeliveringDeliveryStatus $status, DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt < $this->statusOccurredAt) {
            return;
        }

        $this->status = $status;
        $this->statusOccurredAt = $occurredAt;
    }
}
