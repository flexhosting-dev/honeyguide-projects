<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Repository\ProjectMemberRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Enum\NotificationType;
use App\Service\ActivityService;
use App\Service\NotificationService;
use App\Service\ProjectInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{id}/members')]
class MemberController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectMemberRepository $projectMemberRepository,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
        private readonly NotificationService $notificationService,
        private readonly ProjectInvitationService $invitationService,
    ) {
    }

    #[Route('/invite', name: 'app_member_invite', methods: ['POST'])]
    public function invite(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $email = $request->request->get('email');
        $roleSlug = $request->request->get('role', 'project-member');

        if (!$email) {
            $this->addFlash('error', 'Email is required.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            $this->addFlash('error', 'User not found with this email.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $existingMember = $this->projectMemberRepository->findByProjectAndUser($project, $user);
        if ($existingMember) {
            $this->addFlash('error', 'User is already a member of this project.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $role = $this->roleRepository->findBySlug($roleSlug);
        if (!$role || !$role->isProjectRole()) {
            $role = $this->roleRepository->findBySlug('project-member');
        }

        if (!$role) {
            $this->addFlash('error', 'Invalid role specified.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($role);

        $this->entityManager->persist($member);

        $this->activityService->logMemberAdded(
            $project,
            $currentUser,
            $user->getFullName(),
            $role->getName()
        );

        $this->notificationService->notify(
            $user,
            NotificationType::PROJECT_INVITED,
            $currentUser,
            'project',
            $project->getId(),
            $project->getName(),
            ['projectId' => $project->getId()->toString()],
        );

        $this->entityManager->flush();

        $this->addFlash('success', $user->getFullName() . ' has been added to the project.');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{memberId}/role', name: 'app_member_update_role', methods: ['POST'])]
    public function updateRole(Request $request, Project $project, string $memberId): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $member = $this->projectMemberRepository->find($memberId);
        if (!$member || $member->getProject()->getId()->toString() !== $project->getId()->toString()) {
            throw $this->createNotFoundException('Member not found');
        }

        $roleSlug = $request->request->get('role');
        $role = $this->roleRepository->findBySlug($roleSlug);

        if ($role && $role->isProjectRole()) {
            $oldRoleName = $member->getRole()->getName();
            $member->setRole($role);

            $this->activityService->logMemberRoleChanged(
                $project,
                $currentUser,
                $member->getUser()->getFullName(),
                $oldRoleName,
                $role->getName()
            );

            $this->entityManager->flush();
            $this->addFlash('success', 'Member role updated.');
        } else {
            $this->addFlash('error', 'Invalid role specified.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{memberId}/remove', name: 'app_member_remove', methods: ['POST'])]
    public function remove(Request $request, Project $project, string $memberId): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $member = $this->projectMemberRepository->find($memberId);
        if (!$member || $member->getProject()->getId()->toString() !== $project->getId()->toString()) {
            throw $this->createNotFoundException('Member not found');
        }

        // Can't remove the owner
        if ($member->getUser()->getId()->equals($project->getOwner()->getId())) {
            $this->addFlash('error', 'Cannot remove the project owner.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('remove' . $member->getId(), $request->request->get('_token'))) {
            $memberName = $member->getUser()->getFullName();

            $this->activityService->logMemberRemoved($project, $currentUser, $memberName);

            $this->notificationService->notify(
                $member->getUser(),
                NotificationType::PROJECT_REMOVED,
                $currentUser,
                'project',
                $project->getId(),
                $project->getName(),
            );

            $this->entityManager->remove($member);
            $this->entityManager->flush();

            $this->addFlash('success', $memberName . ' has been removed from the project.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/eligible', name: 'app_member_eligible', methods: ['GET'])]
    public function eligible(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        $search = $request->query->get('search', '');

        // Get all users except current members and project owner
        $qb = $this->userRepository->createQueryBuilder('u');

        // Exclude project members
        $qb->where('u NOT IN (
            SELECT IDENTITY(pm.user)
            FROM App\Entity\ProjectMember pm
            WHERE pm.project = :project
        )');
        $qb->setParameter('project', $project);

        // Apply search filter
        if ($search !== '') {
            $qb->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search');
            $qb->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults(50);

        $users = $qb->getQuery()->getResult();

        return $this->json([
            'users' => array_map(fn(User $u) => [
                'id' => $u->getId()->toString(),
                'name' => $u->getFullName(),
                'email' => $u->getEmail(),
                'initials' => $u->getInitials(),
            ], $users),
        ]);
    }

    #[Route('/bulk-add', name: 'app_member_bulk_add', methods: ['POST'])]
    public function bulkAdd(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $userIds = $data['userIds'] ?? [];
        $roleSlug = $data['role'] ?? 'project-member';

        if (empty($userIds)) {
            return $this->json(['error' => 'No users selected.'], 400);
        }

        $role = $this->roleRepository->findBySlug($roleSlug);
        if (!$role || !$role->isProjectRole()) {
            $role = $this->roleRepository->findBySlug('project-member');
        }

        $added = [];
        $errors = [];

        foreach ($userIds as $userId) {
            try {
                $user = $this->userRepository->find($userId);
                if (!$user) {
                    $errors[] = "User with ID {$userId} not found.";
                    continue;
                }

                $existingMember = $this->projectMemberRepository->findByProjectAndUser($project, $user);
                if ($existingMember) {
                    $errors[] = $user->getFullName() . ' is already a member.';
                    continue;
                }

                $member = new ProjectMember();
                $member->setProject($project);
                $member->setUser($user);
                $member->setRole($role);

                $this->entityManager->persist($member);

                $this->activityService->logMemberAdded(
                    $project,
                    $currentUser,
                    $user->getFullName(),
                    $role->getName()
                );

                $this->notificationService->notify(
                    $user,
                    NotificationType::PROJECT_INVITED,
                    $currentUser,
                    'project',
                    $project->getId(),
                    $project->getName(),
                    ['projectId' => $project->getId()->toString()],
                );

                $added[] = $user->getFullName();
            } catch (\Exception $e) {
                $errors[] = 'Error adding user: ' . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        $message = count($added) . ' user(s) added successfully.';
        if (!empty($errors)) {
            $message .= ' ' . implode(' ', $errors);
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'added' => count($added),
        ]);
    }

    #[Route('/invite-email', name: 'app_member_invite_email', methods: ['POST'])]
    public function inviteEmail(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $roleSlug = $data['role'] ?? 'project-member';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Valid email is required.'], 400);
        }

        $role = $this->roleRepository->findBySlug($roleSlug);
        if (!$role || !$role->isProjectRole()) {
            $role = $this->roleRepository->findBySlug('project-member');
        }

        try {
            // Check if user exists
            $user = $this->userRepository->findByEmail($email);

            if ($user) {
                // User exists - add directly
                $existingMember = $this->projectMemberRepository->findByProjectAndUser($project, $user);
                if ($existingMember) {
                    return $this->json(['error' => 'User is already a member of this project.'], 400);
                }

                $member = new ProjectMember();
                $member->setProject($project);
                $member->setUser($user);
                $member->setRole($role);

                $this->entityManager->persist($member);

                $this->activityService->logMemberAdded(
                    $project,
                    $currentUser,
                    $user->getFullName(),
                    $role->getName()
                );

                $this->notificationService->notify(
                    $user,
                    NotificationType::PROJECT_INVITED,
                    $currentUser,
                    'project',
                    $project->getId(),
                    $project->getName(),
                    ['projectId' => $project->getId()->toString()],
                );

                $this->entityManager->flush();

                return $this->json([
                    'success' => true,
                    'message' => $user->getFullName() . ' has been added to the project.',
                ]);
            } else {
                // User doesn't exist - create invitation
                $invitation = $this->invitationService->createInvitation(
                    $project,
                    $currentUser,
                    $email,
                    $role
                );

                if ($invitation->getStatus()->value === 'pending_admin_approval') {
                    return $this->json([
                        'success' => true,
                        'message' => 'Invitation sent to administrators for approval (restricted domain).',
                        'requiresApproval' => true,
                    ]);
                } else {
                    return $this->json([
                        'success' => true,
                        'message' => 'Invitation sent to ' . $email,
                    ]);
                }
            }
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
