<?php

namespace App\Controller;

use App\Service\PushNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/push')]
#[IsGranted('ROLE_USER')]
class PushNotificationController extends AbstractController
{
    public function __construct(
        private PushNotificationService $pushService,
    ) {
    }

    #[Route('/public-key', name: 'app_push_public_key', methods: ['GET'])]
    public function getPublicKey(): JsonResponse
    {
        return new JsonResponse([
            'publicKey' => $this->pushService->getPublicKey(),
        ]);
    }

    #[Route('/subscribe', name: 'app_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint']) || !isset($data['keys'])) {
            return new JsonResponse(['error' => 'Invalid subscription data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->pushService->subscribe($this->getUser(), $data);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/unsubscribe', name: 'app_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->pushService->unsubscribe($this->getUser(), $data['endpoint']);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/subscription-status', name: 'app_push_subscription_status', methods: ['GET'])]
    public function getSubscriptionStatus(): JsonResponse
    {
        $hasSubscriptions = $this->pushService->hasActiveSubscriptions($this->getUser());
        return new JsonResponse(['hasSubscriptions' => $hasSubscriptions]);
    }

    #[Route('/test', name: 'app_push_test', methods: ['POST'])]
    public function sendTestNotification(): JsonResponse
    {
        try {
            $this->pushService->sendPushNotification(
                $this->getUser(),
                \App\Enum\NotificationType::MENTIONED,
                null,
                'task',
                \Symfony\Component\Uid\Uuid::v7(),
                'Test Notification',
                null
            );
            return new JsonResponse(['success' => true, 'message' => 'Test notification sent']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
