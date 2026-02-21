<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectInvitation;
use App\Entity\User;
use App\Enum\ProjectInvitationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectInvitation>
 */
class ProjectInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectInvitation::class);
    }

    public function findByToken(string $token): ?ProjectInvitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findPendingByEmail(string $email): ?ProjectInvitation
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.email = :email')
            ->andWhere('pi.status IN (:statuses)')
            ->setParameter('email', $email)
            ->setParameter('statuses', [
                ProjectInvitationStatus::PENDING->value,
                ProjectInvitationStatus::PENDING_ADMIN_APPROVAL->value,
            ])
            ->orderBy('pi.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPendingByProjectAndEmail(Project $project, string $email): ?ProjectInvitation
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.project = :project')
            ->andWhere('pi.email = :email')
            ->andWhere('pi.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('email', $email)
            ->setParameter('statuses', [
                ProjectInvitationStatus::PENDING->value,
                ProjectInvitationStatus::PENDING_ADMIN_APPROVAL->value,
            ])
            ->orderBy('pi.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ProjectInvitation[]
     */
    public function findPendingAdminApprovals(): array
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.status = :status')
            ->setParameter('status', ProjectInvitationStatus::PENDING_ADMIN_APPROVAL)
            ->orderBy('pi.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProjectInvitation[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.email = :email OR pi.invitedUser = :user')
            ->setParameter('email', $user->getEmail())
            ->setParameter('user', $user)
            ->orderBy('pi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
