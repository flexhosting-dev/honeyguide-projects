<?php

namespace App\Repository;

use App\DTO\TaskFilterDTO;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskStatusType;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findByMilestone(Milestone $milestone): array
    {
        return $this->findBy(['milestone' => $milestone], ['position' => 'ASC']);
    }

    public function createQueryBuilderForProject(Project $project): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->join('t.milestone', 'm')
            ->where('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.position', 'ASC');
    }

    /**
     * @return Task[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilderForProject($project)->getQuery()->getResult();
    }

    public function createQueryBuilderForUserTasks(User $user): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->join('t.milestone', 'm')
            ->join('m.project', 'p')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.priority', 'DESC');

        // Exclude tasks from hidden projects
        $this->excludeHiddenProjects($qb, $user);

        return $qb;
    }

    /**
     * Helper to exclude tasks from user's hidden projects.
     */
    private function excludeHiddenProjects(QueryBuilder $qb, User $user): void
    {
        $hiddenProjectIds = $user->getHiddenProjectIds();
        if (!empty($hiddenProjectIds)) {
            // Ensure milestone alias exists
            if (!in_array('m', $qb->getAllAliases())) {
                $qb->join('t.milestone', 'm');
            }
            $qb->andWhere('m.project NOT IN (:hiddenProjectIds)')
                ->setParameter('hiddenProjectIds', $hiddenProjectIds);
        }
    }

    /**
     * @return Task[]
     */
    public function findUserTasks(User $user): array
    {
        return $this->createQueryBuilderForUserTasks($user)->getQuery()->getResult();
    }

    /**
     * @return Task[]
     */
    public function findOverdueTasks(User $user): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->join('t.milestone', 'm')
            ->leftJoin('t.statusType', 'st')
            ->where('a.user = :user')
            ->andWhere('t.dueDate < :today')
            ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completed))')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.dueDate', 'ASC');

        $this->excludeHiddenProjects($qb, $user);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Task[]
     */
    public function findTasksDueToday(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $qb = $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->join('t.milestone', 'm')
            ->leftJoin('t.statusType', 'st')
            ->where('a.user = :user')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('t.dueDate < :tomorrow')
            ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completed))')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.priority', 'DESC');

        $this->excludeHiddenProjects($qb, $user);

        return $qb->getQuery()->getResult();
    }

    public function getNextPosition(Milestone $milestone, ?TaskStatus $status = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.milestone = :milestone')
            ->setParameter('milestone', $milestone);

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return ($result ?? -1) + 1;
    }

    public function findMaxPositionInMilestone(Milestone $milestone): int
    {
        $result = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.milestone = :milestone')
            ->setParameter('milestone', $milestone)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function applyFilter(QueryBuilder $qb, TaskFilterDTO $filter): QueryBuilder
    {
        // Filter by statuses (using statusType relation or legacy enum)
        $statusSlugs = $filter->getEffectiveStatusSlugs();
        if (!empty($statusSlugs)) {
            // Join statusType if not already joined
            if (!in_array('st', $qb->getAllAliases())) {
                $qb->leftJoin('t.statusType', 'st');
            }
            // Filter by statusType slug OR legacy enum status value
            $qb->andWhere('(st.slug IN (:statusSlugs) OR (st IS NULL AND t.status IN (:statusSlugs)))')
                ->setParameter('statusSlugs', $statusSlugs);
        }

        // Filter by priorities
        if (!empty($filter->priorities)) {
            $qb->andWhere('t.priority IN (:priorities)')
                ->setParameter('priorities', $filter->priorities);
        }

        // Filter by assignees
        if (!empty($filter->assigneeIds)) {
            if (!in_array('a', $qb->getAllAliases())) {
                $qb->join('t.assignees', 'a');
            }
            $qb->andWhere('a.user IN (:assigneeIds)')
                ->setParameter('assigneeIds', $filter->assigneeIds);
        }

        // Filter by milestones
        if (!empty($filter->milestoneIds)) {
            $qb->andWhere('t.milestone IN (:milestoneIds)')
                ->setParameter('milestoneIds', $filter->milestoneIds);
        }

        // Filter by projects
        if (!empty($filter->projectIds)) {
            if (!in_array('m', $qb->getAllAliases())) {
                $qb->join('t.milestone', 'm');
            }
            $qb->andWhere('m.project IN (:projectIds)')
                ->setParameter('projectIds', $filter->projectIds);
        }

        // Filter by due date
        if ($filter->dueFilter !== null) {
            $today = new \DateTimeImmutable('today');

            switch ($filter->dueFilter) {
                case 'overdue':
                    // Join statusType if not already joined
                    if (!in_array('st', $qb->getAllAliases())) {
                        $qb->leftJoin('t.statusType', 'st');
                    }
                    $qb->andWhere('t.dueDate < :today')
                        ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completedStatus))')
                        ->setParameter('today', $today)
                        ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
                        ->setParameter('completedStatus', TaskStatus::COMPLETED);
                    break;

                case 'today':
                    $tomorrow = $today->modify('+1 day');
                    $qb->andWhere('t.dueDate >= :today')
                        ->andWhere('t.dueDate < :tomorrow')
                        ->setParameter('today', $today)
                        ->setParameter('tomorrow', $tomorrow);
                    break;

                case 'this_week':
                    $endOfWeek = $today->modify('sunday this week')->setTime(23, 59, 59);
                    $qb->andWhere('t.dueDate >= :today')
                        ->andWhere('t.dueDate <= :endOfWeek')
                        ->setParameter('today', $today)
                        ->setParameter('endOfWeek', $endOfWeek);
                    break;

                case 'next_week':
                    $startNextWeek = $today->modify('monday next week');
                    $endNextWeek = $startNextWeek->modify('sunday this week')->setTime(23, 59, 59);
                    $qb->andWhere('t.dueDate >= :startNextWeek')
                        ->andWhere('t.dueDate <= :endNextWeek')
                        ->setParameter('startNextWeek', $startNextWeek)
                        ->setParameter('endNextWeek', $endNextWeek);
                    break;

                case 'no_date':
                    $qb->andWhere('t.dueDate IS NULL');
                    break;

                case 'custom':
                    if ($filter->dueDateFrom) {
                        $from = new \DateTimeImmutable($filter->dueDateFrom);
                        $qb->andWhere('t.dueDate >= :dueDateFrom')
                            ->setParameter('dueDateFrom', $from);
                    }
                    if ($filter->dueDateTo) {
                        $to = new \DateTimeImmutable($filter->dueDateTo);
                        $qb->andWhere('t.dueDate <= :dueDateTo')
                            ->setParameter('dueDateTo', $to->setTime(23, 59, 59));
                    }
                    break;
            }
        }

        // Search in title and description
        if ($filter->search !== null && $filter->search !== '') {
            $qb->andWhere('(t.title LIKE :search OR t.description LIKE :search)')
                ->setParameter('search', '%' . $filter->search . '%');
        }

        return $qb;
    }

    /**
     * @return Task[]
     */
    public function findUserTasksFiltered(User $user, TaskFilterDTO $filter): array
    {
        $qb = $this->createQueryBuilderForUserTasks($user);
        $this->applyFilter($qb, $filter);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all tasks from the given projects with filters applied.
     *
     * @param Project[] $projects
     * @return Task[]
     */
    public function findProjectTasksFiltered(array $projects, TaskFilterDTO $filter): array
    {
        if (empty($projects)) {
            return [];
        }

        $qb = $this->createQueryBuilder('t')
            ->join('t.milestone', 'm')
            ->where('m.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->orderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.priority', 'DESC');

        $this->applyFilter($qb, $filter);

        return $qb->getQuery()->getResult();
    }

    public function countOverdueByProject(Project $project): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->join('t.milestone', 'm')
            ->leftJoin('t.statusType', 'st')
            ->where('m.project = :project')
            ->andWhere('t.dueDate < :today')
            ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completed))')
            ->setParameter('project', $project)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNextDeadlineByProject(Project $project): ?Task
    {
        return $this->createQueryBuilder('t')
            ->join('t.milestone', 'm')
            ->leftJoin('t.statusType', 'st')
            ->where('m.project = :project')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completed))')
            ->setParameter('project', $project)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.dueDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Task[]
     */
    public function findUserTasksInProject(User $user, Project $project, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->join('t.milestone', 'm')
            ->leftJoin('t.statusType', 'st')
            ->where('a.user = :user')
            ->andWhere('m.project = :project')
            ->andWhere('(st.parentType != :closedType OR (st IS NULL AND t.status != :completed))')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->setParameter('closedType', TaskStatusType::PARENT_TYPE_CLOSED)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.priority', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all tasks accessible to the user (for All Tasks page).
     * For admins: all tasks in the system (excluding their hidden projects)
     * For regular users: all tasks from projects they are members of
     *
     * @return Task[]
     */
    public function findAllTasksFiltered(User $user, TaskFilterDTO $filter, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            // Admin can see all tasks, but only their own personal project (not others')
            $qb = $this->createQueryBuilder('t')
                ->join('t.milestone', 'm')
                ->join('m.project', 'p')
                ->andWhere('p.isPersonal = false OR p.owner = :user')
                ->setParameter('user', $user)
                ->orderBy('t.dueDate', 'ASC')
                ->addOrderBy('t.priority', 'DESC');
        } else {
            // Regular user sees tasks from projects they are members of, own, or are public
            // Excludes other users' personal projects
            $projectRepo = $this->getEntityManager()->getRepository(Project::class);
            $accessibleProjects = $projectRepo->createQueryBuilder('ap')
                ->select('ap.id')
                ->leftJoin('ap.members', 'apm')
                ->where('ap.owner = :user')
                ->orWhere('apm.user = :user')
                ->orWhere('ap.isPublic = true')
                ->andWhere('ap.isPersonal = false OR ap.owner = :user')
                ->setParameter('user', $user)
                ->distinct()
                ->getQuery()
                ->getSingleColumnResult();

            if (empty($accessibleProjects)) {
                return [];
            }

            $qb = $this->createQueryBuilder('t')
                ->join('t.milestone', 'm')
                ->join('m.project', 'p')
                ->where('p.id IN (:accessibleProjects)')
                ->setParameter('accessibleProjects', $accessibleProjects)
                ->orderBy('t.dueDate', 'ASC')
                ->addOrderBy('t.priority', 'DESC');
        }

        // Exclude tasks from user's hidden projects
        $this->excludeHiddenProjects($qb, $user);

        $this->applyFilter($qb, $filter);

        return $qb->getQuery()->getResult();
    }
}
