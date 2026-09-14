<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Tests\Bundle;

use App\Delivering\DeliveringBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class DeliveryBundleTest extends TestCase
{
    public function testPrependRegistersDoctrineAndMessengerConfiguration(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerExtension($this->extension('doctrine'));
        $builder->registerExtension($this->extension('framework'));

        $configurator = (new ReflectionClass(ContainerConfigurator::class))->newInstanceWithoutConstructor();

        (new DeliveringBundle())->prependExtension($configurator, $builder);

        $doctrine = $builder->getExtensionConfig('doctrine');
        self::assertCount(1, $doctrine);
        self::assertSame('attribute', $doctrine[0]['orm']['mappings']['Delivering']['type']);
        self::assertFalse($doctrine[0]['orm']['mappings']['Delivering']['is_bundle']);
        self::assertSame('App\\Delivering\\Entity', $doctrine[0]['orm']['mappings']['Delivering']['prefix']);

        $framework = $builder->getExtensionConfig('framework');
        self::assertCount(1, $framework);

        $messenger = $framework[0]['messenger'];
        self::assertSame('delivering_failed', $messenger['failure_transport']);
        self::assertSame(
            '%env(default:delivering.messenger.async_dsn:DELIVERING_MESSENGER_DSN)%',
            $messenger['transports']['delivering_async']['dsn'],
        );
        self::assertSame(
            '%env(default:delivering.messenger.failed_dsn:DELIVERING_MESSENGER_FAILED_DSN)%',
            $messenger['transports']['delivering_failed']['dsn'],
        );
        self::assertSame([
            'max_retries' => 4,
            'delay' => 1000,
            'multiplier' => 2,
            'max_delay' => 30000,
            'jitter' => 0.1,
        ], $messenger['transports']['delivering_async']['retry_strategy']);
        self::assertSame(
            'delivering_async',
            $messenger['routing']['App\\Delivering\\Message\\Command\\Delivery\\DeliverySendSms'],
        );
        self::assertSame(
            'delivering_async',
            $messenger['routing']['App\\Delivering\\Message\\Command\\Delivery\\DeliverySendPush'],
        );
        self::assertSame(
            'delivering_async',
            $messenger['routing']['App\\Delivering\\Message\\Command\\Receipt\\DeliveryProcessReceipt'],
        );
    }

    public function testPrependDoesNothingWhenHostExtensionsAreUnavailable(): void
    {
        $builder = new ContainerBuilder();
        $configurator = (new ReflectionClass(ContainerConfigurator::class))->newInstanceWithoutConstructor();

        (new DeliveringBundle())->prependExtension($configurator, $builder);

        self::assertSame([], $builder->getExtensions());
    }

    private function extension(string $alias): Extension
    {
        return new class ($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
