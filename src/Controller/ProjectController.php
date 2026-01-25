<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Form\ProjectFormType;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\ActivityService;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityService $activityService,
    ) {
    }

    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projects = $this->projectRepository->findByUser($user);

        return $this->render('project/index.html.twig', [
            'page_title' => 'Projects',
            'projects' => $projects,
            'recent_projects' => array_slice($projects, 0, 5),
        ]);
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

            // Add owner as admin member
            $member = new ProjectMember();
            $member->setUser($user);
            $member->setRole(ProjectRole::ADMIN);
            $project->addMember($member);

            $this->entityManager->persist($project);

            // Log activity
            $this->activityService->logProjectCreated($project, $user);

            $this->entityManager->flush();

            $this->addFlash('success', 'Project created successfully.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('project/new.html.twig', [
            'page_title' => 'New Project',
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    #[IsGranted('PROJECT_VIEW', subject: 'project')]
    public function show(Project $project): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $recentProjects = $this->projectRepository->findByUser($user);
        $tasks = $this->taskRepository->findByProject($project);

        return $this->render('project/show.html.twig', [
            'page_title' => $project->getName(),
            'project' => $project,
            'tasks' => $tasks,
            'recent_projects' => array_slice($recentProjects, 0, 5),
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

        $recentProjects = $this->projectRepository->findByUser($user);

        return $this->render('project/edit.html.twig', [
            'page_title' => 'Edit ' . $project->getName(),
            'project' => $project,
            'form' => $form,
            'recent_projects' => array_slice($recentProjects, 0, 5),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    #[IsGranted('PROJECT_DELETE', subject: 'project')]
    public function delete(Request $request, Project $project): Response
    {
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
            ],
        ], $activities);

        return $this->json([
            'activities' => $data,
            'hasMore' => $hasMore,
            'page' => $page,
        ]);
    }
}
