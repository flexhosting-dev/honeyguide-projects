<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\NotificationEmailService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-notification-emails',
    description: 'Send test emails for all notification types',
)]
class TestNotificationEmailsCommand extends Command
{
    public function __construct(
        private readonly NotificationEmailService $emailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address to send test emails to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Testing Notification Emails');
        $io->text("Sending test emails to: $email");

        // Create mock recipient
        $recipient = new User();
        $recipient->setEmail($email);
        $recipient->setFirstName('Sam');
        $recipient->setLastName('Test');
        // Force all email notifications to be enabled
        $recipient->setNotificationPreferences([]);

        // Create mock actor
        $actor = new User();
        $actor->setEmail('actor@example.com');
        $actor->setFirstName('John');
        $actor->setLastName('Doe');

        $projectId = Uuid::uuid4()->toString();
        $taskId = Uuid::uuid4()->toString();

        $testCases = [
            [
                'type' => NotificationType::TASK_ASSIGNED,
                'entityType' => 'task',
                'entityName' => 'Fix login validation bug',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'projectName' => 'Website Redesign',
                ],
            ],
            [
                'type' => NotificationType::TASK_UNASSIGNED,
                'entityType' => 'task',
                'entityName' => 'Update documentation',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                ],
            ],
            [
                'type' => NotificationType::TASK_COMPLETED,
                'entityType' => 'task',
                'entityName' => 'Design homepage mockup',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                ],
            ],
            [
                'type' => NotificationType::TASK_DUE_SOON,
                'entityType' => 'task',
                'entityName' => 'Submit quarterly report',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'dueDate' => 'February 18, 2026',
                ],
                'actor' => null,
            ],
            [
                'type' => NotificationType::TASK_OVERDUE,
                'entityType' => 'task',
                'entityName' => 'Review pull request',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'dueDate' => 'February 15, 2026',
                ],
                'actor' => null,
            ],
            [
                'type' => NotificationType::TASK_STATUS_CHANGED,
                'entityType' => 'task',
                'entityName' => 'Implement search feature',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'oldStatus' => 'To Do',
                    'newStatus' => 'In Progress',
                ],
            ],
            [
                'type' => NotificationType::COMMENT_ADDED,
                'entityType' => 'task',
                'entityName' => 'API integration task',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'commentPreview' => 'I\'ve finished the initial implementation. Can you review the changes when you get a chance?',
                ],
            ],
            [
                'type' => NotificationType::COMMENT_REPLY,
                'entityType' => 'task',
                'entityName' => 'Database migration task',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'commentPreview' => 'Good point! I\'ll update the migration script to handle that edge case.',
                ],
            ],
            [
                'type' => NotificationType::MENTIONED,
                'entityType' => 'task',
                'entityName' => 'Sprint planning discussion',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'commentPreview' => '@Sam can you take a look at this? I think we need your input on the architecture.',
                ],
            ],
            [
                'type' => NotificationType::PROJECT_INVITED,
                'entityType' => 'project',
                'entityName' => 'Mobile App Development',
                'data' => [
                    'projectId' => $projectId,
                    'role' => 'Developer',
                ],
            ],
            [
                'type' => NotificationType::PROJECT_REMOVED,
                'entityType' => 'project',
                'entityName' => 'Old Project Archive',
                'data' => [],
                'actor' => null,
            ],
            [
                'type' => NotificationType::MILESTONE_DUE,
                'entityType' => 'milestone',
                'entityName' => 'Phase 1 Completion',
                'data' => [
                    'projectId' => $projectId,
                    'dueDate' => 'February 28, 2026',
                ],
                'actor' => null,
            ],
            [
                'type' => NotificationType::ATTACHMENT_ADDED,
                'entityType' => 'task',
                'entityName' => 'Design assets task',
                'data' => [
                    'projectId' => $projectId,
                    'taskId' => $taskId,
                    'fileName' => 'homepage-mockup-v2.figma',
                ],
            ],
            [
                'type' => NotificationType::REGISTRATION_REQUEST,
                'entityType' => 'registration_request',
                'entityName' => 'Jane Smith',
                'data' => [
                    'email' => 'jane.smith@example.com',
                    'domain' => 'example.com',
                    'type' => 'Google OAuth',
                ],
                'actor' => null,
            ],
        ];

        $sent = 0;
        $failed = 0;

        foreach ($testCases as $test) {
            $type = $test['type'];
            $testActor = $test['actor'] ?? $actor;

            try {
                $this->emailService->sendNotificationEmail(
                    $recipient,
                    $type,
                    $testActor,
                    $test['entityType'],
                    Uuid::uuid4(),
                    $test['entityName'],
                    $test['data'],
                );
                $io->writeln("  <info>✓</info> Sent: {$type->value}");
                $sent++;
            } catch (\Exception $e) {
                $io->writeln("  <error>✗</error> Failed: {$type->value} - {$e->getMessage()}");
                $failed++;
            }
        }

        $io->newLine();
        $io->success("Sent $sent emails, $failed failed");

        return Command::SUCCESS;
    }
}
