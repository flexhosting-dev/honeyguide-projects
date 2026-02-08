<?php

namespace App\Service;

use App\Entity\PendingRegistrationRequest;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\RegistrationRequestStatus;
use App\Enum\RegistrationType;
use App\Repository\PendingRegistrationRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationRequestService
{
    private array $allowedDomains;
    private Address $fromAddress;

    public function __construct(
        #[Autowire('%env(ALLOWED_REGISTRATION_DOMAINS)%')]
        string $allowedDomains,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        string $fromEmail,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        string $fromName,
        private readonly EntityManagerInterface $entityManager,
        private readonly PendingRegistrationRequestRepository $pendingRequestRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->allowedDomains = array_filter(array_map('trim', explode(',', $allowedDomains)));
        $this->fromAddress = new Address($fromEmail, $fromName);
    }

    public function isDomainAllowed(string $email): bool
    {
        if (empty($this->allowedDomains)) {
            return true;
        }

        $domain = substr(strrchr($email, '@'), 1);

        return in_array($domain, $this->allowedDomains, true);
    }

    public function getAllowedDomains(): array
    {
        return $this->allowedDomains;
    }

    public function createGoogleRequest(
        string $email,
        string $firstName,
        string $lastName,
        string $googleId,
    ): PendingRegistrationRequest {
        $existing = $this->pendingRequestRepository->findPendingByEmail($email);
        if ($existing !== null) {
            return $existing;
        }

        $request = new PendingRegistrationRequest();
        $request->setEmail($email);
        $request->setFirstName($firstName);
        $request->setLastName($lastName);
        $request->setGoogleId($googleId);
        $request->setRegistrationType(RegistrationType::GOOGLE);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $this->notifyAdmins($request);

        return $request;
    }

    public function createManualRequest(
        string $email,
        string $firstName,
        string $lastName,
        string $passwordHash,
    ): PendingRegistrationRequest {
        $existing = $this->pendingRequestRepository->findPendingByEmail($email);
        if ($existing !== null) {
            return $existing;
        }

        $request = new PendingRegistrationRequest();
        $request->setEmail($email);
        $request->setFirstName($firstName);
        $request->setLastName($lastName);
        $request->setPasswordHash($passwordHash);
        $request->setRegistrationType(RegistrationType::MANUAL);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $this->notifyAdmins($request);

        return $request;
    }

    private function notifyAdmins(PendingRegistrationRequest $request): void
    {
        $admins = $this->userRepository->findPortalAdmins();

        foreach ($admins as $admin) {
            $this->notificationService->notify(
                $admin,
                NotificationType::REGISTRATION_REQUEST,
                null,
                'registration_request',
                $request->getId(),
                $request->getFullName(),
                [
                    'email' => $request->getEmail(),
                    'domain' => $request->getDomain(),
                    'type' => $request->getRegistrationType()->value,
                ],
            );

            if ($admin->shouldReceiveNotification(NotificationType::REGISTRATION_REQUEST, 'email')) {
                $this->sendEmailNotification($admin, $request);
            }
        }

        $this->entityManager->flush();
    }

    private function sendEmailNotification(User $admin, PendingRegistrationRequest $request): void
    {
        $usersUrl = $this->urlGenerator->generate(
            'admin_users_index',
            ['tab' => 'pending'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Registration Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">New Registration Request</h1>
        <p>Hi {$admin->getFirstName()},</p>
        <p>A new user has requested access to Honeyguide Projects:</p>
        <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Name</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">{$request->getFullName()}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Email</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">{$request->getEmail()}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Domain</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">{$request->getDomain()}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Registration Type</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">{$request->getRegistrationType()->label()}</td>
            </tr>
        </table>
        <p style="margin: 30px 0;">
            <a href="{$usersUrl}"
               style="background-color: #2563eb; color: white; padding: 12px 24px;
                      text-decoration: none; border-radius: 6px; display: inline-block;">
                Review Request
            </a>
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            This is an automated email from Honeyguide Projects. Please do not reply.
        </p>
    </div>
</body>
</html>
HTML;

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($admin->getEmail())
            ->subject('New Registration Request: ' . $request->getFullName())
            ->html($html);

        $this->mailer->send($email);
    }

    public function approve(PendingRegistrationRequest $request, User $reviewer, ?string $note = null): User
    {
        // Prevent approving already-approved requests (would create duplicate user)
        if ($request->getStatus() === RegistrationRequestStatus::APPROVED) {
            throw new \LogicException('Cannot approve an already-approved request.');
        }

        // Check if a user with this email already exists
        $existingUser = $this->userRepository->findByEmail($request->getEmail());
        if ($existingUser !== null) {
            throw new \LogicException('A user with this email already exists.');
        }

        $request->setStatus(RegistrationRequestStatus::APPROVED);
        $request->setReviewedBy($reviewer);
        $request->setReviewedAt(new \DateTimeImmutable());
        $request->setNote($note);

        $user = new User();
        $user->setEmail($request->getEmail());
        $user->setFirstName($request->getFirstName());
        $user->setLastName($request->getLastName());
        $user->setIsVerified(true);

        if ($request->getRegistrationType() === RegistrationType::GOOGLE) {
            $user->setGoogleId($request->getGoogleId());
        } else {
            $user->setPassword($request->getPasswordHash());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function reject(PendingRegistrationRequest $request, User $reviewer, ?string $note = null): void
    {
        // Prevent rejecting already-approved requests (user already exists)
        if ($request->getStatus() === RegistrationRequestStatus::APPROVED) {
            throw new \LogicException('Cannot reject an already-approved request.');
        }

        $request->setStatus(RegistrationRequestStatus::REJECTED);
        $request->setReviewedBy($reviewer);
        $request->setReviewedAt(new \DateTimeImmutable());
        $request->setNote($note);

        $this->entityManager->flush();
    }
}
