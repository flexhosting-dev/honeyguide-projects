<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return User[]
     */
    public function findAllWithSearch(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC');

        if (!empty($search)) {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return User[]
     */
    public function findPortalAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.portalRole', 'r')
            ->andWhere('r.slug IN (:slugs)')
            ->setParameter('slugs', ['portal-super-admin', 'portal-admin'])
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active projects for a user (owned + member of)
     */
    public function getActiveProjectsCount(User $user): int
    {
        $em = $this->getEntityManager();

        // Count owned projects that are active
        $ownedCount = $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Project', 'p')
            ->where('p.owner = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        // Count projects where user is a member (and project is active)
        $memberCount = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT pm.project)')
            ->from('App\Entity\ProjectMember', 'pm')
            ->join('pm.project', 'p')
            ->where('pm.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $ownedCount + (int) $memberCount;
    }
}
