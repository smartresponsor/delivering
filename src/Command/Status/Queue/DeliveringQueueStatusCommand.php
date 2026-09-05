<?php

# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

namespace App\Delivering\Command\Status\Queue;

use App\Delivering\ServiceInterface\Query\Queue\DeliveringQueueStatusProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports the current Delivering queue state for operators and health checks.
 */
#[AsCommand(
    name: 'delivering:queue:status',
    description: 'Show queued and failed Delivering message counts.',
)]
final class DeliveringQueueStatusCommand extends Command
{
    public function __construct(
        private readonly DeliveringQueueStatusProviderInterface $statusProvider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->statusProvider->status();
        $io = new SymfonyStyle($input, $output);

        $io->table(
            ['Transport', 'Messages'],
            [
                ['delivering_async', (string) $status->queued],
                ['delivering_failed', (string) $status->failed],
            ],
        );
        $io->writeln(sprintf('Observed at: %s', $status->observedAt->format(DATE_ATOM)));

        if (!$status->isHealthy()) {
            $io->warning('Delivering has failed messages requiring operator review.');

            return self::FAILURE;
        }

        $io->success('Delivering queues are healthy.');

        return self::SUCCESS;
    }
}
