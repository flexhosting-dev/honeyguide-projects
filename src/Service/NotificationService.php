<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationEmailService $emailService,
    ) {
    }

    public function notify(
        User $recipient,
        NotificationType $type,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName = null,
        ?array $data = null,
    ): ?Notification {
        // Don't notify yourself
        if ($actor !== null && $actor->getId()->equals($recipient->getId())) {
            return null;
        }

        // Check user preferences
        if (!$recipient->shouldReceiveNotification($type, 'in_app')) {
            return null;
        }

        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setActor($actor);
        $notification->setType($type);
        $notification->setEntityType($entityType);
        $notification->setEntityId($entityId);
        $notification->setEntityName($entityName);
        $notification->setData($data);

        $this->entityManager->persist($notification);

        // Send email notification if user has email notifications enabled
        $this->emailService->sendNotificationEmail(
            $recipient,
            $type,
            $actor,
            $entityType,
            $entityId,
            $entityName,
            $data,
        );

        return $notification;
    }

    /**
     * @param User[] $recipients
     */
    public function notifyMultiple(
        array $recipients,
        NotificationType $type,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName = null,
        ?array $data = null,
    ): void {
        foreach ($recipients as $recipient) {
            $this->notify($recipient, $type, $actor, $entityType, $entityId, $entityName, $data);
        }
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->setReadAt(new \DateTimeImmutable());
    }

    public function markAllAsRead(User $user): void
    {
        $this->entityManager
            ->getRepository(Notification::class)
            ->markAllReadForUser($user);
    }
}
