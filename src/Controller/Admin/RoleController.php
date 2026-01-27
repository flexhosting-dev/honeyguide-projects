<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Enum\Permission;
use App\Enum\RoleType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/roles')]
class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_admin_roles', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        $portalRoles = $this->roleRepository->findPortalRoles();
        $projectRoles = $this->roleRepository->findProjectRoles();

        return $this->render('admin/roles/index.html.twig', [
            'portalRoles' => $portalRoles,
            'projectRoles' => $projectRoles,
        ]);
    }

    #[Route('/new', name: 'app_admin_roles_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalSuperAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Super admin required.');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $type = $request->request->get('type', 'project');
            $permissions = $request->request->all('permissions') ?? [];

            if (empty($name)) {
                $this->addFlash('error', 'Role name is required.');
                return $this->redirectToRoute('app_admin_roles_new');
            }

            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($name)->toString());

            // Check for duplicate slug
            if ($this->roleRepository->findBySlug($slug)) {
                $this->addFlash('error', 'A role with this name already exists.');
                return $this->redirectToRoute('app_admin_roles_new');
            }

            $role = new Role();
            $role->setName($name);
            $role->setSlug($slug);
            $role->setDescription($description ?: null);
            $role->setType(RoleType::from($type));
            $role->setIsSystemRole(false);
            $role->setPermissions($permissions);

            $this->entityManager->persist($role);
            $this->entityManager->flush();

            $this->addFlash('success', 'Role created successfully.');
            return $this->redirectToRoute('app_admin_roles');
        }

        return $this->render('admin/roles/new.html.twig', [
            'permissions' => Permission::getAll(),
            'permissionLabels' => $this->getPermissionLabels(),
            'roleTypes' => RoleType::cases(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_roles_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalSuperAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Super admin required.');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $permissions = $request->request->all('permissions') ?? [];

            if (empty($name)) {
                $this->addFlash('error', 'Role name is required.');
                return $this->redirectToRoute('app_admin_roles_edit', ['id' => $role->getId()]);
            }

            // Only update slug if name changed and not a system role
            if (!$role->isSystemRole() && $name !== $role->getName()) {
                $slugger = new AsciiSlugger();
                $slug = strtolower($slugger->slug($name)->toString());

                $existing = $this->roleRepository->findBySlug($slug);
                if ($existing && $existing->getId() !== $role->getId()) {
                    $this->addFlash('error', 'A role with this name already exists.');
                    return $this->redirectToRoute('app_admin_roles_edit', ['id' => $role->getId()]);
                }

                $role->setSlug($slug);
            }

            $role->setName($name);
            $role->setDescription($description ?: null);
            $role->setPermissions($permissions);

            $this->entityManager->flush();

            $this->addFlash('success', 'Role updated successfully.');
            return $this->redirectToRoute('app_admin_roles');
        }

        return $this->render('admin/roles/edit.html.twig', [
            'role' => $role,
            'permissions' => Permission::getAll(),
            'permissionLabels' => $this->getPermissionLabels(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_roles_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalSuperAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Super admin required.');
        }

        if ($role->isSystemRole()) {
            $this->addFlash('error', 'Cannot delete system roles.');
            return $this->redirectToRoute('app_admin_roles');
        }

        if ($this->isCsrfTokenValid('delete' . $role->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($role);
            $this->entityManager->flush();
            $this->addFlash('success', 'Role deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_roles');
    }

    private function getPermissionLabels(): array
    {
        $labels = [];
        foreach (Permission::getAll() as $module => $permissions) {
            foreach ($permissions as $permission) {
                $labels[$permission] = Permission::getLabel($permission);
            }
        }
        return $labels;
    }
}
