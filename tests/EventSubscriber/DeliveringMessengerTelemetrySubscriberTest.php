<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\EventSubscriber;

use App\Delivering\EventSubscriber\DeliveringMessengerTelemetrySubscriber;
use App\Delivering\Message\Command\Delivery\DeliveringSendSms;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class DeliveringMessengerTelemetrySubscriberTest extends TestCase
{
    public function testLogsHandledAndRetriedDeliveringMessage(): void
    {
        $logger = new DeliveringArrayLogger();
        $subscriber = new DeliveringMessengerTelemetrySubscriber($logger);

        $subscriber->onHandled(new WorkerMessageHandledEvent($this->envelope(), 'delivering_async'));
        $subscriber->onRetried(new WorkerMessageRetriedEvent(
            $this->envelope()->with(new RedeliveryStamp(2)),
            'delivering_async',
        ));

        self::assertSame('delivering.messenger.handled', $logger->records[0]['message']);
        self::assertSame('corr-42', $logger->records[0]['context']['correlation_id']);
        self::assertSame(1, $logger->records[0]['context']['attempt']);
        self::assertSame('warning', $logger->records[1]['level']);
        self::assertSame(3, $logger->records[1]['context']['attempt']);
    }

    public function testLogsOnlyTerminalFailure(): void
    {
        $logger = new DeliveringArrayLogger();
        $subscriber = new DeliveringMessengerTelemetrySubscriber($logger);
        $retrying = new WorkerMessageFailedEvent(
            $this->envelope(),
            'delivering_async',
            new \RuntimeException('Temporary failure'),
        );
        $retrying->setForRetry();

        $subscriber->onFailed($retrying);
        self::assertSame([], $logger->records);

        $subscriber->onFailed(new WorkerMessageFailedEvent(
            $this->envelope()->with(new RedeliveryStamp(4)),
            'delivering_async',
            new \RuntimeException('Retries exhausted'),
        ));

        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('delivering.messenger.failed_terminal', $logger->records[0]['message']);
        self::assertSame(5, $logger->records[0]['context']['attempt']);
        self::assertSame('Retries exhausted', $logger->records[0]['context']['exception_message']);
    }

    public function testIgnoresMessagesOwnedByOtherComponents(): void
    {
        $logger = new DeliveringArrayLogger();
        $subscriber = new DeliveringMessengerTelemetrySubscriber($logger);

        $subscriber->onHandled(new WorkerMessageHandledEvent(new Envelope(new \stdClass()), 'async'));

        self::assertSame([], $logger->records);
    }

    private function envelope(): Envelope
    {
        return new Envelope(new DeliveringSendSms(
            '+13465550101',
            'Operational test',
            'corr-42',
            'idem-42',
        ));
    }
}

/** @internal */
final class DeliveringArrayLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
