<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Message;

use App\Delivering\Enum\DeliveryDeliveryStatus;
use App\Delivering\Message\Command\Delivery\DeliverySendPush;
use App\Delivering\Message\Command\Delivery\DeliverySendSms;
use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\Service\Query\Push\DeliveryPushReadinessService;
use App\Delivering\Service\Query\Queue\DeliveryMessengerQueueStatusProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

final class DeliveryBoundaryValidationTest extends TestCase
{
    public function testSmsCommandValidationBranchesAndSuccess(): void
    {
        $rejected = 0;
        foreach ([
            ['123', 'Body', 'corr', 'idem'],
            ['+13465550100', ' ', 'corr', 'idem'],
            ['+13465550100', 'Body', ' ', 'idem'],
            ['+13465550100', 'Body', 'corr', ' '],
        ] as $arguments) {
            try {
                new DeliverySendSms(...$arguments);
                self::fail('Invalid SMS command was accepted.');
            } catch (InvalidArgumentException) {
                ++$rejected;
            }
        }

        self::assertSame(4, $rejected);

        $message = new DeliverySendSms('+13465550100', 'Body', 'corr', 'idem');
        self::assertSame('+13465550100', $message->recipient);
    }

    public function testPushCommandValidationBranchesAndDerivedValues(): void
    {
        $hash = str_repeat('a', 64);
        $rejected = 0;
        foreach ([
            ['windows', $hash, 'app', 'Title', 'Body', null, [], 'corr', 'idem'],
            ['ios', 'bad-hash', 'app', 'Title', 'Body', null, [], 'corr', 'idem'],
            ['ios', $hash, ' ', 'Title', 'Body', null, [], 'corr', 'idem'],
            ['ios', $hash, 'app', ' ', 'Body', null, [], 'corr', 'idem'],
            ['ios', $hash, 'app', 'Title', 'Body', null, [], ' ', 'idem'],
            ['ios', $hash, 'app', 'Title', 'Body', null, [], 'corr', ' '],
        ] as $arguments) {
            try {
                new DeliverySendPush(...$arguments);
                self::fail('Invalid push command was accepted.');
            } catch (InvalidArgumentException) {
                ++$rejected;
            }
        }

        self::assertSame(6, $rejected);

        $ios = new DeliverySendPush('ios', $hash, 'app', 'Title', 'Body', null, [], 'corr', 'idem');
        $android = new DeliverySendPush('android', $hash, 'app', 'Title', 'Body', null, [], 'corr', 'idem');
        self::assertSame('apns', $ios->provider());
        self::assertSame('fcm', $android->provider());
        self::assertSame('token-sha256:'.$hash, $ios->tokenReference());
    }

    public function testReceiptValidationBranchesAndSuccess(): void
    {
        $rejected = 0;
        foreach ([
            ['', 'provider-id'],
            ['event-id', ''],
        ] as [$eventId, $providerMessageId]) {
            try {
                new DeliveryProcessReceipt($eventId, $providerMessageId, DeliveryDeliveryStatus::Delivered, new DateTimeImmutable(), null, null);
                self::fail('Invalid receipt was accepted.');
            } catch (InvalidArgumentException) {
                ++$rejected;
            }
        }

        self::assertSame(2, $rejected);

        $receipt = new DeliveryProcessReceipt('event-id', 'provider-id', DeliveryDeliveryStatus::Delivered, new DateTimeImmutable(), null, null);
        self::assertSame('event-id', $receipt->eventId);
    }

    public function testMessengerQueueProviderReadsBothTransports(): void
    {
        $async = $this->createMock(MessageCountAwareInterface::class);
        $async->method('getMessageCount')->willReturn(7);
        $failed = $this->createMock(MessageCountAwareInterface::class);
        $failed->method('getMessageCount')->willReturn(2);

        $status = (new DeliveryMessengerQueueStatusProvider($async, $failed))->status();

        self::assertSame(7, $status->queued);
        self::assertSame(2, $status->failed);
        self::assertFalse($status->isHealthy());
    }

    public function testPushReadinessCoversMalformedEmptyAndValidMaps(): void
    {
        $invalid = new DeliveryPushReadinessService('', '', '', 'not-json', 'invalid', 'not-json', 'not-json');
        $empty = new DeliveryPushReadinessService('team', 'key', 'private', '{}', 'production', '{"client_email":"a","private_key":"b"}', '{}');
        $malformed = new DeliveryPushReadinessService('team', 'key', 'private', '{"":"topic"}', 'production', '{"client_email":"a","private_key":"b"}', '{"app":""}');
        $valid = new DeliveryPushReadinessService('team', 'key', 'private', '{"z":"topic-z","a":"topic-a"}', 'development', '{"client_email":"a","private_key":"b"}', '{"z":"project-z","a":"project-a"}');

        self::assertFalse($invalid->status()['configured']);
        self::assertFalse($empty->status()['configured']);
        self::assertFalse($malformed->status()['configured']);
        $status = $valid->status();
        self::assertTrue($status['configured']);
        self::assertSame(['a', 'z'], $status['apns']['appKeys']);
        self::assertSame(['a', 'z'], $status['fcm']['appKeys']);
    }
}
