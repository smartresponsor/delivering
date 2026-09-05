<?php

declare(strict_types=1);

namespace App\Delivering\Command\Status\Push;

use App\Delivering\Service\Query\Push\DeliveringPushReadinessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'delivering:push:status',
    description: 'Check APNs and FCM push provider readiness without exposing secrets.',
)]
final class DeliveringPushStatusCommand extends Command
{
    public function __construct(private readonly DeliveringPushReadinessService $readiness)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->readiness->status();
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach (['apns' => 'APNs', 'fcm' => 'FCM'] as $key => $label) {
            $provider = $status[$key];
            $rows[] = [
                $label,
                $provider['configured'] ? 'yes' : 'no',
                [] === $provider['appKeys'] ? '-' : implode(', ', $provider['appKeys']),
                [] === $provider['issues'] ? '-' : implode(' ', $provider['issues']),
            ];
        }

        $io->table(['Provider', 'Configured', 'Application keys', 'Issues'], $rows);
        if (!$status['configured']) {
            $io->error('Push delivery is not fully configured. No credential values were printed.');

            return self::FAILURE;
        }

        $io->success('APNs and FCM push delivery are configured.');

        return self::SUCCESS;
    }
}
