<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Entity\Attempt;

use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Enum\DeliveryDeliveryStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivering_attempt')]
#[ORM\UniqueConstraint(name: 'uniq_delivering_attempt_event', columns: ['event_id'])]
class DeliveryAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: DeliveryDelivery::class)]
    #[ORM\JoinColumn(name: 'delivery_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DeliveryDelivery $delivery;

    #[ORM\Column(name: 'event_id', length: 191)]
    private string $eventId;

    #[ORM\Column(name: 'provider_message_id', length: 191)]
    private string $providerMessageId;

    #[ORM\Column(enumType: DeliveryDeliveryStatus::class)]
    private DeliveryDeliveryStatus $status;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'error_code', length: 64, nullable: true)]
    private ?string $errorCode;

    #[ORM\Column(name: 'error_detail', type: 'text', nullable: true)]
    private ?string $errorDetail;

    public function __construct(DeliveryDelivery $delivery, string $eventId, string $providerMessageId, DeliveryDeliveryStatus $status, DateTimeImmutable $occurredAt, ?string $errorCode, ?string $errorDetail)
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
