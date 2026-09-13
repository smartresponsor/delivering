<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Command\Receipt;

use App\Delivering\Entity\Attempt\DeliveryAttempt;
use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Enum\DeliveryDeliveryStatus;
use App\Delivering\Message\Command\Receipt\DeliveryProcessReceipt;
use App\Delivering\Service\Command\Receipt\DeliveryDoctrineReceiptRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class DeliveryDoctrineReceiptRecorderTest extends TestCase
{
    public function testDuplicateReceiptReturnsWithoutMutation(): void
    {
        $attemptRepository = $this->createMock(EntityRepository::class);
        $attemptRepository->method('findOneBy')->willReturn(new \stdClass());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($attemptRepository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        (new DeliveryDoctrineReceiptRecorder($entityManager))->record($this->receipt());
    }

    public function testReceiptUpdatesExistingDeliveryAndPersistsAttempt(): void
    {
        $delivery = new DeliveryDelivery('idem', 'corr', 'sms', 'telnyx', '+13465550100');
        $delivery->markSubmitted('provider-id', new DateTimeImmutable('2026-09-13T12:00:00+00:00'));

        $attemptRepository = $this->createMock(EntityRepository::class);
        $attemptRepository->method('findOneBy')->willReturn(null);
        $deliveryRepository = $this->createMock(EntityRepository::class);
        $deliveryRepository->method('findOneBy')->willReturn($delivery);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => DeliveryAttempt::class === $class ? $attemptRepository : $deliveryRepository,
        );
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(DeliveryAttempt::class));
        $entityManager->expects(self::once())->method('flush');

        (new DeliveryDoctrineReceiptRecorder($entityManager))->record($this->receipt());

        self::assertSame(DeliveryDeliveryStatus::Delivered, $delivery->status());
    }

    public function testReceiptCreatesMissingDeliveryThenPersistsAttempt(): void
    {
        $attemptRepository = $this->createMock(EntityRepository::class);
        $attemptRepository->method('findOneBy')->willReturn(null);
        $deliveryRepository = $this->createMock(EntityRepository::class);
        $deliveryRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => DeliveryAttempt::class === $class ? $attemptRepository : $deliveryRepository,
        );
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        (new DeliveryDoctrineReceiptRecorder($entityManager))->record($this->receipt());
    }

    private function receipt(): DeliveryProcessReceipt
    {
        return new DeliveryProcessReceipt(
            'event-id',
            'provider-id',
            DeliveryDeliveryStatus::Delivered,
            new DateTimeImmutable('2026-09-13T12:01:00+00:00'),
            null,
            null,
        );
    }
}
