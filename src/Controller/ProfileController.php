<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    private const ALLOWED_FIELDS = ['firstName', 'lastName', 'email', 'jobTitle', 'department'];
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SluggerInterface $slugger,
        #[Autowire('%avatars_directory%')]
        private readonly string $avatarsDirectory,
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('app_profile');
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('profile/index.html.twig', [
            'page_title' => 'Profile',
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/update-field', name: 'app_profile_update_field', methods: ['POST'])]
    public function updateField(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        if (!$field || !in_array($field, self::ALLOWED_FIELDS, true)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid field'], 400);
        }

        $value = trim((string) $value);

        // Validate based on field type
        $error = $this->validateField($field, $value);
        if ($error) {
            return new JsonResponse(['success' => false, 'error' => $error], 400);
        }

        // Update the field using setter
        $setter = 'set' . ucfirst($field);
        if (method_exists($user, $setter)) {
            $user->$setter($value ?: null);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'value' => $value,
                'message' => 'Field updated successfully',
            ]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Unable to update field'], 400);
    }

    #[Route('/avatar', name: 'app_profile_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $file = $request->files->get('avatar');

        if (!$file) {
            return new JsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        // Validate file type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.',
            ], 400);
        }

        // Validate file size
        if ($file->getSize() > self::MAX_AVATAR_SIZE) {
            return new JsonResponse([
                'success' => false,
                'error' => 'File is too large. Maximum size is 2MB.',
            ], 400);
        }

        // Delete old avatar if exists
        $this->deleteAvatarFile($user->getAvatar());

        // Generate unique filename
        $extension = $file->guessExtension() ?? 'jpg';
        $filename = $this->slugger->slug((string) $user->getId()) . '-' . uniqid() . '.' . $extension;

        try {
            $file->move($this->avatarsDirectory, $filename);
        } catch (FileException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to upload file. Please try again.',
            ], 500);
        }

        $user->setAvatar($filename);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'avatar' => '/uploads/avatars/' . $filename,
            'message' => 'Avatar updated successfully',
        ]);
    }

    #[Route('/avatar', name: 'app_profile_avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->deleteAvatarFile($user->getAvatar());

        $user->setAvatar(null);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'initials' => $user->getInitials(),
            'message' => 'Avatar removed successfully',
        ]);
    }

    #[Route('/password', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Check if this is an AJAX request
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            || $request->headers->get('Accept') === 'application/json';

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            $error = 'All password fields are required.';
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => $error], 400);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('app_profile');
        }

        // For users without a password (Google-only), skip current password check
        if ($user->getPassword() !== null && !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $error = 'Current password is incorrect.';
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => $error], 400);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('app_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => $error], 400);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => $error], 400);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        if ($isAjax) {
            return new JsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        }

        $this->addFlash('success', 'Password changed successfully.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/disconnect-google', name: 'app_profile_disconnect_google', methods: ['POST'])]
    public function disconnectGoogle(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user has a password set (required to disconnect Google)
        if ($user->getPassword() === null) {
            $error = 'You must set a password before disconnecting Google. Otherwise, you won\'t be able to log in.';
            if ($request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['success' => false, 'error' => $error], 400);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('app_profile');
        }

        $user->setGoogleId(null);
        $this->entityManager->flush();

        if ($request->headers->get('Accept') === 'application/json') {
            return new JsonResponse(['success' => true, 'message' => 'Google account disconnected.']);
        }

        $this->addFlash('success', 'Google account disconnected.');
        return $this->redirectToRoute('app_profile');
    }

    private function validateField(string $field, string $value): ?string
    {
        return match ($field) {
            'firstName', 'lastName' => $this->validateName($value),
            'email' => $this->validateEmail($value),
            'jobTitle', 'department' => $this->validateOptionalField($value, 100),
            default => null,
        };
    }

    private function validateName(string $value): ?string
    {
        if (empty($value)) {
            return 'This field is required';
        }
        if (strlen($value) > 100) {
            return 'Maximum 100 characters allowed';
        }
        return null;
    }

    private function validateEmail(string $value): ?string
    {
        if (empty($value)) {
            return 'Email is required';
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address';
        }
        return null;
    }

    private function validateOptionalField(string $value, int $maxLength): ?string
    {
        if (strlen($value) > $maxLength) {
            return "Maximum {$maxLength} characters allowed";
        }
        return null;
    }

    private function deleteAvatarFile(?string $filename): void
    {
        if ($filename) {
            $filepath = $this->avatarsDirectory . '/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}
