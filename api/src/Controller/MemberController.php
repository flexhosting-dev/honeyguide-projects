<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\ProjectMemberRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\ActivityService;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
    ) {
    }

    #[Route('/invite', name: 'app_member_invite', methods: ['POST'])]
    public function invite(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $email = $request->request->get('email');
        $roleValue = $request->request->get('role', 'member');

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

        $role = ProjectRole::tryFrom($roleValue) ?? ProjectRole::MEMBER;

        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($role);

        $this->entityManager->persist($member);

        $this->activityService->logMemberAdded(
            $project,
            $currentUser,
            $user->getFullName(),
            $role->label()
        );

        $this->entityManager->flush();

        $this->addFlash('success', $user->getFullName() . ' has been added to the project.');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{memberId}/role', name: 'app_member_update_role', methods: ['POST'])]
    public function updateRole(Request $request, Project $project, string $memberId): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_MANAGE_MEMBERS', $project);

        $member = $this->projectMemberRepository->find($memberId);
        if (!$member || $member->getProject()->getId()->toString() !== $project->getId()->toString()) {
            throw $this->createNotFoundException('Member not found');
        }

        $roleValue = $request->request->get('role');
        $role = ProjectRole::tryFrom($roleValue);

        if ($role) {
            $member->setRole($role);
            $this->entityManager->flush();
            $this->addFlash('success', 'Member role updated.');
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

            $this->entityManager->remove($member);
            $this->entityManager->flush();

            $this->addFlash('success', $memberName . ' has been removed from the project.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
}
