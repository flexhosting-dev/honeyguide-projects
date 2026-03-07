<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Ramsey\Uuid\UuidInterface;

class PushNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PushSubscriptionRepository $subscriptionRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private string $vapidPublicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        private string $vapidPrivateKey,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private string $vapidSubject,
    ) {
    }

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Send a push notification to a user
     */
    public function sendPushNotification(
        User $recipient,
        NotificationType $type,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName,
        ?array $data = null
    ): void {
        if (!$recipient->shouldReceiveNotification($type, 'push')) {
            return;
        }

        $subscriptions = $this->subscriptionRepository->findByUser($recipient);
        if (empty($subscriptions)) {
            return;
        }

        try {
            $payload = $this->getNotificationPayload($type, $actor, $entityType, $entityId, $entityName, $data);

            $auth = [
                'VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ],
            ];

            $webPush = new WebPush($auth);

            foreach ($subscriptions as $pushSubscription) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $pushSubscription->getEndpoint(),
                        'keys' => [
                            'p256dh' => $pushSubscription->getP256dhKey(),
                            'auth' => $pushSubscription->getAuthToken(),
                        ],
                    ]);

                    $webPush->queueNotification($subscription, json_encode($payload));
                } catch (\Exception $e) {
                    $this->logger->error('Failed to queue push notification', [
                        'subscription_id' => $pushSubscription->getId()->toString(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $this->updateSubscriptionLastUsed($recipient, $endpoint);
                } else {
                    $this->handlePushError($recipient, $endpoint, $report);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to send push notifications', [
                'recipient' => $recipient->getEmail(),
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Subscribe a user to push notifications
     */
    public function subscribe(User $user, array $subscriptionData): PushSubscription
    {
        $endpoint = $subscriptionData['endpoint'] ?? null;
        $keys = $subscriptionData['keys'] ?? [];

        if (!$endpoint || !isset($keys['p256dh']) || !isset($keys['auth'])) {
            throw new \InvalidArgumentException('Invalid subscription data');
        }

        $existing = $this->subscriptionRepository->findByEndpoint($user, $endpoint);
        if ($existing) {
            $existing->setP256dhKey($keys['p256dh']);
            $existing->setAuthToken($keys['auth']);
            $existing->updateLastUsed();
            $this->entityManager->flush();
            return $existing;
        }

        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint($endpoint);
        $subscription->setP256dhKey($keys['p256dh']);
        $subscription->setAuthToken($keys['auth']);
        $subscription->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Unsubscribe a user from push notifications
     */
    public function unsubscribe(User $user, string $endpoint): void
    {
        $subscription = $this->subscriptionRepository->findByEndpoint($user, $endpoint);
        if ($subscription) {
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
        }
    }

    /**
     * Check if user has active subscriptions
     */
    public function hasActiveSubscriptions(User $user): bool
    {
        return count($this->subscriptionRepository->findByUser($user)) > 0;
    }

    /**
     * Clean up stale subscriptions
     */
    public function cleanupStaleSubscriptions(int $days = 30): int
    {
        return $this->subscriptionRepository->deleteStaleSubscriptions($days);
    }

    /**
     * Generate notification payload
     */
    private function getNotificationPayload(
        NotificationType $type,
        ?User $actor,
        string $entityType,
        UuidInterface $entityId,
        ?string $entityName,
        ?array $data = null
    ): array {
        $title = $this->getNotificationTitle($type, $actor);
        $body = $this->getNotificationBody($type, $entityName);
        $url = $this->getNotificationUrl($entityType, $entityId, $data);

        return [
            'title' => $title,
            'body' => $body,
            'icon' => '/icon-192.png',
            'badge' => '/icon-192.png',
            'data' => [
                'url' => $url,
                'notificationId' => $entityId->toString(),
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ],
            'tag' => 'notification-' . $entityId->toString(),
            'requireInteraction' => false,
        ];
    }

    private function getNotificationTitle(NotificationType $type, ?User $actor): string
    {
        $actorName = $actor ? $actor->getFullName() : 'Someone';

        return match ($type) {
            NotificationType::TASK_ASSIGNED => "{$actorName} assigned you a task",
            NotificationType::COMMENT_ADDED => "{$actorName} commented on a task",
            NotificationType::MENTIONED => "{$actorName} mentioned you",
            NotificationType::PROJECT_INVITED => "{$actorName} invited you to a project",
            NotificationType::REGISTRATION_REQUEST => "New registration request",
            NotificationType::PROJECT_INVITATION_APPROVAL_REQUIRED => "Project invitation needs approval",
            NotificationType::PROJECT_INVITATION_APPROVED => "Project invitation approved",
            default => "New notification",
        };
    }

    private function getNotificationBody(NotificationType $type, ?string $entityName): string
    {
        if (!$entityName) {
            return '';
        }

        return match ($type) {
            NotificationType::TASK_ASSIGNED => "Task: {$entityName}",
            NotificationType::COMMENT_ADDED => "In task: {$entityName}",
            NotificationType::MENTIONED => "In task: {$entityName}",
            NotificationType::PROJECT_INVITED => "Project: {$entityName}",
            default => $entityName,
        };
    }

    private function getNotificationUrl(string $entityType, UuidInterface $entityId, ?array $data = null): string
    {
        try {
            return match ($entityType) {
                'task' => $this->urlGenerator->generate(
                    'app_project_show',
                    ['id' => $data['projectId'] ?? '', 'task' => $entityId->toString()],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                ),
                'project' => $this->urlGenerator->generate(
                    'app_project_show',
                    ['id' => $entityId->toString()],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                ),
                'user' => $this->urlGenerator->generate(
                    'app_home',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                ),
                default => '/',
            };
        } catch (\Exception $e) {
            $this->logger->warning('Failed to generate notification URL', [
                'entityType' => $entityType,
                'entityId' => $entityId->toString(),
                'error' => $e->getMessage(),
            ]);
            return '/';
        }
    }

    private function updateSubscriptionLastUsed(User $user, string $endpoint): void
    {
        try {
            $subscription = $this->subscriptionRepository->findByEndpoint($user, $endpoint);
            if ($subscription) {
                $subscription->updateLastUsed();
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to update subscription last used', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handlePushError(User $user, string $endpoint, $report): void
    {
        $statusCode = $report->getResponse()?->getStatusCode();

        if ($statusCode === 410) {
            $this->logger->info('Push subscription expired (410 Gone), removing', [
                'endpoint' => $endpoint,
            ]);
            $this->unsubscribe($user, $endpoint);
        } else {
            $this->logger->error('Failed to send push notification', [
                'endpoint' => $endpoint,
                'status' => $statusCode,
                'reason' => $report->getReason(),
            ]);
        }
    }
}
