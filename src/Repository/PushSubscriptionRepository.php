<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * Find all push subscriptions for a user
     *
     * @return PushSubscription[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ps')
            ->where('ps.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ps.lastUsedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a subscription by endpoint
     */
    public function findByEndpoint(User $user, string $endpoint): ?PushSubscription
    {
        $hash = hash('sha256', $endpoint);

        return $this->createQueryBuilder('ps')
            ->where('ps.user = :user')
            ->andWhere('ps.endpointHash = :hash')
            ->setParameter('user', $user)
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete subscriptions that haven't been used in X days
     *
     * @param int $days Number of days of inactivity
     * @return int Number of deleted subscriptions
     */
    public function deleteStaleSubscriptions(int $days = 30): int
    {
        $cutoff = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('ps')
            ->delete()
            ->where('ps.lastUsedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
