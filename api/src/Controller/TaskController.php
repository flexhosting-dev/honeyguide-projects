<?php

namespace App\Controller;

use App\Entity\Milestone;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Form\TaskFormType;
use App\Repository\MilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly MilestoneRepository $milestoneRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
    ) {
    }

    #[Route('/my-tasks', name: 'app_task_my_tasks', methods: ['GET'])]
    public function myTasks(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $tasks = $this->taskRepository->findUserTasks($user);
        $recentProjects = $this->projectRepository->findByUser($user);

        // Group tasks by status
        $tasksByStatus = [
            'todo' => [],
            'in_progress' => [],
            'in_review' => [],
            'completed' => [],
        ];

        foreach ($tasks as $task) {
            $status = $task->getStatus()->value;
            $tasksByStatus[$status][] = $task;
        }

        return $this->render('task/index.html.twig', [
            'page_title' => 'My Tasks',
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/projects/{projectId}/tasks/new', name: 'app_task_new_for_project', methods: ['GET', 'POST'])]
    public function newForProject(Request $request, string $projectId): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        // Get the first milestone as default, or null if none exist
        $milestones = $project->getMilestones();
        $defaultMilestone = $milestones->count() > 0 ? $milestones->first() : null;

        $task = new Task();
        if ($defaultMilestone) {
            $task->setMilestone($defaultMilestone);
        }

        $form = $this->createForm(TaskFormType::class, $task, [
            'project' => $project,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $milestone = $task->getMilestone();

            // Set position to end of list
            $maxPosition = $this->taskRepository->findMaxPositionInMilestone($milestone);
            $task->setPosition($maxPosition + 1);

            $this->entityManager->persist($task);

            $this->activityService->logTaskCreated(
                $project,
                $user,
                $task->getId(),
                $task->getTitle()
            );

            $this->entityManager->flush();

            $this->addFlash('success', 'Task created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('task/new.html.twig', [
            'page_title' => 'New Task',
            'project' => $project,
            'milestone' => $defaultMilestone,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/milestones/{milestoneId}/tasks/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $milestoneId): Response
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        if (!$milestone) {
            throw $this->createNotFoundException('Milestone not found');
        }

        $project = $milestone->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $task = new Task();
        $task->setMilestone($milestone);

        $form = $this->createForm(TaskFormType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set position to end of list
            $maxPosition = $this->taskRepository->findMaxPositionInMilestone($milestone);
            $task->setPosition($maxPosition + 1);

            $this->entityManager->persist($task);

            $this->activityService->logTaskCreated(
                $project,
                $user,
                $task->getId(),
                $task->getTitle()
            );

            $this->entityManager->flush();

            $this->addFlash('success', 'Task created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('task/new.html.twig', [
            'page_title' => 'New Task',
            'project' => $project,
            'milestone' => $milestone,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/tasks/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();
        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('task/show.html.twig', [
            'page_title' => $task->getTitle(),
            'task' => $task,
            'project' => $project,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task): Response
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $oldStatus = $task->getStatus();
        $form = $this->createForm(TaskFormType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $task->getStatus();

            if ($oldStatus !== $newStatus) {
                $this->activityService->logTaskStatusChanged(
                    $project,
                    $user,
                    $task->getId(),
                    $task->getTitle(),
                    $oldStatus->label(),
                    $newStatus->label()
                );
            } else {
                $this->activityService->logTaskUpdated(
                    $project,
                    $user,
                    $task->getId(),
                    $task->getTitle()
                );
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Task updated successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('task/edit.html.twig', [
            'page_title' => 'Edit Task',
            'task' => $task,
            'project' => $project,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/tasks/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task): Response
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        if ($this->isCsrfTokenValid('delete' . $task->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($task);
            $this->entityManager->flush();

            $this->addFlash('success', 'Task deleted successfully.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/tasks/{id}/status', name: 'app_task_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $newStatusValue = $data['status'] ?? null;

        if (!$newStatusValue) {
            return $this->json(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        $newStatus = TaskStatus::tryFrom($newStatusValue);
        if (!$newStatus) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $oldStatus = $task->getStatus();
        $task->setStatus($newStatus);

        $this->activityService->logTaskStatusChanged(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            $oldStatus->label(),
            $newStatus->label()
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $newStatus->value,
            'statusLabel' => $newStatus->label(),
        ]);
    }
}
