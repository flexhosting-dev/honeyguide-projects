<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function createQueryBuilderForUser(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->where('p.owner = :user')
            ->orWhere('m.user = :user')
            ->orWhere('p.isPublic = true')
            ->setParameter('user', $user)
            ->distinct()
            ->orderBy('p.createdAt', 'DESC');
    }

    /**
     * @return Project[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilderForUser($user)->getQuery()->getResult();
    }
}
