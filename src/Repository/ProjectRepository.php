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
            ->orderBy('p.position', 'ASC');
    }

    /**
     * @return Project[]
     */
    public function findAllOrderedByPosition(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * @return Project[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilderForUser($user)->getQuery()->getResult();
    }

    /**
     * Get recent projects for sidebar, filtering out hidden ones.
     *
     * @return Project[]
     */
    public function findRecentForUser(User $user, int $limit = 4): array
    {
        $recentIds = $user->getRecentProjectIds();

        if (empty($recentIds)) {
            return [];
        }

        $recentIds = array_slice($recentIds, 0, $limit);
        $projects = $this->findBy(['id' => $recentIds]);

        // Sort by the order in recentIds
        $idOrder = array_flip($recentIds);
        usort($projects, fn(Project $a, Project $b) =>
            ($idOrder[(string) $a->getId()] ?? 999) <=> ($idOrder[(string) $b->getId()] ?? 999)
        );

        return $projects;
    }

    /**
     * Get favourite projects for sidebar.
     *
     * @return Project[]
     */
    public function findFavouritesForUser(User $user): array
    {
        $favouriteIds = $user->getFavouriteProjectIds();

        if (empty($favouriteIds)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $favouriteIds)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the user's personal project.
     */
    public function findPersonalProjectForUser(User $user): ?Project
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :user')
            ->andWhere('p.isPersonal = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get hidden projects for user.
     *
     * @return Project[]
     */
    public function findHiddenForUser(User $user): array
    {
        $hiddenIds = $user->getHiddenProjectIds();

        if (empty($hiddenIds)) {
            return [];
        }

        // Only return projects user has access to
        return $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->where('p.id IN (:ids)')
            ->andWhere('p.owner = :user OR m.user = :user OR p.isPublic = true')
            ->setParameter('ids', $hiddenIds)
            ->setParameter('user', $user)
            ->distinct()
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
