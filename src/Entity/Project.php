<?php

namespace App\Entity;

use App\Enum\ProjectStatus;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: ProjectStatus::class)]
    private ProjectStatus $status = ProjectStatus::ACTIVE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedProjects')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublic = true;

    /** @var Collection<int, ProjectMember> */
    #[ORM\OneToMany(targetEntity: ProjectMember::class, mappedBy: 'project', orphanRemoval: true, cascade: ['persist'])]
    private Collection $members;

    /** @var Collection<int, Milestone> */
    #[ORM\OneToMany(targetEntity: Milestone::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $milestones;

    /** @var Collection<int, Activity> */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $activities;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
        $this->members = new ArrayCollection();
        $this->milestones = new ArrayCollection();
        $this->activities = new ArrayCollection();
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

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): static
    {
        $this->status = $status;
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

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;
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

    /** @return Collection<int, ProjectMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(ProjectMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setProject($this);
        }
        return $this;
    }

    public function removeMember(ProjectMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getProject() === $this) {
                $member->setProject($this);
            }
        }
        return $this;
    }

    /** @return Collection<int, Milestone> */
    public function getMilestones(): Collection
    {
        return $this->milestones;
    }

    /** @return Collection<int, Activity> */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getMemberCount(): int
    {
        return $this->members->count();
    }

    public function getMilestoneCount(): int
    {
        return $this->milestones->count();
    }

    public function getTaskCount(): int
    {
        $count = 0;
        foreach ($this->milestones as $milestone) {
            $count += $milestone->getTaskCount();
        }
        return $count;
    }

    public function getCompletedTaskCount(): int
    {
        $count = 0;
        foreach ($this->milestones as $milestone) {
            $count += $milestone->getCompletedTaskCount();
        }
        return $count;
    }

    public function getProgress(): int
    {
        $total = $this->getTaskCount();
        if ($total === 0) {
            return 0;
        }
        return (int) round(($this->getCompletedTaskCount() / $total) * 100);
    }
}
