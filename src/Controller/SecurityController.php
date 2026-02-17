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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

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
        Security $security,
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

            // Auto-login the user after registration
            $security->login($user, 'form_login', 'main');

            $this->addFlash('success', 'Welcome! Your account has been created.');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ResetPasswordHelperInterface $resetPasswordHelper,
        MailerInterface $mailer
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $emailAddress = $request->request->get('email');

            if ($emailAddress) {
                $user = $userRepository->findByEmail($emailAddress);

                if ($user) {
                    try {
                        $resetToken = $resetPasswordHelper->generateResetToken($user);

                        $resetUrl = $this->generateUrl('app_reset_password', [
                            'token' => $resetToken->getToken(),
                        ], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

                        $fromAddress = $_ENV['MAILER_FROM_ADDRESS'] ?? 'noreply@honeyguide.org';
                        $fromName = $_ENV['MAILER_FROM_NAME'] ?? 'Honeyguide Projects';

                        $email = (new Email())
                            ->from(new Address($fromAddress, $fromName))
                            ->to($user->getEmail())
                            ->subject('Password Reset Request')
                            ->html($this->renderResetEmail($user, $resetUrl));

                        $mailer->send($email);
                    } catch (ResetPasswordExceptionInterface $e) {
                        // Silently fail to prevent email enumeration
                    }
                }

                $this->addFlash('success', 'If an account exists with this email, you will receive a password reset link.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        Request $request,
        string $token,
        ResetPasswordHelperInterface $resetPasswordHelper,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        try {
            /** @var User $user */
            $user = $resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', 'This password reset link is invalid or has expired.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $entityManager->flush();

            $this->addFlash('success', 'Your password has been reset successfully. You can now log in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }

    private function renderResetEmail(User $user, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">Password Reset Request</h1>
        <p>Hi {$user->getFirstName()},</p>
        <p>You requested a password reset for your account. Click the button below to reset your password:</p>
        <p style="margin: 30px 0;">
            <a href="{$resetUrl}"
               style="background-color: #2563eb; color: white; padding: 12px 24px;
                      text-decoration: none; border-radius: 6px; display: inline-block;">
                Reset Password
            </a>
        </p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this reset, you can safely ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            This is an automated email from Honeyguide Projects. Please do not reply.
        </p>
    </div>
</body>
</html>
HTML;
    }
}
