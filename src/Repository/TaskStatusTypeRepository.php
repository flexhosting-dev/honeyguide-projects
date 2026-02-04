<?php

namespace App\Repository;

use App\Entity\TaskStatusType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskStatusType>
 */
class TaskStatusTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskStatusType::class);
    }

    /**
     * @return TaskStatusType[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?TaskStatusType
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return TaskStatusType[]
     */
    public function findByParentType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.parentType = :type')
            ->setParameter('type', $type)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TaskStatusType[]
     */
    public function findOpenStatuses(): array
    {
        return $this->findByParentType(TaskStatusType::PARENT_TYPE_OPEN);
    }

    /**
     * @return TaskStatusType[]
     */
    public function findClosedStatuses(): array
    {
        return $this->findByParentType(TaskStatusType::PARENT_TYPE_CLOSED);
    }

    public function findDefaultForType(string $type): ?TaskStatusType
    {
        return $this->findOneBy([
            'parentType' => $type,
            'isDefault' => true,
        ]);
    }

    public function findDefaultOpen(): ?TaskStatusType
    {
        return $this->findDefaultForType(TaskStatusType::PARENT_TYPE_OPEN);
    }

    public function findDefaultClosed(): ?TaskStatusType
    {
        return $this->findDefaultForType(TaskStatusType::PARENT_TYPE_CLOSED);
    }

    public function countTasksWithStatus(TaskStatusType $status): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('App\Entity\Task', 't')
            ->where('t.statusType = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMaxSortOrder(): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * @return array{open: TaskStatusType[], closed: TaskStatusType[]}
     */
    public function findGroupedByType(): array
    {
        $statuses = $this->findAllOrdered();
        $grouped = [
            'open' => [],
            'closed' => [],
        ];

        foreach ($statuses as $status) {
            $grouped[$status->getParentType()][] = $status;
        }

        return $grouped;
    }
}
