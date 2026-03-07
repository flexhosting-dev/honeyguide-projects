<?php

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
#[ORM\Index(columns: ['user_id'], name: 'idx_push_subscription_user')]
#[ORM\Index(columns: ['last_used_at'], name: 'idx_push_subscription_last_used')]
#[ORM\UniqueConstraint(name: 'uniq_user_endpoint', columns: ['user_id', 'endpoint_hash'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $endpoint = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private ?string $endpointHash = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $p256dhKey = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $authToken = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid7();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);
        return $this;
    }

    public function getEndpointHash(): ?string
    {
        return $this->endpointHash;
    }

    public function getP256dhKey(): ?string
    {
        return $this->p256dhKey;
    }

    public function setP256dhKey(string $p256dhKey): static
    {
        $this->p256dhKey = $p256dhKey;
        return $this;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function setAuthToken(string $authToken): static
    {
        $this->authToken = $authToken;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function updateLastUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }
}
