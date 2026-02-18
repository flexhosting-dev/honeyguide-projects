<?php

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Entity\TaskStatusType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\RecurrenceFrequency;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_task_status', columns: ['status'])]
#[ORM\Index(name: 'idx_task_status_type', columns: ['status_type_id'])]
#[ORM\Index(name: 'idx_task_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_task_due_date', columns: ['due_date'])]
#[ORM\Index(name: 'idx_task_recurrence_series', columns: ['recurrence_series_id'])]
#[Assert\Callback('validateDates')]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Milestone::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Milestone $milestone;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'subtasks')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Task $parent = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::TODO;

    #[ORM\ManyToOne(targetEntity: TaskStatusType::class)]
    #[ORM\JoinColumn(name: 'status_type_id', nullable: true, onDelete: 'SET NULL')]
    private ?TaskStatusType $statusType = null;

    #[ORM\Column(type: 'string', enumType: TaskPriority::class)]
    private TaskPriority $priority = TaskPriority::NONE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'parent', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $subtasks;

    /** @var Collection<int, TaskAssignee> */
    #[ORM\OneToMany(targetEntity: TaskAssignee::class, mappedBy: 'task', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $assignees;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'task', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    /** @var Collection<int, TaskChecklist> */
    #[ORM\OneToMany(targetEntity: TaskChecklist::class, mappedBy: 'task', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $checklistItems;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    private Collection $tags;

    /** Recurrence rule storing frequency, interval, weekDays, etc. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recurrenceRule = null;

    /** UUID linking all instances in the same recurrence series */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $recurrenceSeriesId = null;

    /** Points to the task that spawned this recurring instance */
    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'recurrence_parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $recurrenceParent = null;

    /** Optional end date for recurrence */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $recurrenceEndsAt = null;

    /** Optional remaining occurrences (max 52) */
    #[ORM\Column(nullable: true)]
    private ?int $recurrenceCountRemaining = null;

    /** Tracks which fields were edited for "this instance only" */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recurrenceOverrides = null;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
        $this->subtasks = new ArrayCollection();
        $this->assignees = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->checklistItems = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getMilestone(): Milestone
    {
        return $this->milestone;
    }

    public function setMilestone(Milestone $milestone): static
    {
        $this->milestone = $milestone;
        return $this;
    }

    public function getParent(): ?Task
    {
        return $this->parent;
    }

    public function setParent(?Task $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusType(): ?TaskStatusType
    {
        return $this->statusType;
    }

    public function setStatusType(?TaskStatusType $statusType): static
    {
        $this->statusType = $statusType;
        // Also update the legacy enum for backwards compatibility
        if ($statusType !== null) {
            $enumStatus = TaskStatus::tryFrom($statusType->getSlug());
            if ($enumStatus !== null) {
                $this->status = $enumStatus;
            }
        }
        return $this;
    }

    public function getEffectiveStatusLabel(): string
    {
        if ($this->statusType !== null) {
            return $this->statusType->getName();
        }
        return $this->status->label();
    }

    public function getEffectiveStatusValue(): string
    {
        if ($this->statusType !== null) {
            return $this->statusType->getSlug();
        }
        return $this->status->value;
    }

    public function getEffectiveStatusColor(): string
    {
        if ($this->statusType !== null) {
            return $this->statusType->getColor();
        }
        // Return default colors for legacy enum statuses
        return match($this->status) {
            TaskStatus::TODO => '#6B7280',
            TaskStatus::IN_PROGRESS => '#3B82F6',
            TaskStatus::IN_REVIEW => '#F59E0B',
            TaskStatus::COMPLETED => '#10B981',
        };
    }

    public function isEffectivelyCompleted(): bool
    {
        if ($this->statusType !== null) {
            return $this->statusType->isClosed();
        }
        return $this->status === TaskStatus::COMPLETED;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Task> */
    public function getSubtasks(): Collection
    {
        return $this->subtasks;
    }

    public function addSubtask(Task $subtask): static
    {
        if (!$this->subtasks->contains($subtask)) {
            $this->subtasks->add($subtask);
            $subtask->setParent($this);
        }
        return $this;
    }

    public function removeSubtask(Task $subtask): static
    {
        if ($this->subtasks->removeElement($subtask)) {
            if ($subtask->getParent() === $this) {
                $subtask->setParent(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, TaskAssignee> */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    public function addAssignee(TaskAssignee $assignee): static
    {
        if (!$this->assignees->contains($assignee)) {
            $this->assignees->add($assignee);
            $assignee->setTask($this);
        }
        return $this;
    }

    public function removeAssignee(TaskAssignee $assignee): static
    {
        if ($this->assignees->removeElement($assignee)) {
            if ($assignee->getTask() === $this) {
                $assignee->setTask($this);
            }
        }
        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, TaskChecklist> */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function getChecklistCount(): int
    {
        return $this->checklistItems->count();
    }

    public function getCompletedChecklistCount(): int
    {
        return $this->checklistItems->filter(fn(TaskChecklist $item) => $item->isCompleted())->count();
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function hasTag(Tag $tag): bool
    {
        return $this->tags->contains($tag);
    }

    public function getSubtaskCount(): int
    {
        return $this->subtasks->count();
    }

    public function getCompletedSubtaskCount(): int
    {
        return $this->subtasks->filter(fn(Task $t) => $t->isEffectivelyCompleted())->count();
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        while ($current !== null) {
            $depth++;
            $current = $current->getParent();
        }
        return $depth;
    }

    /**
     * Returns parent titles from immediate parent up to root, joined by " in ".
     * E.g. "Parent Title in Grandparent Title"
     */
    public function getParentChain(): ?string
    {
        if ($this->parent === null) {
            return null;
        }
        $parts = [];
        $current = $this->parent;
        while ($current !== null) {
            $parts[] = $current->getTitle();
            $current = $current->getParent();
        }
        return implode(' in ', $parts);
    }

    public function getCommentCount(): int
    {
        return $this->comments->count();
    }

    public function getProject(): Project
    {
        return $this->milestone->getProject();
    }

    public function isOverdue(): bool
    {
        if ($this->dueDate === null) {
            return false;
        }
        if ($this->isEffectivelyCompleted()) {
            return false;
        }
        return $this->dueDate < new \DateTimeImmutable('today');
    }

    public function validateDates(ExecutionContextInterface $context): void
    {
        // Only validate if both dates are set
        if ($this->startDate === null || $this->dueDate === null) {
            return;
        }

        // Due date must not be earlier than start date
        if ($this->dueDate < $this->startDate) {
            $context->buildViolation('Due date cannot be earlier than start date.')
                ->atPath('dueDate')
                ->addViolation();
        }
    }

    // Recurrence getters and setters

    public function getRecurrenceRule(): ?array
    {
        return $this->recurrenceRule;
    }

    public function setRecurrenceRule(?array $recurrenceRule): static
    {
        $this->recurrenceRule = $recurrenceRule;
        return $this;
    }

    public function getRecurrenceSeriesId(): ?UuidInterface
    {
        return $this->recurrenceSeriesId;
    }

    public function setRecurrenceSeriesId(?UuidInterface $recurrenceSeriesId): static
    {
        $this->recurrenceSeriesId = $recurrenceSeriesId;
        return $this;
    }

    public function getRecurrenceParent(): ?Task
    {
        return $this->recurrenceParent;
    }

    public function setRecurrenceParent(?Task $recurrenceParent): static
    {
        $this->recurrenceParent = $recurrenceParent;
        return $this;
    }

    public function getRecurrenceEndsAt(): ?\DateTimeImmutable
    {
        return $this->recurrenceEndsAt;
    }

    public function setRecurrenceEndsAt(?\DateTimeImmutable $recurrenceEndsAt): static
    {
        $this->recurrenceEndsAt = $recurrenceEndsAt;
        return $this;
    }

    public function getRecurrenceCountRemaining(): ?int
    {
        return $this->recurrenceCountRemaining;
    }

    public function setRecurrenceCountRemaining(?int $recurrenceCountRemaining): static
    {
        $this->recurrenceCountRemaining = $recurrenceCountRemaining;
        return $this;
    }

    public function getRecurrenceOverrides(): ?array
    {
        return $this->recurrenceOverrides;
    }

    public function setRecurrenceOverrides(?array $recurrenceOverrides): static
    {
        $this->recurrenceOverrides = $recurrenceOverrides;
        return $this;
    }

    // Recurrence helper methods

    /**
     * Check if this task has a recurrence rule
     */
    public function isRecurring(): bool
    {
        return $this->recurrenceRule !== null && !empty($this->recurrenceRule);
    }

    /**
     * Check if this task is part of a recurrence series
     */
    public function isPartOfRecurrenceSeries(): bool
    {
        return $this->recurrenceSeriesId !== null;
    }

    /**
     * Get human-readable description of the recurrence rule
     */
    public function getRecurrenceDescription(): string
    {
        if (!$this->isRecurring()) {
            return 'Does not repeat';
        }

        $rule = $this->recurrenceRule;
        $frequency = RecurrenceFrequency::tryFrom($rule['frequency'] ?? '');
        if ($frequency === null) {
            return 'Does not repeat';
        }

        $interval = $rule['interval'] ?? 1;

        // Handle daily with weekdays only
        if ($frequency === RecurrenceFrequency::DAILY && ($rule['weekdaysOnly'] ?? false)) {
            return 'Every weekday (Mon-Fri)';
        }

        // Handle weekly with specific days
        if ($frequency === RecurrenceFrequency::WEEKLY && !empty($rule['weekDays'])) {
            $dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $days = array_map(fn($d) => $dayNames[$d] ?? '', $rule['weekDays']);
            $daysStr = implode(', ', $days);
            if ($interval === 1) {
                return "Weekly on $daysStr";
            }
            return "Every $interval weeks on $daysStr";
        }

        // Handle monthly by day of week
        if ($frequency === RecurrenceFrequency::MONTHLY && ($rule['monthlyType'] ?? '') === 'dayOfWeek') {
            $dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $weekNames = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', -1 => 'last'];
            $weekOfMonth = $rule['weekOfMonth'] ?? 1;
            $dayOfWeek = $rule['dayOfWeek'] ?? 1;
            $weekStr = $weekNames[$weekOfMonth] ?? 'first';
            $dayStr = $dayNames[$dayOfWeek] ?? 'Monday';
            if ($interval === 1) {
                return "Monthly on the $weekStr $dayStr";
            }
            return "Every $interval months on the $weekStr $dayStr";
        }

        // Simple interval description
        if ($interval === 1) {
            return $frequency->label();
        }
        return "Every $interval " . $frequency->pluralLabel();
    }

    /**
     * Check if a specific field has been overridden for this instance
     */
    public function hasRecurrenceOverride(string $field): bool
    {
        return isset($this->recurrenceOverrides[$field]) && $this->recurrenceOverrides[$field] === true;
    }

    /**
     * Mark a field as overridden for this instance only
     */
    public function addRecurrenceOverride(string $field): void
    {
        if ($this->recurrenceOverrides === null) {
            $this->recurrenceOverrides = [];
        }
        $this->recurrenceOverrides[$field] = true;
    }

    /**
     * Remove an override for a field
     */
    public function removeRecurrenceOverride(string $field): void
    {
        if ($this->recurrenceOverrides !== null) {
            unset($this->recurrenceOverrides[$field]);
            if (empty($this->recurrenceOverrides)) {
                $this->recurrenceOverrides = null;
            }
        }
    }

    /**
     * Get the recurrence frequency enum
     */
    public function getRecurrenceFrequency(): ?RecurrenceFrequency
    {
        if (!$this->isRecurring()) {
            return null;
        }
        return RecurrenceFrequency::tryFrom($this->recurrenceRule['frequency'] ?? '');
    }
}
