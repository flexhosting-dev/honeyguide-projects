<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $request->query->get('filter');
        $page = max(1, $request->query->getInt('page', 1));

        $notifications = $this->notificationRepository->findByUserFiltered($user, $filter, $page);
        $total = $this->notificationRepository->countByUserFiltered($user, $filter);
        $unreadCount = $this->notificationRepository->countUnread($user);

        return $this->render('notification/index.html.twig', [
            'page_title' => 'Notifications',
            'notifications' => $notifications,
            'filter' => $filter,
            'currentPage' => $page,
            'totalPages' => max(1, (int) ceil($total / 20)),
            'total' => $total,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'count' => $this->notificationRepository->countUnread($user),
        ]);
    }

    #[Route('/recent', name: 'app_notifications_recent', methods: ['GET'])]
    public function recent(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findRecent($user, 10);

        $data = array_map(function (Notification $n) {
            $actor = $n->getActor();
            return [
                'id' => $n->getId()->toString(),
                'type' => $n->getType()->value,
                'typeLabel' => $n->getType()->label(),
                'icon' => $n->getType()->icon(),
                'entityType' => $n->getEntityType(),
                'entityId' => $n->getEntityId()->toString(),
                'entityName' => $n->getEntityName(),
                'actorName' => $actor?->getFullName(),
                'actorInitials' => $actor ? $actor->getInitials() : null,
                'data' => $n->getData(),
                'isRead' => $n->isRead(),
                'createdAt' => $n->getCreatedAt()->format('c'),
                'url' => $this->buildNotificationUrl($n),
            ];
        }, $notifications);

        return $this->json(['notifications' => $data]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markRead(Notification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$notification->getRecipient()->getId()->equals($user->getId())) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->notificationService->markAsRead($notification);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notificationService->markAllAsRead($user);

        return $this->json(['success' => true]);
    }

    private function buildNotificationUrl(Notification $notification): ?string
    {
        $entityType = $notification->getEntityType();
        $entityId = $notification->getEntityId()->toString();

        return match ($entityType) {
            'task' => $this->generateUrl('app_task_show', ['id' => $entityId]),
            'project' => $this->generateUrl('app_project_show', ['id' => $entityId]),
            'registration_request' => $this->generateUrl('admin_users_index', ['tab' => 'pending']),
            default => null,
        };
    }
}
