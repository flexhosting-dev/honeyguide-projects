<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PersonalProjectService;
use App\Service\ProjectInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InvitationController extends AbstractController
{
    public function __construct(
        private readonly ProjectInvitationService $invitationService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PersonalProjectService $personalProjectService,
    ) {
    }

    #[Route('/invitations/{token}/accept', name: 'app_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(Request $request, string $token): Response
    {
        $invitation = $this->invitationService->findByToken($token);

        if (!$invitation) {
            $this->addFlash('error', 'Invalid invitation link.');
            return $this->redirectToRoute('app_login');
        }

        if ($invitation->isExpired()) {
            $this->addFlash('error', 'This invitation has expired.');
            return $this->redirectToRoute('app_login');
        }

        if ($invitation->getStatus()->value !== 'pending') {
            $this->addFlash('error', 'This invitation is no longer valid.');
            return $this->redirectToRoute('app_login');
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($invitation->getEmail());

        if ($existingUser) {
            // User exists - auto-accept if logged in, otherwise redirect to login
            if ($this->getUser()) {
                if ($this->getUser()->getId()->equals($existingUser->getId())) {
                    try {
                        $this->invitationService->acceptInvitation($invitation, $existingUser);
                        $this->addFlash('success', 'You have been added to the project.');
                        return $this->redirectToRoute('app_project_show', ['id' => $invitation->getProject()->getId()]);
                    } catch (\Exception $e) {
                        $this->addFlash('error', $e->getMessage());
                        return $this->redirectToRoute('app_dashboard');
                    }
                } else {
                    $this->addFlash('error', 'This invitation is for a different user. Please log out and try again.');
                    return $this->redirectToRoute('app_dashboard');
                }
            } else {
                // Redirect to login with return URL
                $this->addFlash('info', 'Please log in to accept the invitation.');
                return $this->redirectToRoute('app_login', ['invitation' => $token]);
            }
        }

        // User doesn't exist - show registration form
        if ($request->isMethod('POST')) {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('passwordConfirm');

            // Validate
            $errors = [];
            if (empty($firstName)) {
                $errors[] = 'First name is required.';
            }
            if (empty($lastName)) {
                $errors[] = 'Last name is required.';
            }
            if (empty($password)) {
                $errors[] = 'Password is required.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Passwords do not match.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('invitation/accept.html.twig', [
                    'invitation' => $invitation,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                ]);
            }

            // Create user
            $user = new User();
            $user->setEmail($invitation->getEmail());
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setIsVerified(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create personal project for new user
            try {
                $this->personalProjectService->createPersonalProject($user);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                // Log but don't fail - personal project is nice to have
                error_log('Failed to create personal project: ' . $e->getMessage());
            }

            // Accept invitation
            try {
                $this->invitationService->acceptInvitation($invitation, $user);
                $this->addFlash('success', 'Your account has been created and you have been added to the project.');

                // Auto-login the user would require LoginFormAuthenticator, so just redirect to login
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('invitation/accept.html.twig', [
            'invitation' => $invitation,
        ]);
    }
}
