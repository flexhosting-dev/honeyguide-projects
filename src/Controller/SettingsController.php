<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig', [
            'page_title' => 'Settings',
        ]);
    }

    #[Route('/users', name: 'app_settings_users', methods: ['GET'])]
    public function users(): Response
    {
        return $this->render('settings/users.html.twig', [
            'page_title' => 'User Permissions',
        ]);
    }

    #[Route('/notifications', name: 'app_settings_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $preferences = $data['preferences'] ?? [];
            $user->setNotificationPreferences($preferences);
            $this->entityManager->flush();

            return $this->json(['success' => true]);
        }

        // Group types by category
        $categories = [];
        foreach (NotificationType::cases() as $type) {
            $category = $type->category();
            $categories[$category][] = $type;
        }

        return $this->render('settings/notifications.html.twig', [
            'page_title' => 'Notification Preferences',
            'preferences' => $user->getNotificationPreferences(),
            'categories' => $categories,
        ]);
    }
}
