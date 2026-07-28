<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Receipt;

use App\Delivering\Entity\Attempt\DeliveringAttempt;
use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Message\DeliveringProcessReceipt;
use App\Delivering\ServiceInterface\Command\Receipt\DeliveringReceiptRecorderInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeliveringDoctrineReceiptRecorder implements DeliveringReceiptRecorderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function record(DeliveringProcessReceipt $receipt): void
    {
        if (null !== $this->entityManager->getRepository(DeliveringAttempt::class)->findOneBy([
            'eventId' => $receipt->eventId,
        ])) {
            return;
        }

        $delivery = $this->entityManager->getRepository(DeliveringDelivery::class)->findOneBy([
            'providerMessageId' => $receipt->providerMessageId,
        ]);

        if (!$delivery instanceof DeliveringDelivery) {
            $delivery = new DeliveringDelivery(
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
        $this->entityManager->persist(new DeliveringAttempt(
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
