<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/users/{id}')]
class UserProfileController extends AbstractController
{
    #[Route('/hover-card', name: 'app_user_hover_card', methods: ['GET'])]
    public function hoverCard(User $user, UserRepository $userRepo): JsonResponse
    {
        // Simple permission: must be logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Get active projects count
        $activeProjectsCount = $userRepo->getActiveProjectsCount($user);

        return $this->json([
            'id' => $user->getId()->toString(),
            'fullName' => $user->getFullName(),
            'jobTitle' => $user->getJobTitle(),
            'department' => $user->getDepartment(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
            'initials' => $user->getInitials(),
            'activeProjectsCount' => $activeProjectsCount,
            'canViewProfile' => true
        ]);
    }

    #[Route('/profile', name: 'app_user_profile', methods: ['GET'])]
    public function show(User $user, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        // Simple permission: must be logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $canEdit = $this->getUser() === $user;

        // Get active projects (owned + member)
        $ownedProjects = $em->createQueryBuilder()
            ->select('p')
            ->from('App\Entity\Project', 'p')
            ->where('p.owner = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $memberProjects = $em->createQueryBuilder()
            ->select('p')
            ->from('App\Entity\Project', 'p')
            ->join('p.members', 'm')
            ->where('m.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // Combine and dedupe
        $allProjects = array_merge($ownedProjects, $memberProjects);
        $projectsById = [];
        foreach ($allProjects as $project) {
            $projectsById[$project->getId()->toString()] = $project;
        }
        $projects = array_values($projectsById);

        // Get recent activity
        $recentActivity = $em->createQueryBuilder()
            ->select('a')
            ->from('App\Entity\Activity', 'a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Calculate stats
        $totalProjects = $userRepo->getActiveProjectsCount($user);

        $completedTasks = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT ta.task)')
            ->from('App\Entity\TaskAssignee', 'ta')
            ->join('ta.task', 't')
            ->where('ta.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $activeTasks = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT ta.task)')
            ->from('App\Entity\TaskAssignee', 'ta')
            ->join('ta.task', 't')
            ->where('ta.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['todo', 'in-progress', 'in-review'])
            ->getQuery()
            ->getSingleScalarResult();

        $overdueTasks = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT ta.task)')
            ->from('App\Entity\TaskAssignee', 'ta')
            ->join('ta.task', 't')
            ->where('ta.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->andWhere('t.dueDate < :today')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['todo', 'in-progress', 'in-review'])
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('user_profile/show.html.twig', [
            'profileUser' => $user,
            'canEdit' => $canEdit,
            'projects' => $projects,
            'recentActivity' => $recentActivity,
            'stats' => [
                'totalProjects' => $totalProjects,
                'completedTasks' => $completedTasks,
                'activeTasks' => $activeTasks,
                'overdueTasks' => $overdueTasks,
            ],
        ]);
    }
}
