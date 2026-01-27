<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\RoleType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
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
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        $search = $request->query->get('search', '');
        $users = $this->userRepository->findAllWithSearch($search);
        $portalRoles = $this->roleRepository->findPortalRoles();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'portalRoles' => $portalRoles,
            'search' => $search,
        ]);
    }

    #[Route('/{id}/role', name: 'app_admin_users_update_role', methods: ['POST'])]
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
            return $this->redirectToRoute('app_admin_users');
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
                return $this->redirectToRoute('app_admin_users');
            }

            $user->setPortalRole($role);
            $this->addFlash('success', $user->getFullName() . ' is now a ' . $role->getName());
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}', name: 'app_admin_users_show', methods: ['GET'])]
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
}
