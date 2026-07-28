<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Entity\Attempt;

use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Enum\DeliveringStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivering_attempt')]
#[ORM\UniqueConstraint(name: 'uniq_delivering_attempt_event', columns: ['event_id'])]
class DeliveringAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: DeliveringDelivery::class)]
    #[ORM\JoinColumn(name: 'delivery_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DeliveringDelivery $delivery;

    #[ORM\Column(name: 'event_id', length: 191)]
    private string $eventId;

    #[ORM\Column(name: 'provider_message_id', length: 191)]
    private string $providerMessageId;

    #[ORM\Column(enumType: DeliveringStatus::class)]
    private DeliveringStatus $status;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'error_code', length: 64, nullable: true)]
    private ?string $errorCode;

    #[ORM\Column(name: 'error_detail', type: 'text', nullable: true)]
    private ?string $errorDetail;

    public function __construct(DeliveringDelivery $delivery, string $eventId, string $providerMessageId, DeliveringStatus $status, DateTimeImmutable $occurredAt, ?string $errorCode, ?string $errorDetail)
    {
        $this->id = Uuid::v7();
        $this->delivery = $delivery;
        $this->eventId = $eventId;
        $this->providerMessageId = $providerMessageId;
        $this->status = $status;
        $this->occurredAt = $occurredAt;
        $this->errorCode = $errorCode;
        $this->errorDetail = $errorDetail;
    }
}
