<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\EventSubscriber;

use App\Delivering\Message\DeliveringSendSms;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Emits structured operational logs for the Delivering Messenger lifecycle.
 */
final readonly class DeliveringMessengerTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageRetriedEvent::class => 'onRetried',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $context = $this->context($event->getEnvelope(), $event->getReceiverName());

        if (null === $context) {
            return;
        }

        $context['outcome'] = 'handled';
        $this->logger->info('delivering.messenger.handled', $context);
    }

    public function onRetried(WorkerMessageRetriedEvent $event): void
    {
        $context = $this->context($event->getEnvelope(), $event->getReceiverName());

        if (null === $context) {
            return;
        }

        $context['outcome'] = 'retry_scheduled';
        $this->logger->warning('delivering.messenger.retried', $context);
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $context = $this->context($event->getEnvelope(), $event->getReceiverName());

        if (null === $context) {
            return;
        }

        $exception = $event->getThrowable();
        $context['outcome'] = 'failed_terminal';
        $context['exception_class'] = $exception::class;
        $context['exception_message'] = $exception->getMessage();
        $this->logger->error('delivering.messenger.failed_terminal', $context);
    }

    /** @return array<string, int|string>|null */
    private function context(Envelope $envelope, string $receiverName): ?array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof DeliveringSendSms) {
            return null;
        }

        return [
            'component' => 'delivering',
            'channel' => 'sms',
            'receiver' => $receiverName,
            'message_class' => $message::class,
            'correlation_id' => $message->correlationId,
            'idempotency_key' => $message->idempotencyKey,
            'attempt' => RedeliveryStamp::getRetryCountFromEnvelope($envelope) + 1,
        ];
    }
}
