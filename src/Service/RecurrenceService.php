<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\User;
use App\Enum\RecurrenceFrequency;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class RecurrenceService
{
    private const MAX_INSTANCES = 52;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Set up recurrence on a task
     */
    public function setupRecurrence(
        Task $task,
        array $rule,
        ?\DateTimeImmutable $endsAt = null,
        ?int $count = null
    ): void {
        // Validate the rule
        if (!isset($rule['frequency'])) {
            throw new \InvalidArgumentException('Recurrence rule must include frequency');
        }

        $frequency = RecurrenceFrequency::tryFrom($rule['frequency']);
        if ($frequency === null) {
            throw new \InvalidArgumentException('Invalid recurrence frequency');
        }

        // Ensure interval is set
        if (!isset($rule['interval'])) {
            $rule['interval'] = 1;
        }

        // Limit count to max 52
        if ($count !== null) {
            $count = min($count, self::MAX_INSTANCES);
        }

        // If no end date specified, auto-set to end of calendar year from task due date
        if ($endsAt === null && $count === null) {
            $baseDate = $task->getDueDate() ?? new \DateTimeImmutable();
            $endsAt = $baseDate->modify('last day of december this year');
        }

        // Generate a new series ID if not already part of a series
        if ($task->getRecurrenceSeriesId() === null) {
            $task->setRecurrenceSeriesId(Uuid::uuid7());
        }

        $task->setRecurrenceRule($rule);
        $task->setRecurrenceEndsAt($endsAt);
        $task->setRecurrenceCountRemaining($count);
    }

    /**
     * Remove recurrence from a task
     */
    public function removeRecurrence(Task $task): void
    {
        $task->setRecurrenceRule(null);
        $task->setRecurrenceEndsAt(null);
        $task->setRecurrenceCountRemaining(null);
        // Keep series ID for historical reference, but clear rule
    }

    /**
     * Create the next instance of a recurring task when the current one is completed
     * Returns the new task instance or null if recurrence has ended
     */
    public function createNextInstance(Task $completedTask, User $user): ?Task
    {
        if (!$completedTask->isRecurring()) {
            return null;
        }

        // Check end conditions
        $endsAt = $completedTask->getRecurrenceEndsAt();
        $countRemaining = $completedTask->getRecurrenceCountRemaining();

        // Check count limit
        if ($countRemaining !== null && $countRemaining <= 0) {
            return null;
        }

        // Calculate the next due date
        $baseDueDate = $completedTask->getDueDate() ?? new \DateTimeImmutable();
        $nextDueDate = $this->calculateNextDate($baseDueDate, $completedTask->getRecurrenceRule());

        // Check if next date exceeds end date
        if ($endsAt !== null && $nextDueDate > $endsAt) {
            return null;
        }

        // Create the new task instance
        $newTask = new Task();
        $newTask->setMilestone($completedTask->getMilestone());
        $newTask->setParent($completedTask->getParent());
        $newTask->setStatus(TaskStatus::TODO);
        $newTask->setPriority($completedTask->getPriority());

        // Copy fields, respecting overrides
        if (!$completedTask->hasRecurrenceOverride('title')) {
            $newTask->setTitle($completedTask->getTitle());
        } else {
            // Find original title from series
            $newTask->setTitle($this->getOriginalFieldValue($completedTask, 'title'));
        }

        if (!$completedTask->hasRecurrenceOverride('description')) {
            $newTask->setDescription($completedTask->getDescription());
        } else {
            $newTask->setDescription($this->getOriginalFieldValue($completedTask, 'description'));
        }

        // Calculate start date relative to due date
        $startDate = null;
        if ($completedTask->getStartDate() !== null && $completedTask->getDueDate() !== null) {
            $interval = $completedTask->getStartDate()->diff($completedTask->getDueDate());
            $startDate = $nextDueDate->sub($interval);
        }
        $newTask->setStartDate($startDate);
        $newTask->setDueDate($nextDueDate);

        // Copy recurrence settings
        $newTask->setRecurrenceRule($completedTask->getRecurrenceRule());
        $newTask->setRecurrenceSeriesId($completedTask->getRecurrenceSeriesId());
        $newTask->setRecurrenceParent($completedTask);
        $newTask->setRecurrenceEndsAt($endsAt);

        // Decrement count if applicable
        if ($countRemaining !== null) {
            $newTask->setRecurrenceCountRemaining($countRemaining - 1);
        }

        // Set position after the completed task
        $newTask->setPosition($completedTask->getPosition() + 1);

        $this->entityManager->persist($newTask);

        // Copy assignees
        foreach ($completedTask->getAssignees() as $assignee) {
            $newAssignee = new TaskAssignee();
            $newAssignee->setTask($newTask);
            $newAssignee->setUser($assignee->getUser());
            $newAssignee->setAssignedBy($user);
            $this->entityManager->persist($newAssignee);
            $newTask->addAssignee($newAssignee);
        }

        // Copy tags
        foreach ($completedTask->getTags() as $tag) {
            $newTask->addTag($tag);
        }

        return $newTask;
    }

    /**
     * Calculate the next occurrence date based on the recurrence rule
     */
    public function calculateNextDate(\DateTimeImmutable $from, array $rule): \DateTimeImmutable
    {
        $frequency = RecurrenceFrequency::tryFrom($rule['frequency'] ?? '');
        if ($frequency === null) {
            throw new \InvalidArgumentException('Invalid frequency in recurrence rule');
        }

        $interval = $rule['interval'] ?? 1;

        return match ($frequency) {
            RecurrenceFrequency::DAILY => $this->calculateNextDaily($from, $interval, $rule['weekdaysOnly'] ?? false),
            RecurrenceFrequency::WEEKLY => $this->calculateNextWeekly($from, $interval, $rule['weekDays'] ?? []),
            RecurrenceFrequency::MONTHLY => $this->calculateNextMonthly($from, $interval, $rule),
            RecurrenceFrequency::QUARTERLY => $from->modify("+{$interval} * 3 months"),
            RecurrenceFrequency::YEARLY => $from->modify("+{$interval} year"),
        };
    }

    private function calculateNextDaily(\DateTimeImmutable $from, int $interval, bool $weekdaysOnly): \DateTimeImmutable
    {
        $next = $from->modify("+{$interval} day");

        if ($weekdaysOnly) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            while (in_array((int) $next->format('w'), [0, 6])) {
                $next = $next->modify('+1 day');
            }
        }

        return $next;
    }

    private function calculateNextWeekly(\DateTimeImmutable $from, int $interval, array $weekDays): \DateTimeImmutable
    {
        if (empty($weekDays)) {
            // No specific days, just add weeks
            return $from->modify("+{$interval} week");
        }

        // Sort week days (ISO: 1=Mon, 7=Sun)
        sort($weekDays);

        // Get current ISO day of week (1=Mon, 7=Sun)
        $currentDayOfWeek = (int) $from->format('N');

        // Find the next day in the same week
        foreach ($weekDays as $day) {
            if ($day > $currentDayOfWeek) {
                return $from->modify('+' . ($day - $currentDayOfWeek) . ' days');
            }
        }

        // If we're past all days in the week, go to the first day of the next interval week
        $daysUntilFirstDay = 7 - $currentDayOfWeek + $weekDays[0] + (($interval - 1) * 7);
        return $from->modify("+{$daysUntilFirstDay} days");
    }

    private function calculateNextMonthly(\DateTimeImmutable $from, int $interval, array $rule): \DateTimeImmutable
    {
        $monthlyType = $rule['monthlyType'] ?? 'dayOfMonth';

        if ($monthlyType === 'dayOfWeek') {
            // e.g., "2nd Tuesday of month" or "last Friday of month"
            $weekOfMonth = $rule['weekOfMonth'] ?? 1;
            $dayOfWeek = $rule['dayOfWeek'] ?? 1;

            return $this->calculateNextMonthlyByDayOfWeek($from, $interval, $weekOfMonth, $dayOfWeek);
        }

        // Default: same day of month
        $dayOfMonth = (int) $from->format('d');
        $next = $from->modify("+{$interval} month");

        // Handle months with fewer days (e.g., Jan 31 -> Feb 28)
        $nextDayOfMonth = (int) $next->format('d');
        if ($nextDayOfMonth < $dayOfMonth) {
            // We overflowed to the next month, go back to the last day of intended month
            $next = $next->modify('last day of previous month');
        }

        return $next;
    }

    private function calculateNextMonthlyByDayOfWeek(
        \DateTimeImmutable $from,
        int $interval,
        int $weekOfMonth,
        int $dayOfWeek
    ): \DateTimeImmutable {
        // Day names for strtotime (ISO: 1=Monday, 7=Sunday)
        $dayNames = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
        $dayName = $dayNames[$dayOfWeek] ?? 'monday';

        // Move to the target month
        $targetMonth = $from->modify("+{$interval} month");
        $year = (int) $targetMonth->format('Y');
        $month = (int) $targetMonth->format('m');

        if ($weekOfMonth === -1) {
            // Last occurrence in month
            $lastDay = new \DateTimeImmutable("last day of {$year}-{$month}");
            return new \DateTimeImmutable("last {$dayName} of {$year}-{$month}");
        }

        // First, second, third, fourth occurrence
        $weekNames = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth'];
        $weekName = $weekNames[$weekOfMonth] ?? 'first';

        return new \DateTimeImmutable("{$weekName} {$dayName} of {$year}-{$month}");
    }

    /**
     * Get the original field value from the first non-overridden task in the series
     */
    private function getOriginalFieldValue(Task $task, string $field): mixed
    {
        // Walk back through the recurrence parents to find the original value
        $current = $task;
        while ($current->getRecurrenceParent() !== null && $current->hasRecurrenceOverride($field)) {
            $current = $current->getRecurrenceParent();
        }

        return match ($field) {
            'title' => $current->getTitle(),
            'description' => $current->getDescription(),
            default => null,
        };
    }

    /**
     * Update all future instances in a series with new field values
     */
    public function updateFutureInstances(Task $task, array $changes): void
    {
        if (!$task->isPartOfRecurrenceSeries()) {
            return;
        }

        $seriesId = $task->getRecurrenceSeriesId();
        $taskDueDate = $task->getDueDate() ?? $task->getCreatedAt();

        // Find all future tasks in the series
        $futureTasks = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.recurrenceSeriesId = :seriesId')
            ->andWhere('t.id != :currentId')
            ->andWhere('t.dueDate >= :currentDue OR t.dueDate IS NULL')
            ->setParameter('seriesId', $seriesId)
            ->setParameter('currentId', $task->getId())
            ->setParameter('currentDue', $taskDueDate)
            ->getQuery()
            ->getResult();

        foreach ($futureTasks as $futureTask) {
            foreach ($changes as $field => $value) {
                // Only update if this future task doesn't have its own override
                if (!$futureTask->hasRecurrenceOverride($field)) {
                    match ($field) {
                        'title' => $futureTask->setTitle($value),
                        'description' => $futureTask->setDescription($value),
                        'priority' => $futureTask->setPriority($value),
                        default => null,
                    };
                }
            }
        }
    }

    /**
     * Delete this instance and all future instances in the series
     */
    public function deleteFutureInstances(Task $task): array
    {
        if (!$task->isPartOfRecurrenceSeries()) {
            return [$task];
        }

        $seriesId = $task->getRecurrenceSeriesId();
        $taskDueDate = $task->getDueDate() ?? $task->getCreatedAt();

        // Find all future tasks in the series (including this one)
        $futureTasks = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.recurrenceSeriesId = :seriesId')
            ->andWhere('t.dueDate >= :currentDue OR (t.dueDate IS NULL AND t.id = :currentId)')
            ->setParameter('seriesId', $seriesId)
            ->setParameter('currentDue', $taskDueDate)
            ->setParameter('currentId', $task->getId())
            ->getQuery()
            ->getResult();

        return $futureTasks;
    }
}
