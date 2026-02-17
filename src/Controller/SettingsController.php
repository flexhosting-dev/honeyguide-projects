<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\ChangelogRepository;
use App\Repository\PortalSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings')]
class SettingsController extends AbstractController
{
    public const ABOUT_TEXT_KEY = 'about_text';
    public const ABOUT_HEADING_KEY = 'about_heading';
    public const APP_VERSION_KEY = 'app_version';

    private const DEFAULT_ABOUT_TEXT = '<p>A modern, intuitive project management platform designed to help teams collaborate effectively and deliver projects on time.</p><p>With powerful features like Kanban boards, task hierarchies, milestone tracking, and real-time collaboration, this app provides everything you need to manage projects of any size.</p>';
    private const DEFAULT_ABOUT_HEADING = 'About this App';
    private const DEFAULT_APP_VERSION = '1.0.0';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PortalSettingRepository $portalSettingRepository,
        private readonly ChangelogRepository $changelogRepository,
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

        // Group types by category and build defaults
        $categories = [];
        $defaults = [];
        foreach (NotificationType::cases() as $type) {
            $category = $type->category();
            $categories[$category][] = $type;
            $defaults[$type->value] = [
                'in_app' => $type->defaultInApp(),
                'email' => $type->defaultEmail(),
            ];
        }

        return $this->render('settings/notifications.html.twig', [
            'page_title' => 'Notification Preferences',
            'preferences' => $user->getNotificationPreferences(),
            'defaults' => $defaults,
            'categories' => $categories,
        ]);
    }

    #[Route('/about', name: 'app_settings_about', methods: ['GET'])]
    public function about(): Response
    {
        $aboutText = $this->portalSettingRepository->get(self::ABOUT_TEXT_KEY, self::DEFAULT_ABOUT_TEXT);
        $aboutHeading = $this->portalSettingRepository->get(self::ABOUT_HEADING_KEY, self::DEFAULT_ABOUT_HEADING);
        $appVersion = $this->portalSettingRepository->get(self::APP_VERSION_KEY, self::DEFAULT_APP_VERSION);

        /** @var User|null $user */
        $user = $this->getUser();
        $canEdit = $user?->isPortalSuperAdmin() ?? false;

        return $this->render('settings/about.html.twig', [
            'page_title' => 'About',
            'about_text' => $aboutText,
            'about_heading' => $aboutHeading,
            'app_version' => $appVersion,
            'can_edit' => $canEdit,
        ]);
    }

    #[Route('/about/text', name: 'app_settings_about_text', methods: ['POST'])]
    public function updateAboutText(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user?->isPortalSuperAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $description = $data['description'] ?? '';

        $this->portalSettingRepository->set(self::ABOUT_TEXT_KEY, $description);

        return $this->json(['success' => true]);
    }

    #[Route('/about/heading', name: 'app_settings_about_heading', methods: ['POST'])]
    public function updateAboutHeading(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user?->isPortalSuperAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $heading = trim($data['value'] ?? '');

        if (empty($heading)) {
            return $this->json(['error' => 'Heading cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        $this->portalSettingRepository->set(self::ABOUT_HEADING_KEY, $heading);

        return $this->json(['success' => true, 'value' => $heading]);
    }

    #[Route('/about/version', name: 'app_settings_about_version', methods: ['POST'])]
    public function updateAppVersion(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user?->isPortalSuperAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $version = trim($data['value'] ?? '');

        if (empty($version)) {
            return $this->json(['error' => 'Version cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        $this->portalSettingRepository->set(self::APP_VERSION_KEY, $version);

        return $this->json(['success' => true, 'value' => $version]);
    }

    #[Route('/about/changelogs', name: 'app_settings_changelogs', methods: ['GET'])]
    public function getChangelogs(): JsonResponse
    {
        $changelogs = $this->changelogRepository->findAllOrderedByDate();

        return $this->json([
            'changelogs' => array_map(fn($c) => $c->toArray(), $changelogs),
        ]);
    }

    #[Route('/task-table-preferences/{key}', name: 'app_settings_task_table_preferences', methods: ['GET', 'POST'])]
    public function taskTablePreferences(Request $request, string $key): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $user->setUiPreference($key, $data);
            $this->entityManager->flush();

            return $this->json(['success' => true]);
        }

        $preferences = $user->getUiPreference($key);

        return $this->json([
            'success' => true,
            'preferences' => $preferences,
        ]);
    }
}
