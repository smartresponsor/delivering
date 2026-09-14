<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Operational;

use App\Delivering\Command\Status\Push\DeliveryPushStatusCommand;
use App\Delivering\Command\Status\Queue\DeliveryQueueStatusCommand;
use App\Delivering\Event\DeliveryPushSubscriptionInvalidated;
use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Exception\DeliveryTransportException;
use App\Delivering\Service\Command\Delivery\DeliveryPushSenderRouter;
use App\Delivering\Service\Command\Delivery\DeliveryUnavailablePushTokenResolver;
use App\Delivering\Service\Observability\DeliveryDeliveryTelemetryService;
use App\Delivering\Service\Query\Push\DeliveryPushReadinessService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushProviderInterface;
use App\Delivering\ServiceInterface\Query\Queue\DeliveryQueueStatusProviderInterface;
use App\Delivering\ValueObject\Queue\DeliveryQueueStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DeliveryOperationalContractTest extends TestCase
{
    public function testPushRouterDelegatesToSupportingProvider(): void
    {
        $provider = new class () implements DeliveryPushProviderInterface {
            public function supports(string $platform): bool
            {
                return 'ios' === $platform;
            }
            public function send(string $token, string $appKey, string $title, string $body, ?string $actionUrl, array $payload, string $correlationId, string $idempotencyKey): string
            {
                return 'provider-message';
            }
        };

        $router = new DeliveryPushSenderRouter([$provider]);

        self::assertSame('provider-message', $router->send('ios', 'token', 'app', 'Title', 'Body', null, [], 'corr', 'idem'));
    }

    public function testPushRouterRejectsUnsupportedPlatform(): void
    {
        $router = new DeliveryPushSenderRouter([]);

        $this->expectException(DeliveryPermanentTransportException::class);
        $router->send('windows', 'token', 'app', 'Title', 'Body', null, [], 'corr', 'idem');
    }

    public function testUnavailableTokenResolverFailsExplicitly(): void
    {
        $this->expectException(DeliveryPermanentTransportException::class);
        (new DeliveryUnavailablePushTokenResolver())->resolve('hash', 'ios', 'app');
    }

    public function testQueueStatusHealthAndValidation(): void
    {
        $healthy = new DeliveryQueueStatus(2, 0, new DateTimeImmutable('2026-09-13T12:00:00+00:00'));
        $failed = new DeliveryQueueStatus(2, 1, new DateTimeImmutable('2026-09-13T12:00:00+00:00'));

        self::assertTrue($healthy->isHealthy());
        self::assertFalse($failed->isHealthy());

        $this->expectException(\InvalidArgumentException::class);
        new DeliveryQueueStatus(-1, 0, new DateTimeImmutable());
    }

    public function testInvalidationEventValidatesRequiredIdentity(): void
    {
        $event = new DeliveryPushSubscriptionInvalidated('ios', 'app', 'hash', 'Unregistered', 'corr', 'idem');
        self::assertSame('hash', $event->tokenHash);

        $this->expectException(\InvalidArgumentException::class);
        new DeliveryPushSubscriptionInvalidated('ios', 'app', '', 'Unregistered', 'corr', 'idem');
    }

    public function testQueueStatusCommandReturnsSuccessAndFailure(): void
    {
        $provider = $this->createMock(DeliveryQueueStatusProviderInterface::class);
        $provider->method('status')->willReturnOnConsecutiveCalls(
            new DeliveryQueueStatus(1, 0, new DateTimeImmutable('2026-09-13T12:00:00+00:00')),
            new DeliveryQueueStatus(0, 1, new DateTimeImmutable('2026-09-13T12:01:00+00:00')),
        );
        $command = new DeliveryQueueStatusCommand($provider);

        self::assertSame(Command::SUCCESS, (new CommandTester($command))->execute([]));
        self::assertSame(Command::FAILURE, (new CommandTester($command))->execute([]));
    }

    public function testPushStatusCommandReportsConfiguredAndMissingStates(): void
    {
        $configured = new DeliveryPushReadinessService(
            'team',
            'key',
            'private',
            '{"app":"topic"}',
            'production',
            '{"client_email":"push@example.test","private_key":"private"}',
            '{"app":"project"}',
        );
        $missing = new DeliveryPushReadinessService('', '', '', '{}', 'invalid', '{}', '{}');

        self::assertSame(Command::SUCCESS, (new CommandTester(new DeliveryPushStatusCommand($configured)))->execute([]));
        self::assertSame(Command::FAILURE, (new CommandTester(new DeliveryPushStatusCommand($missing)))->execute([]));
    }

    public function testTelemetryEmitsStableLifecycleRecords(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|Stringable, context: array<string, mixed>}> */
            public array $records = [];
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
        $telemetry = new DeliveryDeliveryTelemetryService($logger);

        $telemetry->duplicate('corr', 'idem');
        $telemetry->succeeded('corr', 'idem', 'provider-id', 12.5);
        $telemetry->failed('corr', 'idem', new DeliveryTransportException('retry'), 15.0);
        $telemetry->failed('corr', 'idem', new DeliveryPermanentTransportException('permanent'), 16.0);
        $telemetry->failed('corr', 'idem', new \RuntimeException('unknown'), 17.0);

        self::assertSame(
            ['delivering.delivery.duplicate', 'delivering.delivery.succeeded', 'delivering.delivery.failed', 'delivering.delivery.failed', 'delivering.delivery.failed'],
            array_map(static fn (array $record): string => (string) $record['message'], $logger->records),
        );
        self::assertSame('transient', $logger->records[2]['context']['failure_classification']);
        self::assertSame('permanent', $logger->records[3]['context']['failure_classification']);
        self::assertSame('unknown', $logger->records[4]['context']['failure_classification']);
    }
}
