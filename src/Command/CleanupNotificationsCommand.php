<?php

namespace App\Command;

use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-notifications',
    description: 'Delete old read and expired notifications',
)]
class CleanupNotificationsCommand extends Command
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $ninetyDaysAgo = new \DateTimeImmutable('-90 days');

        $readDeleted = $this->notificationRepository->deleteReadOlderThan($thirtyDaysAgo);
        $io->info("Deleted $readDeleted read notifications older than 30 days.");

        $allDeleted = $this->notificationRepository->deleteAllOlderThan($ninetyDaysAgo);
        $io->info("Deleted $allDeleted notifications older than 90 days.");

        $io->success('Notification cleanup complete.');

        return Command::SUCCESS;
    }
}
