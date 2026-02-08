<?php

namespace App\Repository;

use App\Entity\Changelog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Changelog>
 */
class ChangelogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Changelog::class);
    }

    /**
     * @return Changelog[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.releaseDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
