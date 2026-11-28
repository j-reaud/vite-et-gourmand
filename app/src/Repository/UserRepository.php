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

    /**
     * @return User[]
     */
    public function findEmployees(): array
    {
        $employeeIds = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT id FROM user
                WHERE JSON_CONTAINS(roles, :employeeRole) = 1',
                [
                    'employeeRole' => json_encode(
                        'ROLE_EMPLOYEE',
                        JSON_THROW_ON_ERROR
                    ),
                ]
            )
            ->fetchFirstColumn();

        if ([] === $employeeIds) {
            return [];
        }

        return $this->createQueryBuilder('user')
            ->andWhere('user.id IN (:employeeIds)')
            ->setParameter('employeeIds', $employeeIds)
            ->orderBy('user.email', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function countActiveEmployees(): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT COUNT(*) FROM user
                WHERE JSON_CONTAINS(roles, :employeeRole) = 1
                AND is_active = 1',
                [
                    'employeeRole' => json_encode(
                        'ROLE_EMPLOYEE',
                        JSON_THROW_ON_ERROR,
                    ),
                ],
            );
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
