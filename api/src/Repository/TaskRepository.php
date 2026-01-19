<?php

namespace App\Repository;

use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Task;
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
        return $this->findBy(['milestone' => $milestone, 'parent' => null], ['position' => 'ASC']);
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
        return $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->join('t.milestone', 'm')
            ->join('m.project', 'p')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.priority', 'DESC');
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
        return $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->where('a.user = :user')
            ->andWhere('t.dueDate < :today')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findTasksDueToday(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('t')
            ->join('t.assignees', 'a')
            ->where('a.user = :user')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('t.dueDate < :tomorrow')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('completed', TaskStatus::COMPLETED)
            ->orderBy('t.priority', 'DESC')
            ->getQuery()
            ->getResult();
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
}
