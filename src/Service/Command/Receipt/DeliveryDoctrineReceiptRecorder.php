<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Receipt;

use App\Delivering\Entity\Attempt\DeliveryAttempt;
use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\ServiceInterface\Command\Receipt\DeliveryReceiptRecorderInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeliveryDoctrineReceiptRecorder implements DeliveryReceiptRecorderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function record(DeliveryProcessReceipt $receipt): void
    {
        if (null !== $this->entityManager->getRepository(DeliveryAttempt::class)->findOneBy([
            'eventId' => $receipt->eventId,
        ])) {
            return;
        }

        $delivery = $this->entityManager->getRepository(DeliveryDelivery::class)->findOneBy([
            'providerMessageId' => $receipt->providerMessageId,
        ]);

        if (!$delivery instanceof DeliveryDelivery) {
            $delivery = new DeliveryDelivery(
                'telnyx:'.$receipt->providerMessageId,
                $receipt->eventId,
                'sms',
                'telnyx',
                'unknown',
            );
            $delivery->markSubmitted($receipt->providerMessageId, $receipt->occurredAt);
            $this->entityManager->persist($delivery);
        }

        $delivery->applyReceipt($receipt->status, $receipt->occurredAt);
        $this->entityManager->persist(new DeliveryAttempt(
            $delivery,
            $receipt->eventId,
            $receipt->providerMessageId,
            $receipt->status,
            $receipt->occurredAt,
            $receipt->errorCode,
            $receipt->errorDetail,
        ));
        $this->entityManager->flush();
    }
}
