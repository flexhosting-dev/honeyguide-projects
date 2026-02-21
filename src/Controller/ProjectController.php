<?php

namespace App\Controller;

use App\DTO\TaskFilterDTO;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Form\ProjectFormType;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\TaskRepository;
use App\Enum\Permission;
use App\Service\ActivityService;
use App\Service\HtmlSanitizer;
use App\Service\PermissionChecker;
use App\Service\TaskStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TaskRepository $taskRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PermissionChecker $permissionChecker,
        private readonly TaskStatusService $taskStatusService,
    ) {
    }

    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $allProjects = $this->projectRepository->findByUser($user);
        $hiddenProjectIds = $user->getHiddenProjectIds();

        // Separate personal project from team projects, filtering out hidden projects
        $personalProject = null;
        $projects = [];
        foreach ($allProjects as $project) {
            $projectId = (string) $project->getId();
            if ($project->isPersonal() && $project->getOwner() === $user) {
                $personalProject = $project;
            } elseif (!in_array($projectId, $hiddenProjectIds, true)) {
                $projects[] = $project;
            }
        }

        // Fetch hidden projects separately
        $hiddenProjects = $this->projectRepository->findHiddenForUser($user);

        return $this->render('project/index.html.twig', [
            'page_title' => 'Projects',
            'projects' => $projects,
            'personalProject' => $personalProject,
            'hiddenProjects' => $hiddenProjects,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/json', name: 'app_project_list_json', methods: ['GET'])]
    public function listJson(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $allProjects = $this->projectRepository->findByUser($user);

        $projects = [];
        foreach ($allProjects as $project) {
            // Only include projects where user has PROJECT_EDIT permission
            if ($this->permissionChecker->hasPermission($user, $project, Permission::PROJECT_EDIT)) {
                $projects[] = [
                    'id' => $project->getId()->toString(),
                    'name' => $project->getName(),
                    'isPersonal' => $project->isPersonal(),
                ];
            }
        }

        return new JsonResponse($projects);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = new Project();
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setOwner($user);

            // Add owner as manager member
            $managerRole = $this->roleRepository->findBySlug('project-manager');
            if (!$managerRole) {
                throw new \RuntimeException('Project Manager role not found. Please run fixtures.');
            }

            $member = new ProjectMember();
            $member->setUser($user);
            $member->setRole($managerRole);
            $project->addMember($member);

            $this->entityManager->persist($project);

            // Create default "General" milestone
            $milestone = new Milestone();
            $milestone->setName('General');
            $milestone->setProject($project);
            $milestone->setPosition(0);
            $milestone->setIsDefault(true);

            $this->entityManager->persist($milestone);

            // Log activity
            $this->activityService->logProjectCreated($project, $user);

            $this->entityManager->flush();

            $this->addFlash('success', 'Project created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig', [
            'page_title' => 'New Project',
            'form' => $form,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    #[IsGranted('PROJECT_VIEW', subject: 'project')]
    public function show(Request $request, Project $project): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Record project visit for recent projects sidebar
        $user->recordProjectVisit((string) $project->getId());
        $this->entityManager->flush();

        // Apply filters to tasks
        $filter = TaskFilterDTO::fromRequest($request);
        $tasks = $this->taskRepository->findProjectTasksFiltered([$project], $filter);

        // Get project members for assignee filter
        $projectMembers = [];
        $seenUserIds = [];
        foreach ($project->getMembers() as $member) {
            $memberUser = $member->getUser();
            $userId = $memberUser->getId()->toString();
            if (!isset($seenUserIds[$userId])) {
                $seenUserIds[$userId] = true;
                $projectMembers[] = $memberUser;
            }
        }
        usort($projectMembers, fn($a, $b) => $a->getFullName() <=> $b->getFullName());

        // Group tasks by status for kanban
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
        // Pre-populate with all project milestones so empty ones show as columns
        foreach ($project->getMilestones() as $ms) {
            $tasksByMilestone[$ms->getId()->toString()] = [];
        }

        foreach ($tasks as $task) {
            $status = $task->getStatus()->value;
            $tasksByStatus[$status][] = $task;

            $priority = $task->getPriority()->value;
            $tasksByPriority[$priority][] = $task;

            $milestone = $task->getMilestone();
            if ($milestone) {
                $msId = $milestone->getId()->toString();
                $tasksByMilestone[$msId][] = $task;
            }
        }

        $projectRoles = $this->roleRepository->findProjectRoles();

        // Dashboard data for Overview tab
        $overdueCount = $this->taskRepository->countOverdueByProject($project);
        $nextDeadline = $this->taskRepository->findNextDeadlineByProject($project);
        $myTasks = $this->taskRepository->findUserTasksInProject($user, $project, 5);
        $recentActivities = $this->activityRepository->findBy(
            ['project' => $project],
            ['createdAt' => 'DESC'],
            5
        );

        // Permission flags for template
        $canEditProject = $this->permissionChecker->hasPermission($user, Permission::PROJECT_EDIT, $project);
        $canCreateMilestone = $this->permissionChecker->hasPermission($user, Permission::MILESTONE_CREATE, $project);
        $canCreateTask = $this->permissionChecker->hasPermission($user, Permission::TASK_CREATE, $project);
        $canManageMembers = $this->permissionChecker->hasPermission($user, Permission::PROJECT_MANAGE_MEMBERS, $project);
        $canEditMilestone = $this->permissionChecker->hasPermission($user, Permission::MILESTONE_EDIT, $project);

        // Get all status types for the frontend
        $allStatuses = $this->taskStatusService->getAllStatuses();

        return $this->render('project/show.html.twig', [
            'page_title' => $project->getName(),
            'project' => $project,
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'tasksByPriority' => $tasksByPriority,
            'tasksByMilestone' => $tasksByMilestone,
            'milestones' => $project->getMilestones(),
            'filter' => $filter,
            'projectMembers' => $projectMembers,
            'projectRoles' => $projectRoles,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
            'is_favourite' => $user->isFavouriteProject((string) $project->getId()),
            'overdueCount' => $overdueCount,
            'nextDeadline' => $nextDeadline,
            'myTasks' => $myTasks,
            'recentActivities' => $recentActivities,
            'canEditProject' => $canEditProject,
            'canCreateMilestone' => $canCreateMilestone,
            'canCreateTask' => $canCreateTask,
            'canManageMembers' => $canManageMembers,
            'canEditMilestone' => $canEditMilestone,
            'allStatuses' => $allStatuses,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    #[IsGranted('PROJECT_EDIT', subject: 'project')]
    public function edit(Request $request, Project $project): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->activityService->logProjectUpdated($project, $user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Project updated successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'page_title' => 'Edit ' . $project->getName(),
            'project' => $project,
            'form' => $form,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
            'is_favourite' => $user->isFavouriteProject((string) $project->getId()),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    #[IsGranted('PROJECT_DELETE', subject: 'project')]
    public function delete(Request $request, Project $project): Response
    {
        // Prevent deletion of personal projects
        if ($project->isPersonal()) {
            $this->addFlash('error', 'Personal projects cannot be deleted.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($project);
            $this->entityManager->flush();

            $this->addFlash('success', 'Project deleted successfully.');
        }

        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/{id}/feed', name: 'app_project_feed', methods: ['GET'])]
    #[IsGranted('PROJECT_VIEW', subject: 'project')]
    public function feed(Request $request, Project $project): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $activities = $this->activityRepository->findBy(
            ['project' => $project],
            ['createdAt' => 'DESC'],
            $limit + 1, // Fetch one extra to check if there are more
            $offset
        );

        $hasMore = count($activities) > $limit;
        if ($hasMore) {
            array_pop($activities); // Remove the extra item
        }

        $basePath = $request->getBasePath();
        $data = array_map(fn($activity) => [
            'id' => $activity->getId(),
            'description' => $activity->getDescription(),
            'createdAt' => $activity->getCreatedAt()->format('M d, H:i'),
            'user' => [
                'initials' => strtoupper(
                    substr($activity->getUser()->getFirstName(), 0, 1) .
                    substr($activity->getUser()->getLastName(), 0, 1)
                ),
                'name' => $activity->getUser()->getFirstName() . ' ' . $activity->getUser()->getLastName(),
                'avatar' => $activity->getUser()->getAvatar() ? $basePath . '/uploads/avatars/' . $activity->getUser()->getAvatar() : null,
            ],
        ], $activities);

        return $this->json([
            'activities' => $data,
            'hasMore' => $hasMore,
            'page' => $page,
        ]);
    }

    #[Route('/{id}/hide-from-recent', name: 'app_project_hide_recent', methods: ['POST'])]
    public function hideFromRecent(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->hideRecentProject((string) $project->getId());
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/toggle-favourite', name: 'app_project_toggle_favourite', methods: ['POST'])]
    public function toggleFavourite(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $projectId = (string) $project->getId();
        $isFavourite = $user->isFavouriteProject($projectId);

        if ($isFavourite) {
            $user->removeFavouriteProject($projectId);
        } else {
            $user->addFavouriteProject($projectId);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isFavourite' => !$isFavourite,
            'projectName' => $project->getName(),
        ]);
    }

    #[Route('/{id}/hide', name: 'app_project_hide', methods: ['POST'])]
    public function hide(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Prevent hiding personal projects
        if ($project->isPersonal()) {
            return $this->json([
                'success' => false,
                'error' => 'Personal projects cannot be hidden.',
            ], 400);
        }

        $projectId = (string) $project->getId();
        $user->hideProject($projectId);

        // Also remove from favourites and recent projects
        $user->removeFavouriteProject($projectId);
        $user->removeRecentProject($projectId);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'projectName' => $project->getName(),
        ]);
    }

    #[Route('/{id}/unhide', name: 'app_project_unhide', methods: ['POST'])]
    public function unhide(Request $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $projectId = (string) $project->getId();
        $user->unhideProject($projectId);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'projectName' => $project->getName(),
        ]);
    }

    #[Route('/{id}/members', name: 'app_project_members', methods: ['GET'])]
    #[IsGranted('PROJECT_VIEW', subject: 'project')]
    public function members(Project $project): JsonResponse
    {
        $members = [];

        // Add project owner
        $owner = $project->getOwner();
        $members[] = [
            'id' => (string) $owner->getId(),
            'fullName' => $owner->getFullName(),
            'initials' => $owner->getInitials(),
        ];

        // Add all project members
        foreach ($project->getMembers() as $member) {
            $memberUser = $member->getUser();
            // Avoid duplicate if owner is also a member
            if (!$owner->getId()->equals($memberUser->getId())) {
                $members[] = [
                    'id' => (string) $memberUser->getId(),
                    'fullName' => $memberUser->getFullName(),
                    'initials' => $memberUser->getInitials(),
                ];
            }
        }

        return $this->json(['members' => $members]);
    }

    #[Route('/{id}/description', name: 'app_project_update_description', methods: ['POST'])]
    #[IsGranted('PROJECT_EDIT', subject: 'project')]
    public function updateDescription(Request $request, Project $project): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newDescription = $this->htmlSanitizer->sanitize($data['description'] ?? null);

        $project->setDescription($newDescription);

        /** @var User $user */
        $user = $this->getUser();
        $this->activityService->logProjectUpdated($project, $user);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'description' => $newDescription,
        ]);
    }

    #[Route('/reorder', name: 'app_project_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isPortalAdmin()) {
            return $this->json(['success' => false, 'error' => 'Admin only'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $projectIds = $data['projectIds'] ?? [];

            if (empty($projectIds)) {
                return $this->json(['success' => false, 'error' => 'No project IDs provided'], 400);
            }

            $updated = 0;
            foreach ($projectIds as $index => $projectId) {
                $project = $this->projectRepository->find($projectId);
                if ($project) {
                    $project->setPosition($index);
                    $updated++;
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'updated' => $updated,
                'total' => count($projectIds)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
