<?php

namespace App\Controller\Admin;

use App\Entity\TaskStatusType;
use App\Repository\TaskStatusTypeRepository;
use App\Service\TaskStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/settings/statuses')]
class TaskStatusController extends AbstractController
{
    public function __construct(
        private readonly TaskStatusTypeRepository $statusRepository,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_admin_statuses', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        $grouped = $this->taskStatusService->getStatusesGroupedByType();

        // Add task counts for each status
        $statusCounts = [];
        foreach (array_merge($grouped['open'], $grouped['closed']) as $status) {
            $statusCounts[$status->getId()->toString()] = $this->taskStatusService->countTasksWithStatus($status);
        }

        return $this->render('admin/statuses/index.html.twig', [
            'openStatuses' => $grouped['open'],
            'closedStatuses' => $grouped['closed'],
            'statusCounts' => $statusCounts,
        ]);
    }

    #[Route('/new', name: 'app_admin_statuses_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $parentType = $request->request->get('parent_type', 'open');
            $color = $request->request->get('color', '#6B7280');

            if (empty($name)) {
                $this->addFlash('error', 'Status name is required.');
                return $this->redirectToRoute('app_admin_statuses_new');
            }

            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($name)->toString());

            // Check for duplicate slug
            if ($this->statusRepository->findBySlug($slug)) {
                $this->addFlash('error', 'A status with this name already exists.');
                return $this->redirectToRoute('app_admin_statuses_new');
            }

            // Validate color format
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#6B7280';
            }

            $status = new TaskStatusType();
            $status->setName($name);
            $status->setSlug($slug);
            $status->setDescription($description ?: null);
            $status->setParentType($parentType);
            $status->setColor($color);
            $status->setIsSystem(false);
            $status->setIsDefault(false);
            $status->setSortOrder($this->taskStatusService->getMaxSortOrder() + 1);

            $this->entityManager->persist($status);
            $this->entityManager->flush();

            $this->addFlash('success', 'Status created successfully.');
            return $this->redirectToRoute('app_admin_statuses');
        }

        return $this->render('admin/statuses/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'app_admin_statuses_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskStatusType $status): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $color = $request->request->get('color', '#6B7280');

            if (empty($name)) {
                $this->addFlash('error', 'Status name is required.');
                return $this->redirectToRoute('app_admin_statuses_edit', ['id' => $status->getId()]);
            }

            // Only update slug if name changed and not a system status
            if (!$status->isSystem() && $name !== $status->getName()) {
                $slugger = new AsciiSlugger();
                $slug = strtolower($slugger->slug($name)->toString());

                $existing = $this->statusRepository->findBySlug($slug);
                if ($existing && $existing->getId()->toString() !== $status->getId()->toString()) {
                    $this->addFlash('error', 'A status with this name already exists.');
                    return $this->redirectToRoute('app_admin_statuses_edit', ['id' => $status->getId()]);
                }

                $status->setSlug($slug);
            }

            // Validate color format
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#6B7280';
            }

            $status->setName($name);
            $status->setDescription($description ?: null);
            $status->setColor($color);

            $this->entityManager->flush();

            $this->addFlash('success', 'Status updated successfully.');
            return $this->redirectToRoute('app_admin_statuses');
        }

        $taskCount = $this->taskStatusService->countTasksWithStatus($status);

        // Get other statuses for reassignment dropdown
        $allStatuses = $this->taskStatusService->getAllStatuses();
        $reassignOptions = array_filter($allStatuses, fn($s) => $s->getId()->toString() !== $status->getId()->toString());

        return $this->render('admin/statuses/edit.html.twig', [
            'status' => $status,
            'taskCount' => $taskCount,
            'reassignOptions' => $reassignOptions,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_statuses_delete', methods: ['POST'])]
    public function delete(Request $request, TaskStatusType $status): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        if ($status->isSystem()) {
            $this->addFlash('error', 'Cannot delete system statuses.');
            return $this->redirectToRoute('app_admin_statuses');
        }

        if (!$this->isCsrfTokenValid('delete' . $status->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_statuses');
        }

        // Check for tasks using this status
        $taskCount = $this->taskStatusService->countTasksWithStatus($status);
        if ($taskCount > 0) {
            // Get the reassignment status
            $reassignToId = $request->request->get('reassign_to');
            if (empty($reassignToId)) {
                $this->addFlash('error', 'Please select a status to reassign tasks to.');
                return $this->redirectToRoute('app_admin_statuses_edit', ['id' => $status->getId()]);
            }

            $reassignTo = $this->statusRepository->find($reassignToId);
            if (!$reassignTo || $reassignTo->getId()->toString() === $status->getId()->toString()) {
                $this->addFlash('error', 'Invalid reassignment status.');
                return $this->redirectToRoute('app_admin_statuses_edit', ['id' => $status->getId()]);
            }

            // Reassign tasks
            $this->entityManager->createQueryBuilder()
                ->update('App\Entity\Task', 't')
                ->set('t.statusType', ':newStatus')
                ->where('t.statusType = :oldStatus')
                ->setParameter('newStatus', $reassignTo)
                ->setParameter('oldStatus', $status)
                ->getQuery()
                ->execute();
        }

        $this->entityManager->remove($status);
        $this->entityManager->flush();

        $this->addFlash('success', 'Status deleted successfully.');
        return $this->redirectToRoute('app_admin_statuses');
    }

    #[Route('/reorder', name: 'app_admin_statuses_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        if (empty($order)) {
            return $this->json(['error' => 'No order provided'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($order as $index => $statusId) {
            $status = $this->statusRepository->find($statusId);
            if ($status) {
                $status->setSortOrder($index);
            }
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/set-default', name: 'app_admin_statuses_set_default', methods: ['POST'])]
    public function setDefault(Request $request, TaskStatusType $status): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isPortalAdmin()) {
            throw $this->createAccessDeniedException('Access denied. Portal admin required.');
        }

        if (!$this->isCsrfTokenValid('set-default' . $status->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_statuses');
        }

        // Unset current default for the same parent type
        $currentDefault = $this->statusRepository->findDefaultForType($status->getParentType());
        if ($currentDefault) {
            $currentDefault->setIsDefault(false);
        }

        // Set new default
        $status->setIsDefault(true);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s is now the default %s status.', $status->getName(), $status->getParentType()));
        return $this->redirectToRoute('app_admin_statuses');
    }
}
