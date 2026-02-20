<?php

namespace App\Repository;

use App\Entity\Milestone;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Milestone>
 */
class MilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Milestone::class);
    }

    /**
     * @return Milestone[]
     */
    public function findByProject(Project $project): array
    {
        return $this->findBy(['project' => $project], ['position' => 'ASC']);
    }

    public function findDefaultForProject(Project $project): ?Milestone
    {
        return $this->findOneBy(['project' => $project, 'isDefault' => true]);
    }
}
