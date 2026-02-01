<?php

namespace App\Command;

use App\Enum\NotificationType;
use App\Repository\TaskRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-due-date-notifications',
    description: 'Send notifications for tasks due soon or overdue',
)]
class SendDueDateNotificationsCommand extends Command
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now = new \DateTimeImmutable();
        $tomorrow = $now->modify('+24 hours');

        // Tasks due within 24 hours
        $dueSoonTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.dueDate BETWEEN :now AND :tomorrow')
            ->andWhere('t.status != :completed')
            ->setParameter('now', $now)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getResult();

        $dueSoonCount = 0;
        foreach ($dueSoonTasks as $task) {
            foreach ($task->getAssignees() as $assignee) {
                $this->notificationService->notify(
                    $assignee->getUser(),
                    NotificationType::TASK_DUE_SOON,
                    null,
                    'task',
                    $task->getId(),
                    $task->getTitle(),
                    ['dueDate' => $task->getDueDate()->format('Y-m-d')],
                );
                $dueSoonCount++;
            }
        }

        // Overdue tasks
        $overdueTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('now', $now)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getResult();

        $overdueCount = 0;
        foreach ($overdueTasks as $task) {
            foreach ($task->getAssignees() as $assignee) {
                $this->notificationService->notify(
                    $assignee->getUser(),
                    NotificationType::TASK_OVERDUE,
                    null,
                    'task',
                    $task->getId(),
                    $task->getTitle(),
                    ['dueDate' => $task->getDueDate()->format('Y-m-d')],
                );
                $overdueCount++;
            }
        }

        $this->entityManager->flush();

        $io->success("Sent $dueSoonCount due-soon and $overdueCount overdue notifications.");

        return Command::SUCCESS;
    }
}
