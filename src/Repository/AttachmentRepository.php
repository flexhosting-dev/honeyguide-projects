<?php

namespace App\Repository;

use App\Entity\Attachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /**
     * @return Attachment[]
     */
    public function findByAttachable(string $type, UuidInterface $id): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.attachableType = :type')
            ->andWhere('a.attachableId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $id->toString())
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
