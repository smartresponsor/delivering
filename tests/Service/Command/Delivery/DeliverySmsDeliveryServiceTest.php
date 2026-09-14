<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Enum\DeliveryDeliveryStatus;
use App\Delivering\Message\Command\Delivery\DeliverySendSms;
use App\Delivering\Service\Command\Delivery\DeliverySmsDeliveryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliverySmsSenderInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeliverySmsDeliveryServiceTest extends TestCase
{
    public function testPersistBeforeSendAndCaptureProviderMessageId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(DeliveryDelivery::class));
        $entityManager->expects(self::exactly(3))->method('flush');

        $sender = new class () implements DeliverySmsSenderInterface {
            public int $calls = 0;

            public function send(string $recipient, string $body, string $correlationId, string $idempotencyKey): string
            {
                ++$this->calls;

                return 'message-123';
            }
        };
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $service = new DeliverySmsDeliveryService($registry, $sender);
        $delivery = $service->send(new DeliverySendSms(
            '+13465550101',
            'New qualified lead.',
            'lead-42',
            'lead:42:manager:7',
        ));

        self::assertSame(1, $sender->calls);
        self::assertSame('message-123', $delivery->providerMessageId());
        self::assertSame(DeliveryDeliveryStatus::Sent, $delivery->status());
    }

    public function testConcurrentDuplicateReturnsPersistedDeliveryWithoutSending(): void
    {
        $existing = new DeliveryDelivery('idem-1', 'corr-1', 'sms', 'telnyx', '+13465550101');

        $firstRepository = $this->createMock(EntityRepository::class);
        $firstRepository->method('findOneBy')->willReturn(null);
        $secondRepository = $this->createMock(EntityRepository::class);
        $secondRepository->method('findOneBy')->willReturn($existing);

        $firstManager = $this->createMock(EntityManagerInterface::class);
        $firstManager->method('getRepository')->willReturn($firstRepository);
        $firstManager->expects(self::once())->method('persist');
        $firstManager->expects(self::once())->method('flush')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class),
        );

        $secondManager = $this->createMock(EntityManagerInterface::class);
        $secondManager->method('getRepository')->willReturn($secondRepository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnOnConsecutiveCalls($firstManager, $secondManager);

        $sender = $this->createMock(DeliverySmsSenderInterface::class);
        $sender->expects(self::never())->method('send');
        $service = new DeliverySmsDeliveryService($registry, $sender);

        $result = $service->send(new DeliverySendSms('+13465550101', 'Body', 'corr-1', 'idem-1'));

        self::assertSame($existing, $result);
    }

    public function testExistingDeliveryIsReturnedBeforePersistOrSend(): void
    {
        $existing = new DeliveryDelivery('idem-1', 'corr-1', 'sms', 'telnyx', '+13465550101');
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $sender = $this->createMock(DeliverySmsSenderInterface::class);
        $sender->expects(self::never())->method('send');

        $result = (new DeliverySmsDeliveryService($registry, $sender))->send(
            new DeliverySendSms('+13465550101', 'Body', 'corr-1', 'idem-1'),
        );

        self::assertSame($existing, $result);
    }

    public function testProviderFailureMarksDeliveryFailedAndRethrows(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects(self::exactly(3))->method('flush');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $sender = $this->createMock(DeliverySmsSenderInterface::class);
        $sender->method('send')->willThrowException(new RuntimeException('provider failed'));

        $this->expectException(RuntimeException::class);
        (new DeliverySmsDeliveryService($registry, $sender))->send(
            new DeliverySendSms('+13465550101', 'Body', 'corr-1', 'idem-1'),
        );
    }

    public function testMissingEntityManagerFailsExplicitly(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(RuntimeException::class);
        (new DeliverySmsDeliveryService($registry, $this->createMock(DeliverySmsSenderInterface::class)))->send(
            new DeliverySendSms('+13465550101', 'Body', 'corr-1', 'idem-1'),
        );
    }
}
