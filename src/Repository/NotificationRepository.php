<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Notification[]
     */
    public function findRecent(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findByUserFiltered(User $user, ?string $filter = null, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($filter === 'unread') {
            $qb->andWhere('n.readAt IS NULL');
        } elseif ($filter === 'mentions') {
            $qb->andWhere('n.type = :mentionType')
                ->setParameter('mentionType', 'mentioned');
        } elseif ($filter === 'assignments') {
            $qb->andWhere('n.type IN (:assignTypes)')
                ->setParameter('assignTypes', ['task_assigned', 'task_unassigned']);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByUserFiltered(User $user, ?string $filter = null): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->setParameter('user', $user);

        if ($filter === 'unread') {
            $qb->andWhere('n.readAt IS NULL');
        } elseif ($filter === 'mentions') {
            $qb->andWhere('n.type = :mentionType')
                ->setParameter('mentionType', 'mentioned');
        } elseif ($filter === 'assignments') {
            $qb->andWhere('n.type IN (:assignTypes)')
                ->setParameter('assignTypes', ['task_assigned', 'task_unassigned']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function markAllReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteReadOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.readAt IS NOT NULL')
            ->andWhere('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    public function deleteAllOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
