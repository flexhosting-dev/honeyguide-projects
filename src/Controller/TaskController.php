<?php

namespace App\Controller;

use App\DTO\TaskFilterDTO;
use App\Entity\Milestone;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Form\TaskFormType;
use App\Entity\TaskAssignee;
use App\Repository\ActivityRepository;
use App\Repository\MilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\AttachmentRepository;
use App\Service\ActivityService;
use App\Enum\NotificationType;
use App\Service\HtmlSanitizer;
use App\Service\NotificationService;
use App\Service\PermissionChecker;
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
        private readonly UserRepository $userRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly NotificationService $notificationService,
        private readonly PermissionChecker $permissionChecker,
    ) {
    }

    #[Route('/my-tasks', name: 'app_task_my_tasks', methods: ['GET'])]
    public function myTasks(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = TaskFilterDTO::fromRequest($request);
        $tasks = $this->taskRepository->findUserTasksFiltered($user, $filter);

        // Get all user's projects for the filter dropdown
        $userProjects = $this->projectRepository->findByUser($user);

        // Get all unique users who are members of user's projects for assignee filter
        $projectMembers = [];
        $seenUserIds = [];
        foreach ($userProjects as $project) {
            foreach ($project->getMembers() as $member) {
                $memberUser = $member->getUser();
                $userId = $memberUser->getId()->toString();
                if (!isset($seenUserIds[$userId])) {
                    $seenUserIds[$userId] = true;
                    $projectMembers[] = $memberUser;
                }
            }
        }
        // Sort by name
        usort($projectMembers, fn($a, $b) => $a->getFullName() <=> $b->getFullName());

        // Group tasks by status
        $tasksByStatus = [
            'todo' => [],
            'in_progress' => [],
            'in_review' => [],
            'completed' => [],
        ];

        // Group tasks by priority
        $tasksByPriority = [
            'none' => [],
            'low' => [],
            'medium' => [],
            'high' => [],
        ];

        // Group tasks by milestone
        $tasksByMilestone = [];
        $milestoneMap = [];

        foreach ($tasks as $task) {
            $status = $task->getStatus()->value;
            $tasksByStatus[$status][] = $task;

            $priority = $task->getPriority()->value;
            $tasksByPriority[$priority][] = $task;

            $milestone = $task->getMilestone();
            if ($milestone) {
                $msId = $milestone->getId()->toString();
                if (!isset($tasksByMilestone[$msId])) {
                    $tasksByMilestone[$msId] = [];
                    $milestoneMap[$msId] = $milestone;
                }
                $tasksByMilestone[$msId][] = $task;
            }
        }

        $milestones = array_values($milestoneMap);

        return $this->render('task/index.html.twig', [
            'page_title' => 'My Tasks',
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'tasksByPriority' => $tasksByPriority,
            'tasksByMilestone' => $tasksByMilestone,
            'milestones' => $milestones,
            'filter' => $filter,
            'userProjects' => $userProjects,
            'projectMembers' => $projectMembers,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/all-tasks', name: 'app_task_all_tasks', methods: ['GET'])]
    public function allTasks(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $isAdmin = $user->isPortalAdmin();
        $userProjects = $this->projectRepository->findByUser($user);

        $filter = TaskFilterDTO::fromRequest($request);
        $tasks = $this->taskRepository->findAllTasksFiltered($user, $filter, $isAdmin);

        // Get all unique users who are members of user's projects for assignee filter
        $projectMembers = [];
        $seenUserIds = [];
        foreach ($userProjects as $project) {
            foreach ($project->getMembers() as $member) {
                $memberUser = $member->getUser();
                $userId = $memberUser->getId()->toString();
                if (!isset($seenUserIds[$userId])) {
                    $seenUserIds[$userId] = true;
                    $projectMembers[] = $memberUser;
                }
            }
        }
        // Sort by name
        usort($projectMembers, fn($a, $b) => $a->getFullName() <=> $b->getFullName());

        // Group tasks by status
        $tasksByStatus = [
            'todo' => [],
            'in_progress' => [],
            'in_review' => [],
            'completed' => [],
        ];

        // Group tasks by priority
        $tasksByPriority = [
            'none' => [],
            'low' => [],
            'medium' => [],
            'high' => [],
        ];

        // Group tasks by milestone
        $tasksByMilestone = [];
        $milestoneMap = [];

        foreach ($tasks as $task) {
            $status = $task->getStatus()->value;
            $tasksByStatus[$status][] = $task;

            $priority = $task->getPriority()->value;
            $tasksByPriority[$priority][] = $task;

            $milestone = $task->getMilestone();
            if ($milestone) {
                $msId = $milestone->getId()->toString();
                if (!isset($tasksByMilestone[$msId])) {
                    $tasksByMilestone[$msId] = [];
                    $milestoneMap[$msId] = $milestone;
                }
                $tasksByMilestone[$msId][] = $task;
            }
        }

        $milestones = array_values($milestoneMap);

        return $this->render('task/all_tasks.html.twig', [
            'page_title' => 'All Tasks',
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'tasksByPriority' => $tasksByPriority,
            'tasksByMilestone' => $tasksByMilestone,
            'milestones' => $milestones,
            'filter' => $filter,
            'userProjects' => $userProjects,
            'projectMembers' => $projectMembers,
            'isAdmin' => $isAdmin,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
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
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
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
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/tasks/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Request $request, Task $task): Response
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        /** @var User $user */
        $user = $this->getUser();
        $recentProjects = $this->projectRepository->findByUser($user);
        $canEdit = $this->isGranted('PROJECT_EDIT', $project);
        $canComment = $this->permissionChecker->hasPermission($user, 'comment.create', $task);

        $taskAttachments = $this->serializeAttachments(
            $this->attachmentRepository->findByAttachable('task', $task->getId()),
            $request->getBasePath()
        );

        return $this->render('task/show.html.twig', [
            'page_title' => $task->getTitle(),
            'task' => $task,
            'project' => $project,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'canEdit' => $canEdit,
            'canComment' => $canComment,
            'taskAttachments' => $taskAttachments,
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
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
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

        // Notify assignees of status change
        $notifType = $newStatus === TaskStatus::COMPLETED
            ? NotificationType::TASK_COMPLETED
            : NotificationType::TASK_STATUS_CHANGED;
        foreach ($task->getAssignees() as $assignee) {
            $this->notificationService->notify(
                $assignee->getUser(),
                $notifType,
                $user,
                'task',
                $task->getId(),
                $task->getTitle(),
                ['oldStatus' => $oldStatus->label(), 'newStatus' => $newStatus->label()],
            );
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $newStatus->value,
            'statusLabel' => $newStatus->label(),
        ]);
    }

    #[Route('/tasks/{id}/priority', name: 'app_task_update_priority', methods: ['POST'])]
    public function updatePriority(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $newPriorityValue = $data['priority'] ?? null;

        if (!$newPriorityValue) {
            return $this->json(['error' => 'Priority is required'], Response::HTTP_BAD_REQUEST);
        }

        $newPriority = TaskPriority::tryFrom($newPriorityValue);
        if (!$newPriority) {
            return $this->json(['error' => 'Invalid priority'], Response::HTTP_BAD_REQUEST);
        }

        $oldPriority = $task->getPriority();
        $task->setPriority($newPriority);

        $this->activityService->logTaskPriorityChanged(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            $oldPriority->label(),
            $newPriority->label()
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'priority' => $newPriority->value,
            'priorityLabel' => $newPriority->label(),
        ]);
    }

    #[Route('/tasks/{id}/milestone', name: 'app_task_update_milestone', methods: ['POST'])]
    public function updateMilestone(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $newMilestoneId = $data['milestone'] ?? null;

        if (!$newMilestoneId) {
            return $this->json(['error' => 'Milestone is required'], Response::HTTP_BAD_REQUEST);
        }

        $newMilestone = $this->milestoneRepository->find($newMilestoneId);
        if (!$newMilestone) {
            return $this->json(['error' => 'Invalid milestone'], Response::HTTP_BAD_REQUEST);
        }

        // Ensure the milestone belongs to the same project
        if ($newMilestone->getProject()->getId()->toString() !== $project->getId()->toString()) {
            return $this->json(['error' => 'Milestone does not belong to this project'], Response::HTTP_BAD_REQUEST);
        }

        $oldMilestone = $task->getMilestone();
        $task->setMilestone($newMilestone);

        $this->activityService->logTaskMilestoneChanged(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            $oldMilestone ? $oldMilestone->getName() : 'None',
            $newMilestone->getName()
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'milestone' => $newMilestone->getId()->toString(),
            'milestoneName' => $newMilestone->getName(),
        ]);
    }

    #[Route('/tasks/{id}/panel', name: 'app_task_panel', methods: ['GET'])]
    public function panel(Request $request, Task $task): Response
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);
        $canEdit = $this->isGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();
        $canComment = $this->permissionChecker->hasPermission($user, 'comment.create', $task);

        $basePath = $request->getBasePath();
        $taskAttachments = $this->serializeAttachments(
            $this->attachmentRepository->findByAttachable('task', $task->getId()),
            $basePath
        );

        return $this->render('task/_panel.html.twig', [
            'task' => $task,
            'project' => $project,
            'canEdit' => $canEdit,
            'canComment' => $canComment,
            'taskAttachments' => $taskAttachments,
        ]);
    }

    #[Route('/tasks/{id}/activity', name: 'app_task_activity', methods: ['GET'])]
    public function activity(Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        $activities = $this->activityRepository->findByTask($task->getId());

        $activityData = [];
        foreach ($activities as $activity) {
            $user = $activity->getUser();
            $action = $activity->getAction();
            $metadata = $activity->getMetadata() ?? [];

            $activityData[] = [
                'id' => $activity->getId()->toString(),
                'action' => $action->value,
                'actionLabel' => $this->formatActionLabel($action, $metadata),
                'user' => [
                    'fullName' => $user->getFullName(),
                    'initials' => strtoupper(substr($user->getFirstName(), 0, 1) . substr($user->getLastName(), 0, 1)),
                ],
                'metadata' => $metadata,
                'createdAt' => $activity->getCreatedAt()->format('M d, H:i'),
            ];
        }

        return $this->json(['activities' => $activityData]);
    }

    private function formatActionLabel(\App\Enum\ActivityAction $action, array $metadata): string
    {
        return match($action) {
            \App\Enum\ActivityAction::CREATED => 'created this task',
            \App\Enum\ActivityAction::UPDATED => $this->formatUpdatedLabel($metadata),
            \App\Enum\ActivityAction::STATUS_CHANGED => 'changed status',
            \App\Enum\ActivityAction::PRIORITY_CHANGED => 'changed priority',
            \App\Enum\ActivityAction::MILESTONE_CHANGED => 'moved to milestone',
            \App\Enum\ActivityAction::ASSIGNED => 'assigned',
            \App\Enum\ActivityAction::UNASSIGNED => 'unassigned',
            \App\Enum\ActivityAction::COMMENTED => 'commented',
            default => $action->label(),
        };
    }

    private function formatUpdatedLabel(array $metadata): string
    {
        if (isset($metadata['changes']['title'])) {
            return 'changed title';
        }
        if (isset($metadata['changes']['dueDate'])) {
            return 'changed due date';
        }
        if (isset($metadata['changes']['startDate'])) {
            return 'changed start date';
        }
        return 'updated';
    }

    #[Route('/tasks/reorder', name: 'app_task_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $taskIds = $data['taskIds'] ?? [];

        if (empty($taskIds) || !is_array($taskIds)) {
            return $this->json(['error' => 'Task IDs array is required'], Response::HTTP_BAD_REQUEST);
        }

        // Fetch all tasks and verify they belong to the same project
        $tasks = [];
        $project = null;

        foreach ($taskIds as $taskId) {
            $task = $this->taskRepository->find($taskId);
            if (!$task) {
                return $this->json(['error' => 'Task not found: ' . $taskId], Response::HTTP_BAD_REQUEST);
            }

            $taskProject = $task->getProject();

            if ($project === null) {
                $project = $taskProject;
                $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);
            } elseif ($taskProject->getId()->toString() !== $project->getId()->toString()) {
                return $this->json(['error' => 'All tasks must belong to the same project'], Response::HTTP_BAD_REQUEST);
            }

            $tasks[] = $task;
        }

        // Update positions based on array order
        foreach ($tasks as $index => $task) {
            $task->setPosition($index);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'updated' => count($tasks),
        ]);
    }

    #[Route('/tasks/{id}/title', name: 'app_task_update_title', methods: ['POST'])]
    public function updateTitle(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $newTitle = trim($data['title'] ?? '');

        if (empty($newTitle)) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newTitle) > 255) {
            return $this->json(['error' => 'Title must be 255 characters or less'], Response::HTTP_BAD_REQUEST);
        }

        $oldTitle = $task->getTitle();
        $task->setTitle($newTitle);

        $this->activityService->logTaskUpdated(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            ['title' => ['from' => $oldTitle, 'to' => $newTitle]]
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'title' => $newTitle,
        ]);
    }

    #[Route('/tasks/{id}/description', name: 'app_task_update_description', methods: ['POST'])]
    public function updateDescription(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $newDescription = $data['description'] ?? null;

        // Sanitize HTML to prevent XSS
        $newDescription = $this->htmlSanitizer->sanitize($newDescription);

        $task->setDescription($newDescription);

        $this->activityService->logTaskUpdated(
            $project,
            $user,
            $task->getId(),
            $task->getTitle()
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'description' => $newDescription,
        ]);
    }

    #[Route('/tasks/{id}/due-date', name: 'app_task_update_due_date', methods: ['POST'])]
    public function updateDueDate(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $dueDateValue = $data['dueDate'] ?? null;

        $newDueDate = null;
        if ($dueDateValue) {
            try {
                $newDueDate = new \DateTimeImmutable($dueDateValue);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validate: due date cannot be earlier than start date
        $startDate = $task->getStartDate();
        if ($startDate !== null && $newDueDate !== null && $newDueDate < $startDate) {
            return $this->json(['error' => 'Due date cannot be earlier than start date'], Response::HTTP_BAD_REQUEST);
        }

        $oldDueDate = $task->getDueDate();
        $task->setDueDate($newDueDate);

        $this->activityService->logTaskUpdated(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            ['dueDate' => [
                'from' => $oldDueDate ? $oldDueDate->format('Y-m-d') : null,
                'to' => $newDueDate ? $newDueDate->format('Y-m-d') : null
            ]]
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'dueDate' => $newDueDate ? $newDueDate->format('Y-m-d') : null,
            'dueDateFormatted' => $newDueDate ? $newDueDate->format('M d, Y') : null,
            'isOverdue' => $task->isOverdue(),
        ]);
    }

    #[Route('/tasks/{id}/start-date', name: 'app_task_update_start_date', methods: ['POST'])]
    public function updateStartDate(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $startDateValue = $data['startDate'] ?? null;

        $newStartDate = null;
        if ($startDateValue) {
            try {
                $newStartDate = new \DateTimeImmutable($startDateValue);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validate: due date cannot be earlier than start date
        $dueDate = $task->getDueDate();
        if ($newStartDate !== null && $dueDate !== null && $dueDate < $newStartDate) {
            return $this->json(['error' => 'Due date cannot be earlier than start date'], Response::HTTP_BAD_REQUEST);
        }

        $oldStartDate = $task->getStartDate();
        $task->setStartDate($newStartDate);

        $this->activityService->logTaskUpdated(
            $project,
            $user,
            $task->getId(),
            $task->getTitle(),
            ['startDate' => [
                'from' => $oldStartDate ? $oldStartDate->format('Y-m-d') : null,
                'to' => $newStartDate ? $newStartDate->format('Y-m-d') : null
            ]]
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'startDate' => $newStartDate ? $newStartDate->format('Y-m-d') : null,
            'startDateFormatted' => $newStartDate ? $newStartDate->format('M d, Y') : null,
        ]);
    }

    #[Route('/tasks/{id}/assignees', name: 'app_task_update_assignees', methods: ['POST'])]
    public function updateAssignees(Request $request, Task $task): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null; // 'add' or 'remove'
        $userId = $data['userId'] ?? null;

        if (!$action || !in_array($action, ['add', 'remove'])) {
            return $this->json(['error' => 'Action must be "add" or "remove"'], Response::HTTP_BAD_REQUEST);
        }

        if (!$userId) {
            return $this->json(['error' => 'User ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $targetUser = $this->userRepository->find($userId);
        if (!$targetUser) {
            return $this->json(['error' => 'User not found'], Response::HTTP_BAD_REQUEST);
        }

        if ($action === 'add') {
            // Check if already assigned
            foreach ($task->getAssignees() as $assignee) {
                if ($assignee->getUser()->getId()->toString() === $targetUser->getId()->toString()) {
                    return $this->json(['error' => 'User is already assigned'], Response::HTTP_BAD_REQUEST);
                }
            }

            $assignee = new TaskAssignee();
            $assignee->setTask($task);
            $assignee->setUser($targetUser);
            $assignee->setAssignedBy($user);

            $this->entityManager->persist($assignee);
            $task->addAssignee($assignee);

            $this->activityService->logTaskAssigned(
                $project,
                $user,
                $task->getId(),
                $task->getTitle(),
                $targetUser->getFullName()
            );

            $this->notificationService->notify(
                $targetUser,
                NotificationType::TASK_ASSIGNED,
                $user,
                'task',
                $task->getId(),
                $task->getTitle(),
                ['projectName' => $project->getName()],
            );
        } else {
            // Remove assignee
            $assigneeToRemove = null;
            foreach ($task->getAssignees() as $assignee) {
                if ($assignee->getUser()->getId()->toString() === $targetUser->getId()->toString()) {
                    $assigneeToRemove = $assignee;
                    break;
                }
            }

            if (!$assigneeToRemove) {
                return $this->json(['error' => 'User is not assigned to this task'], Response::HTTP_BAD_REQUEST);
            }

            $task->removeAssignee($assigneeToRemove);
            $this->entityManager->remove($assigneeToRemove);

            $this->activityService->logTaskUnassigned(
                $project,
                $user,
                $task->getId(),
                $task->getTitle(),
                $targetUser->getFullName()
            );

            $this->notificationService->notify(
                $targetUser,
                NotificationType::TASK_UNASSIGNED,
                $user,
                'task',
                $task->getId(),
                $task->getTitle(),
                ['projectName' => $project->getName()],
            );
        }

        $this->entityManager->flush();

        // Return updated assignees list
        $basePath = $request->getBasePath();
        $assignees = [];
        foreach ($task->getAssignees() as $assignee) {
            $user = $assignee->getUser();
            $assignees[] = [
                'id' => $user->getId()->toString(),
                'fullName' => $user->getFullName(),
                'initials' => strtoupper(substr($user->getFirstName(), 0, 1) . substr($user->getLastName(), 0, 1)),
                'avatar' => $user->getAvatar() ? $basePath . '/uploads/avatars/' . $user->getAvatar() : null,
            ];
        }

        return $this->json([
            'success' => true,
            'assignees' => $assignees,
        ]);
    }

    #[Route('/tasks/{id}/subtasks', name: 'app_task_create_subtask', methods: ['POST'])]
    public function createSubtask(Request $request, Task $parentTask): JsonResponse
    {
        $project = $parentTask->getProject();
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        if ($parentTask->getDepth() >= 2) {
            return $this->json(['error' => 'Maximum subtask depth (3 levels) reached'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($title) > 255) {
            return $this->json(['error' => 'Title must be 255 characters or less'], Response::HTTP_BAD_REQUEST);
        }

        $subtask = new Task();
        $subtask->setTitle($title);
        $subtask->setMilestone($parentTask->getMilestone());
        $subtask->setParent($parentTask);

        $maxPosition = $this->taskRepository->findMaxPositionInMilestone($parentTask->getMilestone());
        $subtask->setPosition($maxPosition + 1);

        $this->entityManager->persist($subtask);

        $this->activityService->logTaskCreated(
            $project,
            $user,
            $subtask->getId(),
            $subtask->getTitle()
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'subtask' => [
                'id' => $subtask->getId()->toString(),
                'title' => $subtask->getTitle(),
                'status' => [
                    'value' => $subtask->getStatus()->value,
                    'label' => $subtask->getStatus()->label(),
                ],
                'priority' => [
                    'value' => $subtask->getPriority()->value,
                    'label' => $subtask->getPriority()->label(),
                ],
            ],
        ]);
    }

    #[Route('/projects/{projectId}/tasks/create-panel', name: 'app_task_create_panel', methods: ['GET'])]
    public function createPanel(string $projectId): Response
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        return $this->render('task/_create_panel.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/projects/{projectId}/tasks/json', name: 'app_task_create_json', methods: ['POST'])]
    public function createJson(Request $request, string $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        $milestoneId = $data['milestone'] ?? null;
        if (empty($milestoneId)) {
            return $this->json(['error' => 'Milestone is required'], Response::HTTP_BAD_REQUEST);
        }

        $milestone = $this->milestoneRepository->find($milestoneId);
        if (!$milestone || $milestone->getProject()->getId()->toString() !== $project->getId()->toString()) {
            return $this->json(['error' => 'Invalid milestone'], Response::HTTP_BAD_REQUEST);
        }

        // Create the task
        $task = new Task();
        $task->setTitle($title);
        $task->setMilestone($milestone);

        // Optional fields
        if (!empty($data['status'])) {
            $status = TaskStatus::tryFrom($data['status']);
            if ($status) {
                $task->setStatus($status);
            }
        }

        if (!empty($data['priority'])) {
            $priority = TaskPriority::tryFrom($data['priority']);
            if ($priority) {
                $task->setPriority($priority);
            }
        }

        if (!empty($data['dueDate'])) {
            try {
                $task->setDueDate(new \DateTimeImmutable($data['dueDate']));
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        if (!empty($data['description'])) {
            $task->setDescription(trim($data['description']));
        }

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

        return $this->json([
            'success' => true,
            'task' => [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => [
                    'value' => $task->getStatus()->value,
                    'label' => $task->getStatus()->label(),
                ],
                'priority' => [
                    'value' => $task->getPriority()->value,
                    'label' => $task->getPriority()->label(),
                ],
                'milestoneId' => $milestone->getId()->toString(),
                'milestone' => [
                    'id' => $milestone->getId()->toString(),
                    'name' => $milestone->getName(),
                ],
                'dueDate' => $task->getDueDate()?->format('Y-m-d'),
                'position' => $task->getPosition(),
            ],
        ]);
    }

    #[Route('/projects/{projectId}/members', name: 'app_project_members_list', methods: ['GET'])]
    public function getProjectMembers(string $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        $members = [];
        foreach ($project->getMembers() as $member) {
            $members[] = [
                'id' => $member->getUser()->getId()->toString(),
                'fullName' => $member->getUser()->getFullName(),
                'initials' => strtoupper(substr($member->getUser()->getFirstName(), 0, 1) . substr($member->getUser()->getLastName(), 0, 1)),
                'email' => $member->getUser()->getEmail(),
            ];
        }

        return $this->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    private function serializeAttachments(array $attachments, string $basePath): array
    {
        return array_map(function (\App\Entity\Attachment $a) use ($basePath) {
            return [
                'id' => $a->getId()->toString(),
                'originalName' => $a->getOriginalName(),
                'mimeType' => $a->getMimeType(),
                'fileSize' => $a->getFileSize(),
                'humanFileSize' => $a->getHumanFileSize(),
                'isImage' => $a->isImage(),
                'downloadUrl' => $basePath . '/attachments/' . $a->getId()->toString() . '/download',
                'previewUrl' => $a->isImage() ? $basePath . '/attachments/' . $a->getId()->toString() . '/preview' : null,
                'createdAt' => $a->getCreatedAt()->format('M d, H:i'),
            ];
        }, $attachments);
    }
}
