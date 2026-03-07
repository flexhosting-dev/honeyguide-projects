<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ramsey\Uuid\Uuid;

#[AsCommand(
    name: 'app:send-test-notification',
    description: 'Send a test notification to a user',
)]
class SendTestNotificationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Notification type', 'mentioned')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'Custom message', 'Test Notification')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $typeValue = $input->getOption('type');
        $message = $input->getOption('message');

        // Find user
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("User with email '{$email}' not found");
            return Command::FAILURE;
        }

        // Parse notification type
        $type = null;
        foreach (NotificationType::cases() as $case) {
            if ($case->value === $typeValue) {
                $type = $case;
                break;
            }
        }

        if (!$type) {
            $io->error("Invalid notification type '{$typeValue}'");
            $io->note('Available types: ' . implode(', ', array_map(fn($t) => $t->value, NotificationType::cases())));
            return Command::FAILURE;
        }

        $io->title('Sending Test Notification');
        $io->text([
            "Recipient: {$user->getFullName()} ({$user->getEmail()})",
            "Type: {$type->label()} ({$type->value})",
            "Message: {$message}",
        ]);

        // Check user preferences
        $io->section('User Notification Preferences for this type:');
        $preferences = [
            'In-App' => $user->shouldReceiveNotification($type, 'in_app') ? '✅ Enabled' : '❌ Disabled',
            'Email' => $user->shouldReceiveNotification($type, 'email') ? '✅ Enabled' : '❌ Disabled',
            'Push' => $user->shouldReceiveNotification($type, 'push') ? '✅ Enabled' : '❌ Disabled',
        ];
        $io->table(['Channel', 'Status'], array_map(fn($k, $v) => [$k, $v], array_keys($preferences), $preferences));

        try {
            // Find a real project to use for the test notification URL
            $project = $this->entityManager->createQueryBuilder()
                ->select('p')
                ->from('App\Entity\Project', 'p')
                ->join('p.members', 'm')
                ->where('m.user = :user')
                ->setParameter('user', $user)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // If user has projects, use real project ID; otherwise use 'user' entity type for safer URL
            if ($project) {
                $entityType = 'project';
                $entityId = $project->getId();
                $data = ['test' => true];
            } else {
                $entityType = 'user';
                $entityId = Uuid::uuid7();
                $data = ['test' => true];
            }

            // Send notification (will send to all enabled channels)
            $notification = $this->notificationService->notify(
                recipient: $user,
                type: $type,
                actor: null,
                entityType: $entityType,
                entityId: $entityId,
                entityName: $message,
                data: $data
            );

            $this->entityManager->flush();

            if ($notification) {
                $io->success('Test notification sent successfully!');
                $io->note([
                    'In-App notification created in database',
                    'Email sent (if enabled)',
                    'Push notification sent to all devices (if enabled and subscribed)',
                ]);
            } else {
                $io->warning('Notification was not created (user may have disabled in-app notifications or it was a self-notification)');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send notification: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
