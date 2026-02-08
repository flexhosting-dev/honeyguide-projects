<?php

namespace App\Controller\Admin;

use App\Entity\PendingRegistrationRequest;
use App\Entity\User;
use App\Enum\RoleType;
use App\Repository\PendingRegistrationRequestRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\RegistrationRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PendingRegistrationRequestRepository $pendingRequestRepository,
        private readonly RegistrationRequestService $registrationRequestService,
    ) {
    }

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        $tab = $request->query->get('tab', 'users');
        $search = $request->query->get('search', '');
        $users = $this->userRepository->findAllWithSearch($search);
        $portalRoles = $this->roleRepository->findPortalRoles();
        $pendingRequests = $this->pendingRequestRepository->findPending();
        $pendingCount = count($pendingRequests);
        $actionedRequests = $this->pendingRequestRepository->findActioned();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'portalRoles' => $portalRoles,
            'search' => $search,
            'tab' => $tab,
            'pendingRequests' => $pendingRequests,
            'pendingCount' => $pendingCount,
            'actionedRequests' => $actionedRequests,
        ]);
    }

    #[Route('/{id}/role', name: 'admin_users_update_role', methods: ['POST'])]
    public function updateRole(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isPortalSuperAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Super admin required.');
        }

        // Can't change own role
        if ($user->getId()->equals($currentUser->getId())) {
            $this->addFlash('error', 'You cannot change your own portal role.');
            return $this->redirectToRoute('admin_users_index');
        }

        $roleId = $request->request->get('portal_role');

        if (empty($roleId)) {
            // Remove portal role
            $user->setPortalRole(null);
            $this->addFlash('success', 'Portal role removed from ' . $user->getFullName());
        } else {
            $role = $this->roleRepository->find($roleId);

            if (!$role || !$role->isPortalRole()) {
                $this->addFlash('error', 'Invalid portal role selected.');
                return $this->redirectToRoute('admin_users_index');
            }

            $user->setPortalRole($role);
            $this->addFlash('success', $user->getFullName() . ' is now a ' . $role->getName());
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/pending/{id}/approve', name: 'admin_users_approve_request', methods: ['POST'])]
    public function approveRequest(Request $request, PendingRegistrationRequest $pendingRequest): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        try {
            $note = $request->request->get('note');
            $user = $this->registrationRequestService->approve($pendingRequest, $currentUser, $note);
            $this->addFlash('success', 'Registration approved. ' . $user->getFullName() . ' can now log in.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users_index', ['tab' => 'pending']);
    }

    #[Route('/pending/{id}/reject', name: 'admin_users_reject_request', methods: ['POST'])]
    public function rejectRequest(Request $request, PendingRegistrationRequest $pendingRequest): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        try {
            $note = $request->request->get('note');
            $this->registrationRequestService->reject($pendingRequest, $currentUser, $note);
            $this->addFlash('success', 'Registration request from ' . $pendingRequest->getFullName() . ' has been rejected.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users_index', ['tab' => 'pending']);
    }

    #[Route('/{id}', name: 'admin_users_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        $portalRoles = $this->roleRepository->findPortalRoles();

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'portalRoles' => $portalRoles,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isPortalSuperAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Super admin required.');
        }

        // Cannot delete yourself
        if ($user->getId()->equals($currentUser->getId())) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_users_index');
        }

        $userName = $user->getFullName();
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', $userName . ' has been removed from the portal.');

        return $this->redirectToRoute('admin_users_index');
    }
}
