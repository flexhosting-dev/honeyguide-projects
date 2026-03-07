<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-push-subscriptions',
    description: 'Remove stale push subscriptions that have not been used recently',
)]
class CleanupPushSubscriptionsCommand extends Command
{
    public function __construct(
        private PushNotificationService $pushService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Number of days of inactivity before removing subscription',
            30
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title('Cleaning up stale push subscriptions');
        $io->text("Removing subscriptions not used in the last {$days} days...");

        try {
            $count = $this->pushService->cleanupStaleSubscriptions($days);

            if ($count > 0) {
                $io->success("Removed {$count} stale push subscription(s)");
            } else {
                $io->info('No stale subscriptions found');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to cleanup subscriptions: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
