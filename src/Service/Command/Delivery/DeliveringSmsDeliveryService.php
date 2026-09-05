<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Message\Command\Delivery\DeliveringSendSms;
use App\Delivering\Service\Observability\DeliveringDeliveryTelemetryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringSmsSenderInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;

final readonly class DeliveringSmsDeliveryService
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private DeliveringSmsSenderInterface $sender,
        private ?DeliveringDeliveryTelemetryService $telemetry = null,
    ) {
    }

    public function send(DeliveringSendSms $message): DeliveringDelivery
    {
        $entityManager = $this->entityManager();
        $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);

        if ($existing instanceof DeliveringDelivery) {
            $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey);

            return $existing;
        }

        $delivery = new DeliveringDelivery(
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

            if ($existing instanceof DeliveringDelivery) {
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
        $manager = $this->managerRegistry->getManagerForClass(DeliveringDelivery::class);

        if (!$manager instanceof EntityManagerInterface) {
            throw new RuntimeException('Doctrine entity manager for DeliveringDelivery is not available.');
        }

        return $manager;
    }

    private function resetEntityManager(): EntityManagerInterface
    {
        if (method_exists($this->managerRegistry, 'resetManager')) {
            $this->managerRegistry->resetManager();
        }

        return $this->entityManager();
    }

    private function findByIdempotencyKey(EntityManagerInterface $entityManager, string $idempotencyKey): ?DeliveringDelivery
    {
        $delivery = $entityManager->getRepository(DeliveringDelivery::class)->findOneBy([
            'idempotencyKey' => $idempotencyKey,
        ]);

        return $delivery instanceof DeliveringDelivery ? $delivery : null;
    }
}
