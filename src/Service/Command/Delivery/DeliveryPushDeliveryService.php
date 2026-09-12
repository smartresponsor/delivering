<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Event\DeliveryPushSubscriptionInvalidated;
use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Message\Command\Delivery\DeliverySendPush;
use App\Delivering\Service\Observability\DeliveryDeliveryTelemetryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushSenderInterface;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushTokenResolverInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class DeliveryPushDeliveryService
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private DeliveryPushSenderInterface $sender,
        private DeliveryPushTokenResolverInterface $tokenResolver,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ?DeliveryDeliveryTelemetryService $telemetry = null,
    ) {
    }

    public function send(DeliverySendPush $message): DeliveryDelivery
    {
        $entityManager = $this->entityManager();
        $existing = $this->findByIdempotencyKey($entityManager, $message->idempotencyKey);
        $provider = $message->provider();

        if ($existing instanceof DeliveryDelivery) {
            $this->telemetry?->duplicate($message->correlationId, $message->idempotencyKey, 'push', $provider);

            return $existing;
        }

        $delivery = new DeliveryDelivery(
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
            if ($existing instanceof DeliveryDelivery) {
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

    private function reportInvalidSubscription(DeliverySendPush $message, Throwable $exception): void
    {
        if (!$exception instanceof DeliveryPermanentTransportException || !$exception->recipientInvalid || null === $exception->reasonCode) {
            return;
        }

        try {
            $this->eventDispatcher->dispatch(new DeliveryPushSubscriptionInvalidated(
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
        $delivery = $entityManager->getRepository(DeliveryDelivery::class)->findOneBy(['idempotencyKey' => $idempotencyKey]);

        return $delivery instanceof DeliveryDelivery ? $delivery : null;
    }
}
