<?php

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerOrder>
 */
class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    /**
     * @return list<CustomerOrder>
     */
    public function findForCustomer(
        User $customer,
        ?string $search = null,
        ?string $status = null,
        ?int $period = null,
    ): array {
        $queryBuilder = $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.customer = :customer')
            ->setParameter('customer', $customer);

        $search = trim((string) $search);

        if ($search !== '') {
            $searchCondition = 'LOWER(customerOrder.menuName) LIKE :search';

            if (preg_match('/^(?:CMD-)?0*(\d+)$/i', $search, $matches) === 1) {
                $searchCondition = $queryBuilder->expr()->orX(
                    $searchCondition,
                    'customerOrder.id = :orderId',
                );

                $queryBuilder->setParameter('orderId', (int) $matches[1]);
            }

            $queryBuilder
                ->andWhere($searchCondition)
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if (
            $status !== null
            && in_array($status, CustomerOrder::STATUSES, true)
        ) {
            $queryBuilder
                ->andWhere('customerOrder.status = :status')
                ->setParameter('status', $status);
        }

        if ($period !== null && in_array($period, [3, 6, 12], true)) {
            $queryBuilder
                ->andWhere('customerOrder.orderedAt >= :periodStart')
                ->setParameter(
                    'periodStart',
                    new \DateTimeImmutable(sprintf('-%d months', $period)),
                );
        }

        return $queryBuilder
            ->orderBy('customerOrder.orderedAt', 'DESC')
            ->addOrderBy('customerOrder.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForCustomer(int $id, User $customer): ?CustomerOrder
    {
        return $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.id = :id')
            ->andWhere('customerOrder.customer = :customer')
            ->setParameter('id', $id)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
