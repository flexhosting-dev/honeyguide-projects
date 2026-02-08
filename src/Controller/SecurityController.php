<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\PersonalProjectService;
use App\Service\RegistrationRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        PersonalProjectService $personalProjectService,
        RegistrationRequestService $registrationRequestService,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getEmail();
            $plainPassword = $form->get('plainPassword')->getData();

            // Check domain restriction
            if (!$registrationRequestService->isDomainAllowed($email)) {
                $passwordHash = $passwordHasher->hashPassword($user, $plainPassword);
                $registrationRequestService->createManualRequest(
                    $email,
                    $user->getFirstName(),
                    $user->getLastName(),
                    $passwordHash,
                );

                $this->addFlash('info', 'Your registration request has been submitted for review. You will be notified once an administrator approves your account.');

                return $this->redirectToRoute('app_login');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setIsVerified(true);

            $entityManager->persist($user);
            $entityManager->flush();

            // Create personal project for new user
            $personalProjectService->createPersonalProject($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your account has been created. Please log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if ($email) {
                $this->addFlash('success', 'If an account exists with this email, you will receive a password reset link.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }
}
