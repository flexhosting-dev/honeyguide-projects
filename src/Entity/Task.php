<?php

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_task_status', columns: ['status'])]
#[ORM\Index(name: 'idx_task_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_task_due_date', columns: ['due_date'])]
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
        if ($this->status === TaskStatus::COMPLETED) {
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
}
