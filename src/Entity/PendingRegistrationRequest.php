<?php

namespace App\Entity;

use App\Enum\RegistrationRequestStatus;
use App\Enum\RegistrationType;
use App\Repository\PendingRegistrationRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: PendingRegistrationRequestRepository::class)]
#[ORM\Table(name: 'pending_registration_request')]
#[ORM\Index(columns: ['status'], name: 'idx_pending_reg_status')]
#[ORM\Index(columns: ['email'], name: 'idx_pending_reg_email')]
#[ORM\HasLifecycleCallbacks]
class PendingRegistrationRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 100)]
    private string $domain;

    #[ORM\Column(length: 20, enumType: RegistrationType::class)]
    private RegistrationType $registrationType;

    #[ORM\Column(length: 20, enumType: RegistrationRequestStatus::class)]
    private RegistrationRequestStatus $status = RegistrationRequestStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
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
        $this->domain = substr(strrchr($email, '@'), 1);
        return $this;
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

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRegistrationType(): RegistrationType
    {
        return $this->registrationType;
    }

    public function setRegistrationType(RegistrationType $registrationType): static
    {
        $this->registrationType = $registrationType;
        return $this;
    }

    public function getStatus(): RegistrationRequestStatus
    {
        return $this->status;
    }

    public function setStatus(RegistrationRequestStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->firstName, 0, 1) . substr($this->lastName, 0, 1));
    }
}
