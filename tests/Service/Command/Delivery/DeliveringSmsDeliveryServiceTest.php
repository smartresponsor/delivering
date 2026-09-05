<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveringDelivery;
use App\Delivering\Enum\DeliveringDeliveryStatus;
use App\Delivering\Message\Command\Delivery\DeliveringSendSms;
use App\Delivering\Service\Command\Delivery\DeliveringSmsDeliveryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveringSmsSenderInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class DeliveringSmsDeliveryServiceTest extends TestCase
{
    public function testPersistBeforeSendAndCaptureProviderMessageId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(DeliveringDelivery::class));
        $entityManager->expects(self::exactly(3))->method('flush');

        $sender = new class () implements DeliveringSmsSenderInterface {
            public int $calls = 0;

            public function send(string $recipient, string $body, string $correlationId, string $idempotencyKey): string
            {
                ++$this->calls;

                return 'message-123';
            }
        };
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $service = new DeliveringSmsDeliveryService($registry, $sender);
        $delivery = $service->send(new DeliveringSendSms(
            '+13465550101',
            'New qualified lead.',
            'lead-42',
            'lead:42:manager:7',
        ));

        self::assertSame(1, $sender->calls);
        self::assertSame('message-123', $delivery->providerMessageId());
        self::assertSame(DeliveringDeliveryStatus::Sent, $delivery->status());
    }

    public function testConcurrentDuplicateReturnsPersistedDeliveryWithoutSending(): void
    {
        $existing = new DeliveringDelivery('idem-1', 'corr-1', 'sms', 'telnyx', '+13465550101');

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

        $sender = $this->createMock(DeliveringSmsSenderInterface::class);
        $sender->expects(self::never())->method('send');
        $service = new DeliveringSmsDeliveryService($registry, $sender);

        $result = $service->send(new DeliveringSendSms('+13465550101', 'Body', 'corr-1', 'idem-1'));

        self::assertSame($existing, $result);
    }
}
