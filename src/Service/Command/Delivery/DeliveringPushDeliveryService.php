<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Event\DeliveringPushSubscriptionInvalidated;
use App\Delivering\Exception\DeliveringPermanentTransportException;
use App\Delivering\Message\Command\Delivery\DeliveringSendPush;
use App\Delivering\Service\Observability\DeliveringDeliveryTelemetryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushSenderInterface;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringPushTokenResolverInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class DeliveringPushDeliveryService
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private DeliveringPushSenderInterface $sender,
        private DeliveringPushTokenResolverInterface $tokenResolver,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ?DeliveringDeliveryTelemetryService $telemetry = null,
    ) {
    }

    public function send(DeliveringSendPush $message): DeliveringDelivery
    {
        $entityManager = $this->entityManager();
        $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);
        $provider = $message->provider();

        if ($existing instanceof DeliveringDelivery) {
            $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey, 'push', $provider);

            return $existing;
        }

        $delivery = new DeliveringDelivery(
            $message->idempotencyKey,
            $message->correlationId,
            'push',
            $provider,
            $message->tokenReference(),
        );
        $entityManager->persist($delivery);

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $entityManager = $this->resetEntityManager();
            $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);
            if ($existing instanceof DeliveringDelivery) {
                $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey, 'push', $provider);

                return $existing;
            }

            throw $exception;
        }

        $delivery->markSending(new DateTimeImmutable());
        $entityManager->flush();
        $startedAt = hrtime(true);

        try {
            $token = $this->tokenResolver->resolve($message->tokenHash, $message->platform, $message->appKey);
            $providerMessageId = $this->sender->send(
                $message->platform,
                $token,
                $message->appKey,
                $message->title,
                $message->body,
                $message->actionUrl,
                $message->payload,
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
                'push',
                $provider,
            );
            $this->reportInvalidSubscription($message, $exception);

            throw $exception;
        }

        $delivery->markSubmitted($providerMessageId, new DateTimeImmutable());
        $entityManager->flush();
        $this->telemetry?->succeeded(
            $message->correlationId,
            $message->idempotencyKey,
            $providerMessageId,
            $this->latencyMilliseconds($startedAt),
            'push',
            $provider,
        );

        return $delivery;
    }

    private function reportInvalidSubscription(DeliveringSendPush $message, Throwable $exception): void
    {
        if (!$exception instanceof DeliveringPermanentTransportException || !$exception->recipientInvalid || null === $exception->reasonCode) {
            return;
        }

        try {
            $this->eventDispatcher->dispatch(new DeliveringPushSubscriptionInvalidated(
                platform: $message->platform,
                appKey: $message->appKey,
                tokenHash: $message->tokenHash,
                reasonCode: $exception->reasonCode,
                correlationId: $message->correlationId,
                idempotencyKey: $message->idempotencyKey,
            ));
        } catch (Throwable $feedbackException) {
            $this->logger->error('delivering.push.subscription_invalidation_feedback_failed', [
                'platform' => $message->platform,
                'app_key' => $message->appKey,
                'token_hash' => $message->tokenHash,
                'reason_code' => $exception->reasonCode,
                'correlation_id' => $message->correlationId,
                'feedback_exception_class' => $feedbackException::class,
                'feedback_exception_message' => $feedbackException->getMessage(),
            ]);
        }
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
        $this->managerRegistry->resetManager();

        return $this->entityManager();
    }

    private function findByIdempotencyKey(EntityManagerInterface $entityManager, string $idempotencyKey): ?DeliveringDelivery
    {
        $delivery = $entityManager->getRepository(DeliveringDelivery::class)->findOneBy(['idempotencyKey' => $idempotencyKey]);

        return $delivery instanceof DeliveringDelivery ? $delivery : null;
    }
}
