<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Message\Command\Delivery\DeliverySendSms;
use App\Delivering\Service\Observability\DeliveryDeliveryTelemetryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliverySmsSenderInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;

final readonly class DeliverySmsDeliveryService
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private DeliverySmsSenderInterface $sender,
        private ?DeliveryDeliveryTelemetryService $telemetry = null,
    ) {
    }

    public function send(DeliverySendSms $message): DeliveryDelivery
    {
        $entityManager = $this->entityManager();
        $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);

        if ($existing instanceof DeliveryDelivery) {
            $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey);

            return $existing;
        }

        $delivery = new DeliveryDelivery(
            $message->idempotencyKey,
            $message->correlationId,
            'sms',
            'telnyx',
            $message->recipient,
        );
        $entityManager->persist($delivery);

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $entityManager = $this->resetEntityManager();
            $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);

            if ($existing instanceof DeliveryDelivery) {
                $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey);

                return $existing;
            }

            throw $exception;
        }

        $delivery->markSending(new DateTimeImmutable());
        $entityManager->flush();

        $startedAt = hrtime(true);

        try {
            $providerMessageId = $this->sender->send(
                $message->recipient,
                $message->body,
                $message->correlationId,
                $message->idempotencyKey,
            );
        } catch (Throwable $exception) {
            $delivery->markSendingFailed(new DateTimeImmutable());
            $entityManager->flush();
            $this->telemetry?->failed(
                $message->correlationId,
                $message->idempotencyKey,
                $exception,
                $this->latencyMilliseconds($startedAt),
            );

            throw $exception;
        }

        $delivery->markSubmitted($providerMessageId, new DateTimeImmutable());
        $entityManager->flush();
        $this->telemetry?->succeeded(
            $message->correlationId,
            $message->idempotencyKey,
            $providerMessageId,
            $this->latencyMilliseconds($startedAt),
        );

        return $delivery;
    }

    private function latencyMilliseconds(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = $this->managerRegistry->getManagerForClass(DeliveryDelivery::class);

        if (!$manager instanceof EntityManagerInterface) {
            throw new RuntimeException('Doctrine entity manager for DeliveryDelivery is not available.');
        }

        return $manager;
    }

    private function resetEntityManager(): EntityManagerInterface
    {
        $this->managerRegistry->resetManager();

        return $this->entityManager();
    }

    private function findByIdempotencyKey(EntityManagerInterface $entityManager, string $idempotencyKey): ?DeliveryDelivery
    {
        $delivery = $entityManager->getRepository(DeliveryDelivery::class)->findOneBy([
            'idempotencyKey' => $idempotencyKey,
        ]);

        return $delivery instanceof DeliveryDelivery ? $delivery : null;
    }
}
