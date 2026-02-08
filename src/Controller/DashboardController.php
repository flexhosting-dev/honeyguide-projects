<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TaskRepository $taskRepository,
        private readonly ActivityRepository $activityRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $projects = $this->projectRepository->findByUser($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        $userTasks = $this->taskRepository->findUserTasks($user);
        $overdueTasks = $this->taskRepository->findOverdueTasks($user);
        $tasksDueToday = $this->taskRepository->findTasksDueToday($user);

        $tasksByStatus = [
            'todo' => 0,
            'in_progress' => 0,
            'in_review' => 0,
            'completed' => 0,
        ];

        foreach ($userTasks as $task) {
            $status = $task->getStatus()->value;
            $tasksByStatus[$status]++;
        }

        $recentActivities = $this->activityRepository->findRecentForProjects($projectIds, 10);

        $upcomingTasks = array_filter($userTasks, function($task) {
            if ($task->getStatus() === TaskStatus::COMPLETED) {
                return false;
            }
            $dueDate = $task->getDueDate();
            if (!$dueDate) {
                return false;
            }
            $now = new \DateTimeImmutable('today');
            $nextWeek = $now->modify('+7 days');
            return $dueDate >= $now && $dueDate <= $nextWeek;
        });

        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Dashboard',
            'stats' => [
                'totalProjects' => count($projects),
                'totalTasks' => count($userTasks),
                'overdueTasks' => count($overdueTasks),
                'tasksDueToday' => count($tasksDueToday),
                'completedTasks' => $tasksByStatus['completed'],
            ],
            'tasksByStatus' => $tasksByStatus,
            'recentActivities' => $recentActivities,
            'upcomingTasks' => array_slice($upcomingTasks, 0, 5),
            'tasksDueToday' => $tasksDueToday,
            'recent_projects' => $this->projectRepository->findRecentForUser($user),
            'favourite_projects' => $this->projectRepository->findFavouritesForUser($user),
        ]);
    }
}
