<?php

namespace App\Repository;

use App\Entity\PendingRegistrationRequest;
use App\Enum\RegistrationRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PendingRegistrationRequest>
 */
class PendingRegistrationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PendingRegistrationRequest::class);
    }

    /**
     * @return PendingRegistrationRequest[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', RegistrationRequestStatus::PENDING)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', RegistrationRequestStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPendingByEmail(string $email): ?PendingRegistrationRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.email = :email')
            ->andWhere('r.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', RegistrationRequestStatus::PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PendingRegistrationRequest[]
     */
    public function findActioned(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status != :status')
            ->setParameter('status', RegistrationRequestStatus::PENDING)
            ->orderBy('r.reviewedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
