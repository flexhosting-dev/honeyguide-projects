<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\ProjectInvitation;
use App\Entity\ProjectMember;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\ProjectInvitationStatus;
use App\Repository\ProjectInvitationRepository;
use App\Repository\ProjectMemberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProjectInvitationService
{
    private array $allowedDomains;

    public function __construct(
        #[Autowire('%env(ALLOWED_REGISTRATION_DOMAINS)%')]
        string $allowedDomains,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectInvitationRepository $invitationRepository,
        private readonly ProjectMemberRepository $memberRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly NotificationEmailService $emailService,
        private readonly ActivityService $activityService,
    ) {
        $this->allowedDomains = array_filter(array_map('trim', explode(',', $allowedDomains)));
    }

    public function createInvitation(
        Project $project,
        User $invitedBy,
        string $email,
        Role $role,
    ): ProjectInvitation {
        // Check if invitation already exists
        $existing = $this->invitationRepository->findPendingByProjectAndEmail($project, $email);
        if ($existing !== null) {
            throw new \LogicException('An invitation for this email already exists for this project.');
        }

        // Check if user is already a member
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            $existingMember = $this->memberRepository->findByProjectAndUser($project, $existingUser);
            if ($existingMember !== null) {
                throw new \LogicException('User is already a member of this project.');
            }
        }

        $invitation = new ProjectInvitation();
        $invitation->setProject($project);
        $invitation->setInvitedBy($invitedBy);
        $invitation->setEmail($email);
        $invitation->setRole($role);
        $invitation->setInvitedUser($existingUser);

        // Check if domain requires admin approval
        if (!$this->isDomainAllowed($email) && !$invitedBy->isPortalAdmin()) {
            $invitation->setStatus(ProjectInvitationStatus::PENDING_ADMIN_APPROVAL);
            $this->entityManager->persist($invitation);
            $this->entityManager->flush();
            $this->notifyAdminsForApproval($invitation);
        } else {
            $invitation->setStatus(ProjectInvitationStatus::PENDING);
            $this->entityManager->persist($invitation);
            $this->entityManager->flush();
            $this->sendInvitationEmail($invitation);
        }

        return $invitation;
    }

    public function acceptInvitation(ProjectInvitation $invitation, User $user): void
    {
        if ($invitation->getStatus() !== ProjectInvitationStatus::PENDING) {
            throw new \LogicException('This invitation cannot be accepted.');
        }

        if ($invitation->isExpired()) {
            $invitation->setStatus(ProjectInvitationStatus::EXPIRED);
            $this->entityManager->flush();
            throw new \LogicException('This invitation has expired.');
        }

        // Check if user is already a member
        $existingMember = $this->memberRepository->findByProjectAndUser($invitation->getProject(), $user);
        if ($existingMember !== null) {
            throw new \LogicException('User is already a member of this project.');
        }

        // Create project member
        $member = new ProjectMember();
        $member->setProject($invitation->getProject());
        $member->setUser($user);
        $member->setRole($invitation->getRole());

        $this->entityManager->persist($member);

        // Update invitation status
        $invitation->setStatus(ProjectInvitationStatus::ACCEPTED);
        $invitation->setInvitedUser($user);

        // Log activity
        $this->activityService->logMemberAdded(
            $invitation->getProject(),
            $user,
            $user->getFullName(),
            $invitation->getRole()->getName()
        );

        $this->entityManager->flush();
    }

    public function approveInvitation(ProjectInvitation $invitation, User $reviewer): void
    {
        if ($invitation->getStatus() !== ProjectInvitationStatus::PENDING_ADMIN_APPROVAL) {
            throw new \LogicException('This invitation does not require approval.');
        }

        $invitation->setStatus(ProjectInvitationStatus::PENDING);
        $invitation->setReviewedBy($reviewer);
        $invitation->setReviewedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Send invitation email to the user
        $this->sendInvitationEmail($invitation);

        // Notify the user who created the invitation
        $this->notificationService->notify(
            $invitation->getInvitedBy(),
            NotificationType::PROJECT_INVITATION_APPROVED,
            $reviewer,
            'project',
            $invitation->getProject()->getId(),
            $invitation->getProject()->getName(),
            [
                'projectId' => $invitation->getProject()->getId()->toString(),
                'inviteeEmail' => $invitation->getEmail(),
            ],
        );

        $this->entityManager->flush();
    }

    public function declineInvitation(ProjectInvitation $invitation, ?User $reviewer = null): void
    {
        if ($invitation->getStatus() !== ProjectInvitationStatus::PENDING_ADMIN_APPROVAL &&
            $invitation->getStatus() !== ProjectInvitationStatus::PENDING) {
            throw new \LogicException('This invitation cannot be declined.');
        }

        $invitation->setStatus(ProjectInvitationStatus::DECLINED);
        if ($reviewer !== null) {
            $invitation->setReviewedBy($reviewer);
            $invitation->setReviewedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    private function sendInvitationEmail(ProjectInvitation $invitation): void
    {
        $this->emailService->sendProjectInvitationEmail(
            $invitation->getEmail(),
            $invitation->getProject()->getName(),
            $invitation->getInvitedBy()->getFullName(),
            $invitation->getRole()->getName(),
            $invitation->getToken(),
        );
    }

    private function notifyAdminsForApproval(ProjectInvitation $invitation): void
    {
        $admins = $this->userRepository->findPortalAdmins();

        foreach ($admins as $admin) {
            $this->notificationService->notify(
                $admin,
                NotificationType::PROJECT_INVITATION_APPROVAL_REQUIRED,
                $invitation->getInvitedBy(),
                'project',
                $invitation->getProject()->getId(),
                $invitation->getProject()->getName(),
                [
                    'projectId' => $invitation->getProject()->getId()->toString(),
                    'inviteeEmail' => $invitation->getEmail(),
                    'role' => $invitation->getRole()->getName(),
                ],
            );
        }

        $this->entityManager->flush();
    }

    private function isDomainAllowed(string $email): bool
    {
        if (empty($this->allowedDomains)) {
            return true;
        }

        $domain = substr(strrchr($email, '@'), 1);

        return in_array($domain, $this->allowedDomains, true);
    }

    public function findByToken(string $token): ?ProjectInvitation
    {
        return $this->invitationRepository->findByToken($token);
    }
}
