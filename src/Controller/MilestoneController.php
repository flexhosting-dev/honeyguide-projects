<?php

namespace App\Controller;

use App\Entity\Milestone;
use App\Entity\MilestoneTarget;
use App\Entity\Project;
use App\Entity\User;
use App\Form\MilestoneFormType;
use App\Repository\ProjectRepository;
use App\Service\ActivityService;
use App\Service\HtmlSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects/{projectId}/milestones')]
class MilestoneController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    #[Route('/json', name: 'app_milestone_list_json', methods: ['GET'])]
    public function listJson(string $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        $milestones = [];
        foreach ($project->getMilestones() as $milestone) {
            $milestones[] = [
                'id' => $milestone->getId()->toString(),
                'name' => $milestone->getName(),
                'dueDate' => $milestone->getDueDate()?->format('Y-m-d'),
            ];
        }

        return new JsonResponse(['milestones' => $milestones]);
    }

    #[Route('/new', name: 'app_milestone_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $projectId): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $milestone = new Milestone();
        $milestone->setProject($project);

        $form = $this->createForm(MilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($milestone);

            $this->activityService->logMilestoneCreated(
                $project,
                $user,
                $milestone->getId(),
                $milestone->getName()
            );

            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('milestone/new.html.twig', [
            'page_title' => 'New Milestone',
            'project' => $project,
            'form' => $form,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_milestone_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $projectId, Milestone $milestone): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException('Milestone not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(MilestoneFormType::class, $milestone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone updated successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('milestone/edit.html.twig', [
            'page_title' => 'Edit Milestone',
            'project' => $project,
            'milestone' => $milestone,
            'form' => $form,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_milestone_delete', methods: ['POST'])]
    public function delete(Request $request, string $projectId, Milestone $milestone): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException('Milestone not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        if ($this->isCsrfTokenValid('delete' . $milestone->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($milestone);
            $this->entityManager->flush();

            $this->addFlash('success', 'Milestone deleted successfully.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id}/targets/add', name: 'app_milestone_target_add', methods: ['POST'])]
    public function addTarget(Request $request, string $projectId, Milestone $milestone): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $data = json_decode($request->getContent(), true);
        $description = trim($data['description'] ?? '');
        if (!$description) {
            return new JsonResponse(['error' => 'Description required'], 400);
        }

        $target = new MilestoneTarget();
        $target->setMilestone($milestone);
        $target->setDescription($description);
        $target->setPosition($milestone->getTargets()->count());
        $this->entityManager->persist($target);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $target->getId()->toString()]);
    }

    #[Route('/{id}/targets/{targetId}/toggle', name: 'app_milestone_target_toggle', methods: ['POST'])]
    public function toggleTarget(string $projectId, Milestone $milestone, string $targetId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $target = $this->entityManager->getRepository(MilestoneTarget::class)->find($targetId);
        if (!$target || $target->getMilestone()->getId()->toString() !== $milestone->getId()->toString()) {
            throw $this->createNotFoundException();
        }

        $target->setCompleted(!$target->isCompleted());
        $this->entityManager->flush();

        return new JsonResponse(['completed' => $target->isCompleted()]);
    }

    #[Route('/{id}/targets/{targetId}/remove', name: 'app_milestone_target_remove', methods: ['POST'])]
    public function removeTarget(string $projectId, Milestone $milestone, string $targetId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $target = $this->entityManager->getRepository(MilestoneTarget::class)->find($targetId);
        if (!$target || $target->getMilestone()->getId()->toString() !== $milestone->getId()->toString()) {
            throw $this->createNotFoundException();
        }

        $this->entityManager->remove($target);
        $this->entityManager->flush();

        return new JsonResponse(['removed' => true]);
    }

    #[Route('/{id}/tasks', name: 'app_milestone_tasks', methods: ['GET'])]
    public function getTasks(string $projectId, Milestone $milestone): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        $tasks = [];
        foreach ($milestone->getTasks() as $task) {
            // Only include top-level tasks
            if ($task->getParent() !== null) {
                continue;
            }
            $tasks[] = [
                'id' => $task->getId()->toString(),
                'title' => $task->getTitle(),
                'status' => [
                    'value' => $task->getStatus()->value,
                    'label' => $task->getStatus()->label(),
                ],
                'priority' => [
                    'value' => $task->getPriority()->value,
                    'label' => $task->getPriority()->label(),
                ],
            ];
        }

        return new JsonResponse(['tasks' => $tasks]);
    }

    #[Route('/{id}/description', name: 'app_milestone_update_description', methods: ['POST'])]
    public function updateDescription(Request $request, string $projectId, Milestone $milestone): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project || $milestone->getProject()->getId()->toString() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $data = json_decode($request->getContent(), true);
        $newDescription = $this->htmlSanitizer->sanitize($data['description'] ?? null);

        $milestone->setDescription($newDescription);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'description' => $newDescription,
        ]);
    }
}
