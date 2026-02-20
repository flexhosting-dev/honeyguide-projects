<?php

namespace App\Entity;

use App\Enum\MilestoneStatus;
use App\Enum\TaskStatus;
use App\Repository\MilestoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MilestoneRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Milestone
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'milestones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'string', enumType: MilestoneStatus::class)]
    private MilestoneStatus $status = MilestoneStatus::OPEN;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'milestone', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tasks;

    /** @var Collection<int, MilestoneTarget> */
    #[ORM\OneToMany(targetEntity: MilestoneTarget::class, mappedBy: 'milestone', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $targets;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
        $this->tasks = new ArrayCollection();
        $this->targets = new ArrayCollection();
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

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getStatus(): MilestoneStatus
    {
        return $this->status;
    }

    public function setStatus(MilestoneStatus $status): static
    {
        $this->status = $status;
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
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
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function getTaskCount(): int
    {
        return $this->tasks->count();
    }

    public function getCompletedTaskCount(): int
    {
        return $this->tasks->filter(fn(Task $task) => $task->getStatus() === TaskStatus::COMPLETED)->count();
    }

    public function getProgress(): int
    {
        $total = $this->tasks->count();
        if ($total === 0) {
            return 0;
        }
        return (int) round(($this->getCompletedTaskCount() / $total) * 100);
    }

    /** @return Collection<int, MilestoneTarget> */
    public function getTargets(): Collection
    {
        return $this->targets;
    }

    public function addTarget(MilestoneTarget $target): static
    {
        if (!$this->targets->contains($target)) {
            $this->targets->add($target);
            $target->setMilestone($this);
        }
        return $this;
    }

    public function removeTarget(MilestoneTarget $target): static
    {
        $this->targets->removeElement($target);
        return $this;
    }

    public function getCompletedTargetCount(): int
    {
        return $this->targets->filter(fn(MilestoneTarget $t) => $t->isCompleted())->count();
    }
}
