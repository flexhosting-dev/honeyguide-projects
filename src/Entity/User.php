<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\ManyToOne(targetEntity: Role::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Role $portalRole = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private string $lastName;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: 'json')]
    private array $hiddenRecentProjectIds = [];

    #[ORM\Column(type: 'json')]
    private array $favouriteProjectIds = [];

    #[ORM\Column(type: 'json')]
    private array $recentProjectIds = [];

    #[ORM\Column(length: 50, options: ['default' => 'gradient'])]
    private string $uiTheme = 'gradient';

    #[ORM\Column(type: 'json')]
    private array $notificationPreferences = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'owner')]
    private Collection $ownedProjects;

    /** @var Collection<int, ProjectMember> */
    #[ORM\OneToMany(targetEntity: ProjectMember::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $projectMemberships;

    /** @var Collection<int, TaskAssignee> */
    #[ORM\OneToMany(targetEntity: TaskAssignee::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $taskAssignments;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, Activity> */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'user')]
    private Collection $activities;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
        $this->ownedProjects = new ArrayCollection();
        $this->projectMemberships = new ArrayCollection();
        $this->taskAssignments = new ArrayCollection();
        $this->comments = new ArrayCollection();
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getPortalRole(): ?Role
    {
        return $this->portalRole;
    }

    public function setPortalRole(?Role $portalRole): static
    {
        $this->portalRole = $portalRole;
        return $this;
    }

    public function hasPortalPermission(string $permission): bool
    {
        return $this->portalRole !== null && $this->portalRole->hasPermission($permission);
    }

    public function isPortalSuperAdmin(): bool
    {
        return $this->portalRole !== null && $this->portalRole->getSlug() === 'portal-super-admin';
    }

    public function isPortalAdmin(): bool
    {
        return $this->portalRole !== null && in_array($this->portalRole->getSlug(), ['portal-super-admin', 'portal-admin'], true);
    }

    public function eraseCredentials(): void
    {
        // Clear any temporary, sensitive data
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
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

    /** @return Collection<int, Project> */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    /** @return Collection<int, ProjectMember> */
    public function getProjectMemberships(): Collection
    {
        return $this->projectMemberships;
    }

    /** @return Collection<int, TaskAssignee> */
    public function getTaskAssignments(): Collection
    {
        return $this->taskAssignments;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, Activity> */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;
        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->firstName, 0, 1) . substr($this->lastName, 0, 1));
    }

    public function getHiddenRecentProjectIds(): array
    {
        return $this->hiddenRecentProjectIds;
    }

    public function setHiddenRecentProjectIds(array $hiddenRecentProjectIds): static
    {
        $this->hiddenRecentProjectIds = $hiddenRecentProjectIds;
        return $this;
    }

    public function hideRecentProject(string $projectId): static
    {
        // Remove from recent projects list directly
        $this->removeRecentProject($projectId);
        return $this;
    }

    public function unhideRecentProject(string $projectId): static
    {
        $this->hiddenRecentProjectIds = array_values(array_filter(
            $this->hiddenRecentProjectIds,
            fn($id) => $id !== $projectId
        ));
        return $this;
    }

    public function isRecentProjectHidden(string $projectId): bool
    {
        return in_array($projectId, $this->hiddenRecentProjectIds, true);
    }

    public function getRecentProjectIds(): array
    {
        return $this->recentProjectIds;
    }

    public function setRecentProjectIds(array $recentProjectIds): static
    {
        $this->recentProjectIds = $recentProjectIds;
        return $this;
    }

    /**
     * Record a project visit. Moves the project to the front of the list,
     * keeping at most 4 entries.
     */
    public function recordProjectVisit(string $projectId): static
    {
        // Remove if already in list
        $this->recentProjectIds = array_values(array_filter(
            $this->recentProjectIds,
            fn($id) => $id !== $projectId
        ));
        // Add to front
        array_unshift($this->recentProjectIds, $projectId);
        // Keep max 4
        $this->recentProjectIds = array_slice($this->recentProjectIds, 0, 4);
        return $this;
    }

    /**
     * Remove a project from the recent projects list.
     */
    public function removeRecentProject(string $projectId): static
    {
        $this->recentProjectIds = array_values(array_filter(
            $this->recentProjectIds,
            fn($id) => $id !== $projectId
        ));
        return $this;
    }

    public function getUiTheme(): string
    {
        return $this->uiTheme;
    }

    public function setUiTheme(string $uiTheme): static
    {
        $this->uiTheme = $uiTheme;
        return $this;
    }

    public function getFavouriteProjectIds(): array
    {
        return $this->favouriteProjectIds;
    }

    public function setFavouriteProjectIds(array $favouriteProjectIds): static
    {
        $this->favouriteProjectIds = $favouriteProjectIds;
        return $this;
    }

    public function addFavouriteProject(string $projectId): static
    {
        if (!in_array($projectId, $this->favouriteProjectIds, true)) {
            $this->favouriteProjectIds[] = $projectId;
        }
        return $this;
    }

    public function removeFavouriteProject(string $projectId): static
    {
        $this->favouriteProjectIds = array_values(array_filter(
            $this->favouriteProjectIds,
            fn($id) => $id !== $projectId
        ));
        return $this;
    }

    public function isFavouriteProject(string $projectId): bool
    {
        return in_array($projectId, $this->favouriteProjectIds, true);
    }

    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    public function setNotificationPreferences(array $notificationPreferences): static
    {
        $this->notificationPreferences = $notificationPreferences;
        return $this;
    }

    public function shouldReceiveNotification(\App\Enum\NotificationType $type, string $channel): bool
    {
        $prefs = $this->notificationPreferences;
        if (isset($prefs[$type->value][$channel])) {
            return (bool) $prefs[$type->value][$channel];
        }
        // Default: use the enum defaults
        return $channel === 'in_app' ? $type->defaultInApp() : $type->defaultEmail();
    }
}
