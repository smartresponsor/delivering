<?php

declare(strict_types=1);

namespace App\Delivering\Tests\Service\Command\Delivery;

use App\Delivering\Entity\Delivery\DeliveryDelivery;
use App\Delivering\Event\DeliveryPushSubscriptionInvalidated;
use App\Delivering\Exception\DeliveryPermanentTransportException;
use App\Delivering\Message\Command\Delivery\DeliverySendPush;
use App\Delivering\Service\Command\Delivery\DeliveryPushDeliveryService;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushSenderInterface;
use App\Delivering\ServiceInterface\Command\Delivery\DeliveryPushTokenResolverInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DeliveryPushDeliveryServiceTest extends TestCase
{
    public function testSuccessfulSendPersistsBeforeProviderAndStoresProviderMessageId(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(DeliveryDelivery::class));
        $entityManager->expects(self::exactly(3))->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $resolver = $this->createMock(DeliveryPushTokenResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn('device-token');
        $sender = $this->createMock(DeliveryPushSenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturn('push-123');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $delivery = (new DeliveryPushDeliveryService($registry, $sender, $resolver, $dispatcher, new NullLogger()))
            ->send($this->message());

        self::assertSame('push-123', $delivery->providerMessageId());
    }

    public function testInvalidRecipientFailureDispatchesSubscriptionInvalidation(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::exactly(3))->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $resolver = $this->createMock(DeliveryPushTokenResolverInterface::class);
        $resolver->method('resolve')->willReturn('device-token');
        $sender = $this->createMock(DeliveryPushSenderInterface::class);
        $sender->method('send')->willThrowException(new DeliveryPermanentTransportException(
            'invalid token',
            reasonCode: 'UNREGISTERED',
            recipientInvalid: true,
        ));
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(DeliveryPushSubscriptionInvalidated::class));

        $this->expectException(DeliveryPermanentTransportException::class);
        (new DeliveryPushDeliveryService($registry, $sender, $resolver, $dispatcher, new NullLogger()))
            ->send($this->message());
    }

    public function testConcurrentDuplicateReloadsPersistedDeliveryWithoutProviderCall(): void
    {
        $existing = new DeliveryDelivery('idem', 'corr', 'push', 'fcm', 'token-ref');
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
        $registry->expects(self::once())->method('resetManager');
        $registry->method('getManagerForClass')->willReturnOnConsecutiveCalls($firstManager, $secondManager);
        $sender = $this->createMock(DeliveryPushSenderInterface::class);
        $sender->expects(self::never())->method('send');
        $resolver = $this->createMock(DeliveryPushTokenResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $result = (new DeliveryPushDeliveryService(
            $registry,
            $sender,
            $resolver,
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
        ))->send($this->message());

        self::assertSame($existing, $result);
    }

    private function message(): DeliverySendPush
    {
        return new DeliverySendPush(
            'android',
            str_repeat('a', 64),
            'app',
            'Title',
            'Body',
            null,
            [],
            'corr',
            'idem',
        );
    }
}
