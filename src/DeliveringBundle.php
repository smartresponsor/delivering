<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DeliveringBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'Delivering' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => __DIR__.'/Entity',
                            'prefix' => 'App\\Delivering\\Entity',
                            'alias' => 'Delivering',
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'messenger' => [
                    'failure_transport' => 'delivering_failed',
                    'transports' => [
                        'delivering_async' => [
                            'dsn' => '%env(default:delivering.messenger.async_dsn:DELIVERING_MESSENGER_DSN)%',
                            'retry_strategy' => [
                                'max_retries' => 4,
                                'delay' => 1000,
                                'multiplier' => 2,
                                'max_delay' => 30000,
                                'jitter' => 0.1,
                            ],
                        ],
                        'delivering_failed' => [
                            'dsn' => '%env(default:delivering.messenger.failed_dsn:DELIVERING_MESSENGER_FAILED_DSN)%',
                        ],
                    ],
                    'routing' => [
                        'App\\Delivering\\Message\\Command\\Delivery\\DeliveringSendSms' => 'delivering_async',
                        'App\\Delivering\\Message\\Command\\Delivery\\DeliveringSendPush' => 'delivering_async',
                        'App\\Delivering\\Message\\Command\\Receipt\\DeliveringProcessReceipt' => 'delivering_async',
                    ],
                ],
            ]);
        }
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/component/delivering_services.yaml');
    }
}
