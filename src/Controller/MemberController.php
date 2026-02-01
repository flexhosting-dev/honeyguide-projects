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
}
