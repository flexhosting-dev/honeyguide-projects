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

class RegistrationRequestService
{
    private array $allowedDomains;

    public function __construct(
        #[Autowire('%env(ALLOWED_REGISTRATION_DOMAINS)%')]
        string $allowedDomains,
        private readonly EntityManagerInterface $entityManager,
        private readonly PendingRegistrationRequestRepository $pendingRequestRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
        $this->allowedDomains = array_filter(array_map('trim', explode(',', $allowedDomains)));
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
            // NotificationService now handles both in-app and email notifications
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
                    'type' => $request->getRegistrationType()->label(),
                ],
            );
        }

        $this->entityManager->flush();
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
